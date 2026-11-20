<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Public;

use app\modules\Operations\SystemLifecycle\Domain\StoredUpgradePlan;
use app\modules\Operations\SystemLifecycle\Domain\UpgradeStep;
use app\modules\Operations\SystemLifecycle\Domain\UpgradeStepState;

/** 显式启用的升级计划在首次迁移前暂停旧消费者。 */
final class UpgradeConsumerFence
{
    public static function blocks(?StoredUpgradePlan $plan): bool
    {
        if ($plan === null || ($plan->context()['scheduler_fence_version'] ?? null) !== 1
            || isset($plan->context()['pointer_restored_at'])) return false;
        if (self::capturingProof($plan)) return true;
        $step = $plan->plan()->current()?->name();
        return in_array($step, [
            UpgradeStep::PREFLIGHT_MIGRATIONS,
            UpgradeStep::EXPAND_MIGRATIONS,
            UpgradeStep::BACKFILL,
            UpgradeStep::VERIFY_MIGRATIONS,
            UpgradeStep::DRAIN_RUNTIME,
            UpgradeStep::ACTIVATE_RELEASE,
        ], true);
    }

    public static function capturingProof(?StoredUpgradePlan $plan): bool
    {
        return $plan !== null
            && ($plan->context()['scheduler_fence_version'] ?? null) === 1
            && $plan->plan()->current()?->name() === UpgradeStep::BACKUP_GATE
            && $plan->plan()->current()?->state() === UpgradeStepState::RUNNING;
    }
}
