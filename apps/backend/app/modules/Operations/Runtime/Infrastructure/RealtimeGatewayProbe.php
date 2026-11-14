<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Infrastructure;

use app\modules\Operations\Runtime\Contract\RuntimeProbe;
use app\modules\Platform\Config\Public\RedisConnectionSettings;
use think\facade\Env;

/**
 * Realtime Gateway 能力探测（契约 §4.3，验收 #7：禁止只查端口）。
 *
 * GET {LC_REALTIME_GATEWAY_URL}/internal/health + Bearer LC_RUNTIME_INTERNAL_TOKEN，
 * 校验 protocol 版本、topic 内读取提示能力、ticket 鉴权能力与自报 ready。
 * 结果按 gateway_stale_seconds 进程内缓存。实时模式必须使用 Redis 信号通道；
 * Gateway 回源不再作为正常传输。
 */
final class RealtimeGatewayProbe implements RuntimeProbe
{
    public const PROTOCOL = 'lc-realtime/1';

    /** @var array{expiresAt:int,payload:array,configRevision:string}|null */
    private static ?array $cache = null;

    public function key(): string
    {
        return 'realtime-gateway';
    }

    public function probe(array $settings): array
    {
        $provider = 'realtime-gateway';
        $base = rtrim((string) Env::get('LC_REALTIME_GATEWAY_URL', ''), '/');
        if ($base === '') {
            return ['ready' => false, 'provider' => null, 'reason_code' => 'gateway-not-configured'];
        }
        // 内部 health 可达还不够；浏览器必须有一个可公开连接的 Gateway 地址，
        // 否则 effective=realtime 会让客户端拿到无法使用的空 connect_url。
        $public = rtrim((string) Env::get('LC_REALTIME_GATEWAY_PUBLIC_URL', ''), '/');
        if ($public === '') {
            return ['ready' => false, 'provider' => $provider, 'reason_code' => 'gateway-public-url-not-configured'];
        }
        $token = (string) Env::get('LC_RUNTIME_INTERNAL_TOKEN', '');
        if ($token === '') {
            return ['ready' => false, 'provider' => $provider, 'reason_code' => 'gateway-auth-unavailable'];
        }

        $configRevision = RedisConnectionSettings::current()['revision'];
        $ttl = max(1, (int) ($settings['gateway_stale_seconds'] ?? 15));
        if (self::$cache !== null && self::$cache['expiresAt'] > time()
            && self::$cache['configRevision'] === $configRevision) {
            $payload = self::$cache['payload'];
            $payload['note'] = array_merge($payload['note'] ?? [], ['cached' => true]);
            return $payload;
        }

        $result = $this->fetchHealth($base, $token, $configRevision);
        self::$cache = ['expiresAt' => time() + $ttl, 'payload' => $result, 'configRevision' => $configRevision];
        return $result;
    }

    /** 测试与运维对账用：清空进程内缓存。 */
    public static function resetCache(): void
    {
        self::$cache = null;
    }

    private function fetchHealth(string $base, string $token, string $configRevision): array
    {
        $context = stream_context_create(['http' => [
            'timeout' => 2,
            'ignore_errors' => true,
            'header' => "Authorization: Bearer {$token}\r\nAccept: application/json\r\n",
        ]]);
        $body = @file_get_contents($base . '/internal/health', false, $context);
        if ($body === false) {
            return ['ready' => false, 'provider' => 'realtime-gateway', 'reason_code' => 'gateway-unreachable'];
        }
        $status = 0;
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) $status = (int) $matches[1];
        }
        if ($status === 401 || $status === 403) {
            return ['ready' => false, 'provider' => 'realtime-gateway', 'reason_code' => 'gateway-auth-unavailable'];
        }
        if ($status < 200 || $status >= 300) {
            return ['ready' => false, 'provider' => 'realtime-gateway', 'reason_code' => 'gateway-unreachable'];
        }
        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            return ['ready' => false, 'provider' => 'realtime-gateway', 'reason_code' => 'gateway-protocol-mismatch'];
        }
        // cursor=true 只表示 topic 内读取提示，不代表跨 topic 的 SSE 续传游标。
        $sessionTtl = $payload['session_ttl_max_seconds'] ?? null;
        if (($payload['protocol'] ?? null) !== self::PROTOCOL || ($payload['cursor'] ?? null) !== true
            || !is_int($sessionTtl) || $sessionTtl < 1 || $sessionTtl > 60) {
            return ['ready' => false, 'provider' => 'realtime-gateway', 'reason_code' => 'gateway-protocol-mismatch'];
        }
        $auth = $payload['auth'] ?? [];
        if (!is_array($auth) || !in_array('ticket', $auth, true)) {
            return ['ready' => false, 'provider' => 'realtime-gateway', 'reason_code' => 'gateway-auth-unavailable'];
        }
        if (($payload['ready'] ?? null) !== true) {
            return ['ready' => false, 'provider' => 'realtime-gateway', 'reason_code' => 'gateway-not-ready'];
        }
        $transport = (string) ($payload['transport'] ?? '');
        if (($payload['redis_config_version'] ?? null) !== $configRevision) {
            return ['ready' => false, 'provider' => 'realtime-gateway', 'reason_code' => 'gateway-config-mismatch'];
        }
        if ($transport !== 'redis') {
            return [
                'ready' => false,
                'provider' => 'realtime-gateway',
                'reason_code' => 'realtime-transport-unavailable',
                'note' => ['version' => $payload['version'] ?? null, 'transport' => $transport],
            ];
        }
        return [
            'ready' => true,
            'provider' => 'realtime-gateway',
            'reason_code' => null,
            'note' => ['version' => $payload['version'] ?? null, 'transport' => $transport],
        ];
    }
}
