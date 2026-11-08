<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Infrastructure;

use app\modules\Operations\Runtime\Contract\RuntimeProbe;

/**
 * Supervisor 进程治理探测：deployment policy 决定是否为硬依赖。
 *
 * Phase 1 只落策略位：require_supervisor=true 时诚实报缺失（systemd/supervisord
 * adapter 随部署形态立项实现），false 时记 not-required（不参与阻断判定）。
 */
final class SupervisorPolicyProbe implements RuntimeProbe
{
    public function key(): string
    {
        return 'supervisor';
    }

    public function probe(array $settings): array
    {
        if (($settings['require_supervisor'] ?? false) === true) {
            return ['ready' => false, 'provider' => null, 'reason_code' => 'supervisor-required-missing'];
        }
        return ['ready' => null, 'provider' => null, 'reason_code' => 'not-required'];
    }
}
