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
    public function save(StoredUpgradePlan $plan, int $expectedRevision): StoredUpgradePlan;
}
