<?php
declare(strict_types=1);

namespace app\shared\Runtime;

use think\facade\Config;

/** Lightweight probes with no hard dependency on optional extensions/services. */
final class NativeFacilityProbe implements FacilityProbe
{
    public function probe(string $facility, string $provider): FacilityProbeResult
    {
        if ($provider === 'mysql') {
            try { \think\facade\Db::query('SELECT 1'); return FacilityProbeResult::healthy(); }
            catch (\Throwable $e) { return FacilityProbeResult::unreachable('mysql_unreachable'); }
        }
        if ($provider === 'redis') {
            if (!extension_loaded('redis')) return FacilityProbeResult::unsupported('redis_extension_missing');
            $cfg = Config::get('cache.stores.redis', []);
            $host = (string) ($cfg['host'] ?? '127.0.0.1'); $port = (int) ($cfg['port'] ?? 6379);
            $socket = @fsockopen($host, $port, $errno, $errstr, 0.25);
            if (!$socket) return FacilityProbeResult::unreachable('redis_unreachable');
            fclose($socket); return FacilityProbeResult::healthy();
        }
        if ($provider === 'php-worker') {
            try {
                $worker = \think\facade\Db::table('runtime_workers')
                    ->where('worker_type', 'eventing')
                    ->where('state', 'running')
                    ->where('heartbeat_at', '>=', date('Y-m-d H:i:s', time() - 90))
                    ->find();
                return $worker ? FacilityProbeResult::healthy() : FacilityProbeResult::unreachable('worker_heartbeat_stale');
            } catch (\Throwable $e) {
                return FacilityProbeResult::unreachable('worker_status_unavailable');
            }
        }
        if ($provider === 'realtime-gateway') {
            return $this->probeRealtimeGateway();
        }
        return FacilityProbeResult::unsupported('provider_unknown');
    }

    private function probeRealtimeGateway(): FacilityProbeResult
    {
        $baseUrl = trim((string) env('LC_REALTIME_INTERNAL_URL', ''));
        $secret = (string) env('LC_REALTIME_INTERNAL_SECRET', '');
        if ($baseUrl === '' || $secret === '') {
            return FacilityProbeResult::unreachable('gateway_not_configured');
        }

        $parts = parse_url($baseUrl);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || !in_array($parts['scheme'], ['http', 'https'], true)) {
            return FacilityProbeResult::unhealthy('gateway_url_invalid');
        }

        $path = rtrim((string) ($parts['path'] ?? ''), '/') . '/internal/health';
        $url = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) $url .= ':' . (int) $parts['port'];
        $url .= $path;

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 0.5,
                'ignore_errors' => true,
                'follow_location' => 0,
                'header' => "Authorization: Bearer {$secret}\r\nAccept: application/json\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $context);
        $status = 0;
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $match)) {
                $status = (int) $match[1];
                break;
            }
        }
        if ($body === false || $status !== 200) {
            return FacilityProbeResult::unreachable($status === 401 || $status === 403 ? 'gateway_auth_failed' : 'gateway_unreachable');
        }

        $payload = json_decode($body, true);
        if (!is_array($payload) || ($payload['status'] ?? null) !== 'ready' || ($payload['service'] ?? null) !== 'realtime-gateway' || ($payload['protocol'] ?? null) !== 'lc-realtime-1') {
            return FacilityProbeResult::unhealthy('gateway_protocol_mismatch');
        }
        $capabilities = is_array($payload['capabilities'] ?? null) ? $payload['capabilities'] : [];
        $topics = is_array($capabilities['topics'] ?? null) ? $capabilities['topics'] : [];
        foreach (['sse', 'cursor', 'last_event_id'] as $capability) {
            if (($capabilities[$capability] ?? false) !== true) {
                return FacilityProbeResult::unhealthy('gateway_capability_missing');
            }
        }
        if (!in_array('notifications', $topics, true) || !in_array('messages', $topics, true)) {
            return FacilityProbeResult::unhealthy('gateway_topics_missing');
        }

        return FacilityProbeResult::healthy();
    }
}
