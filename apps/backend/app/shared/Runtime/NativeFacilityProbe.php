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
        return FacilityProbeResult::unsupported('provider_unknown');
    }
}
