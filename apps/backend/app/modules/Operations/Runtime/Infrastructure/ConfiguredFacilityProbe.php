<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Infrastructure;

use app\modules\Platform\Config\Public\RedisConnectionSettings;
use app\shared\Runtime\FacilityProbe;
use app\shared\Runtime\FacilityProbeResult;
use app\shared\Runtime\NativeFacilityProbe;

/** 旧设施诊断复用系统 Redis 配置与完整连接测试。 */
final class ConfiguredFacilityProbe implements FacilityProbe
{
    public function __construct(private NativeFacilityProbe $native) {}

    public function probe(string $facility, string $provider): FacilityProbeResult
    {
        if ($facility !== 'eventing.wakeup' || $provider !== 'redis') {
            return $this->native->probe($facility, $provider);
        }

        $result = RedisConnectionSettings::test();
        if ($result['ready']) {
            return FacilityProbeResult::healthy();
        }
        if ($result['reason_code'] === 'redis-extension-unavailable') {
            return FacilityProbeResult::unsupported($result['reason_code']);
        }
        return FacilityProbeResult::unreachable($result['reason_code'] ?? 'redis-unreachable');
    }
}
