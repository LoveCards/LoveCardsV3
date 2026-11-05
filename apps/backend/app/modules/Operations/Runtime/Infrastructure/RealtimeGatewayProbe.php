<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Infrastructure;

use app\modules\Operations\Runtime\Contract\RuntimeProbe;
use think\facade\Env;

/**
 * Realtime Gateway 能力探测（契约 §4.3，验收 #7：禁止只查端口）。
 *
 * GET {LC_REALTIME_GATEWAY_URL}/internal/health + Bearer LC_RUNTIME_INTERNAL_TOKEN，
 * 校验 protocol 版本、cursor 能力、ticket 鉴权能力与自报 ready。
 * 结果按 gateway_stale_seconds 进程内缓存。transport=source-poll 时能力仍 ready，
 * 但带 realtime-transport-degraded 注记（effective 不因传输退化回落）。
 */
final class RealtimeGatewayProbe implements RuntimeProbe
{
    public const PROTOCOL = 'lc-realtime/1';

    /** @var array{expiresAt:int,payload:array}|null */
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
        $token = (string) Env::get('LC_RUNTIME_INTERNAL_TOKEN', '');
        if ($token === '') {
            return ['ready' => false, 'provider' => $provider, 'reason_code' => 'gateway-auth-unavailable'];
        }

        $ttl = max(1, (int) ($settings['gateway_stale_seconds'] ?? 15));
        if (self::$cache !== null && self::$cache['expiresAt'] > time()) {
            $payload = self::$cache['payload'];
            $payload['note'] = array_merge($payload['note'] ?? [], ['cached' => true]);
            return $payload;
        }

        $result = $this->fetchHealth($base, $token);
        self::$cache = ['expiresAt' => time() + $ttl, 'payload' => $result];
        return $result;
    }

    /** 测试与运维对账用：清空进程内缓存。 */
    public static function resetCache(): void
    {
        self::$cache = null;
    }

    private function fetchHealth(string $base, string $token): array
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
        if (($payload['protocol'] ?? null) !== self::PROTOCOL || ($payload['cursor'] ?? null) !== true) {
            return ['ready' => false, 'provider' => 'realtime-gateway', 'reason_code' => 'gateway-protocol-mismatch'];
        }
        $auth = $payload['auth'] ?? [];
        if (!is_array($auth) || !in_array('ticket', $auth, true)) {
            return ['ready' => false, 'provider' => 'realtime-gateway', 'reason_code' => 'gateway-auth-unavailable'];
        }
        if (($payload['ready'] ?? null) !== true) {
            return ['ready' => false, 'provider' => 'realtime-gateway', 'reason_code' => 'gateway-not-ready'];
        }
        $transport = (string) ($payload['transport'] ?? 'redis');
        return [
            'ready' => true,
            'provider' => 'realtime-gateway',
            'reason_code' => $transport === 'source-poll' ? 'realtime-transport-degraded' : null,
            'note' => ['version' => $payload['version'] ?? null, 'transport' => $transport],
        ];
    }
}
