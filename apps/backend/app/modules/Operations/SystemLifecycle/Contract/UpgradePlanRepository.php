<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Contract;

use app\modules\Operations\SystemLifecycle\Domain\StoredUpgradePlan;

interface UpgradePlanRepository
{
    public function create(StoredUpgradePlan $plan): StoredUpgradePlan;
    public function find(string $planId): ?StoredUpgradePlan;
    /** @return StoredUpgradePlan[] */
    public function recent(int $limit = 50): array;
    public function existsUnsettled(?string $excludingPlanId = null): bool;
    /** 唯一未结束计划；领取事务可锁住计划，与步骤切换串行。 */
    public function unsettled(bool $lock = false): ?StoredUpgradePlan;
    public function save(StoredUpgradePlan $plan, int $expectedRevision): StoredUpgradePlan;
}
