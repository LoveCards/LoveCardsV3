<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Infrastructure;

use app\modules\Operations\SystemLifecycle\Contract\ReleaseRuntime;

/** Native FPM/CLI runtime with optional, argv-only worker lifecycle hooks. */
final class NativeReleaseRuntime implements ReleaseRuntime
{
    private const RELEASE_HEADER = 'x-lovecards-release-id';

    /** @param array<string,mixed>|null $configuration */
    public function __construct(private ?array $configuration = null, private ?string $sapi = null) {}

    public function canDrain(): bool
    {
        return $this->command('drain_command') !== [];
    }

    public function assertQuiescent(string $releaseId): void
    {
        if (!$this->canDrain()) throw new \LogicException('Contract 需要配置可确认全部消费者停止的 drain_command');
        $proof = $this->drainWithPhase($releaseId, 'contract');
        if (($proof['web_requests_quiesced'] ?? null) !== true) {
            throw new \RuntimeException('drain_command 未证明旧 Web 请求已排空，禁止 Contract');
        }
        // drain 成功代表部署侧确认进程退出；过期租约/过期心跳不能代替该证据。
        \app\modules\Platform\Eventing\Public\EventConsumption::assertQuiescent();
        \app\modules\Operations\Runtime\Public\RuntimeCommunication::assertWorkersStopped();
    }

    public function drain(string $releaseId): void
    {
        $this->drainWithPhase($releaseId, 'activation');
    }

    /** @return array<string,mixed> */
    private function drainWithPhase(string $releaseId, string $phase): array
    {
        $proof = trim($this->run('drain_command', $releaseId, $phase));
        try { $result = json_decode($proof, true, 8, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { $result = null; }
        if (!is_array($result) || ($result['workers_stopped'] ?? null) !== true
            || ($result['scheduled_trigger_stopped'] ?? null) !== true
            || ($result['restart_disabled'] ?? null) !== true) {
            throw new \RuntimeException('drain_command 未提供 Worker、定时触发及自动重启入口全部停止的有效证明');
        }
        return $result;
    }

    public function canReload(): bool
    {
        return $this->command('reload_command') !== [];
    }

    public function reload(string $releaseId): void
    {
        $this->run('reload_command', $releaseId);
    }

    public function healthCheck(string $releaseId): void
    {
        $this->assertReleaseId($releaseId);
        $active = getenv('LC_ACTIVE_RELEASE_ID');
        $active = is_string($active) ? $active : '';
        $sapi = $this->sapi ?? PHP_SAPI;

        // The health step is a later HTTP request than activation. Requiring
        // this marker proves that FPM actually dispatched the request through
        // the target release bootstrap, without a deadlock-prone self-request.
        if (!in_array($sapi, ['cli', 'phpdbg'], true)) {
            if (!hash_equals($releaseId, $active)) {
                throw new \RuntimeException('Web 运行时尚未切换到目标 Release');
            }
            return;
        }

        $healthUrl = trim((string) ($this->config()['health_url'] ?? ''));
        if ($healthUrl !== '') {
            $this->probeWeb($healthUrl, $releaseId);
            return;
        }
        if (!hash_equals($releaseId, $active)) {
            throw new \RuntimeException('CLI 尚未由目标 Release 启动；请重新执行或配置 LC_RUNTIME_HEALTH_URL');
        }
    }

    private function run(string $name, string $releaseId, string $phase = ''): string
    {
        $this->assertReleaseId($releaseId);
        $command = $this->command($name);
        if ($command === []) throw new \LogicException('当前运行时未配置 ' . $name);
        if (!function_exists('proc_open')) throw new \RuntimeException('当前 PHP 禁用了 proc_open，无法管理 worker');
        $command = array_map(
            static fn(string $argument): string => str_replace(['{release_id}', '{phase}'], [$releaseId, $phase], $argument),
            $command
        );
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];
        // Passing an argv array is deliberate: PHP bypasses the command shell.
        $process = proc_open($command, $descriptors, $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) throw new \RuntimeException('无法启动运行时管理命令');

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $stdout = '';
        $stderr = '';
        $deadline = microtime(true) + $this->commandTimeout();
        $status = ['running' => true, 'exitcode' => -1];
        try {
            while (true) {
                $stdout = $this->appendOutput($stdout, (string) stream_get_contents($pipes[1]));
                $stderr = $this->appendOutput($stderr, (string) stream_get_contents($pipes[2]));
                $status = proc_get_status($process);
                if (!($status['running'] ?? false)) break;
                if (microtime(true) >= $deadline) {
                    proc_terminate($process);
                    throw new \app\modules\Operations\SystemLifecycle\Exception\ExecutionUncertainException('运行时管理命令超时，子进程结束状态需受控核实');
                }
                usleep(10000);
            }
        } finally {
            $stdout = $this->appendOutput($stdout, (string) stream_get_contents($pipes[1]));
            $stderr = $this->appendOutput($stderr, (string) stream_get_contents($pipes[2]));
            fclose($pipes[1]);
            fclose($pipes[2]);
            $closedExit = proc_close($process);
        }
        $exitCode = (int) (($status['exitcode'] ?? -1) >= 0 ? $status['exitcode'] : $closedExit);
        if ($exitCode !== 0) {
            $detail = trim($stderr !== '' ? $stderr : $stdout);
            throw new \RuntimeException('运行时管理命令失败，退出码 ' . $exitCode . ($detail === '' ? '' : ': ' . $detail));
        }
        return $stdout;
    }

    /** @return string[] */
    private function command(string $name): array
    {
        $raw = $this->config()[$name] ?? '[]';
        if (is_string($raw)) {
            if (trim($raw) === '') return [];
            try {
                $raw = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw new \RuntimeException('运行时命令配置不是合法 JSON argv', 0, $exception);
            }
        }
        if (!is_array($raw) || !array_is_list($raw)) {
            throw new \RuntimeException('运行时命令必须是 JSON argv 数组');
        }
        $command = [];
        foreach ($raw as $argument) {
            if (!is_string($argument) || $argument === '' || strlen($argument) > 4096 || str_contains($argument, "\0")) {
                throw new \RuntimeException('运行时命令包含不合法参数');
            }
            $command[] = $argument;
        }
        if (count($command) > 64) throw new \RuntimeException('运行时命令参数过多');
        return $command;
    }

    /** @return array<string,mixed> */
    private function config(): array
    {
        if ($this->configuration !== null) return $this->configuration;
        $configured = config('lifecycle.runtime', []);
        return is_array($configured) ? $configured : [];
    }

    private function commandTimeout(): int
    {
        $value = (int) ($this->config()['command_timeout_seconds'] ?? 30);
        return min(300, max(1, $value));
    }

    private function probeWeb(string $url, string $releaseId): void
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new \RuntimeException('LC_RUNTIME_HEALTH_URL 必须是无凭据的 HTTP(S) URL');
        }
        // A health probe is initiated by the application process. Keep the
        // default deliberately local so an env/config typo cannot turn an
        // upgrade into an SSRF primitive. Remote probes require an explicit
        // opt-in and are still URL/credential validated above.
        $host = strtolower(trim((string) $parts['host'], '[]'));
        $allowRemote = in_array((string) ($this->config()['health_allow_remote'] ?? false), ['1', 'true'], true)
            || $this->config()['health_allow_remote'] === true;
        if (!$allowRemote && !in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new \RuntimeException('LC_RUNTIME_HEALTH_URL 默认只能指向本机；如需远程探测请显式启用 health_allow_remote');
        }
        $timeout = min(30, max(1, (int) ($this->config()['health_timeout_seconds'] ?? 5)));
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $timeout,
                'ignore_errors' => true,
                'header' => "Accept: application/json\r\nConnection: close\r\n",
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $context);
        $headers = isset($http_response_header) && is_array($http_response_header) ? $http_response_header : [];
        $status = 0;
        $actual = '';
        foreach ($headers as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $line, $match) === 1) $status = (int) $match[1];
            $separator = strpos($line, ':');
            if ($separator !== false && strtolower(trim(substr($line, 0, $separator))) === self::RELEASE_HEADER) {
                $actual = trim(substr($line, $separator + 1));
            }
        }
        if ($body === false || $status < 200 || $status >= 300 || !hash_equals($releaseId, $actual)) {
            throw new \RuntimeException('Web 健康探测未确认目标 Release');
        }
    }

    private function assertReleaseId(string $releaseId): void
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $releaseId) !== 1) {
            throw new \InvalidArgumentException('Release ID 不合法');
        }
    }

    private function appendOutput(string $buffer, string $chunk): string
    {
        $buffer .= $chunk;
        return strlen($buffer) > 8192 ? substr($buffer, -8192) : $buffer;
    }
}
