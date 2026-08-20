<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Infrastructure;

use app\modules\Operations\SystemLifecycle\Contract\UpgradePlanRepository;
use app\modules\Operations\SystemLifecycle\Domain\StoredUpgradePlan;
use app\modules\Operations\SystemLifecycle\Domain\UpgradePlan;
use app\modules\Operations\SystemLifecycle\Domain\UpgradePlanStep;
use app\modules\Operations\SystemLifecycle\Domain\UpgradeStepState;
use think\facade\Db;

final class ThinkDbUpgradePlanRepository implements UpgradePlanRepository
{
    public function create(StoredUpgradePlan $stored): StoredUpgradePlan
    {
        $plan = $stored->plan();
        $status = $stored->status();
        Db::startTrans();
        try {
            $now = date('Y-m-d H:i:s.u');
            $planPk = (int) Db::table('system_upgrade_plans')->insertGetId([
                'plan_id' => $plan->planId(),
                'release_id' => $plan->releaseId(),
                'previous_release_id' => $stored->previousReleaseId(),
                'status' => $status,
                'current_step' => $plan->current()?->name(),
                'contract_approved' => $stored->contractApproved() ? 1 : 0,
                'context_json' => $this->encode($stored->context()),
                'revision' => 0,
                'created_by' => $stored->createdBy(),
                'created_at' => $now,
                'updated_at' => $now,
                'completed_at' => null,
                'failed_at' => null,
            ]);
            $this->replaceSteps($planPk, $plan->steps(), $now);
            Db::commit();
            return $stored;
        } catch (\Throwable $exception) {
            Db::rollback();
            throw $exception;
        }
    }

    public function find(string $planId): ?StoredUpgradePlan
    {
        $row = Db::table('system_upgrade_plans')->where('plan_id', $planId)->find();
        return $row ? $this->hydrate($row) : null;
    }

    public function recent(int $limit = 50): array
    {
        $limit = min(200, max(1, $limit));
        return array_map(
            fn(array $row): StoredUpgradePlan => $this->hydrate($row),
            Db::table('system_upgrade_plans')->order('id', 'desc')->limit($limit)->select()->toArray()
        );
    }

    public function existsUnsettled(?string $excludingPlanId = null): bool
    {
        $table = Db::query(
            "SELECT 1 AS present FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'system_upgrade_plans' LIMIT 1"
        );
        if ($table === []) return false;

        $query = Db::table('system_upgrade_plans')
            ->whereNotIn('status', [UpgradePlan::COMPLETED, StoredUpgradePlan::RECOVERED]);
        if ($excludingPlanId !== null) {
            $query->where('plan_id', '<>', $excludingPlanId);
        }
        return $query->limit(1)->value('id') !== null;
    }

    public function save(StoredUpgradePlan $stored, int $expectedRevision): StoredUpgradePlan
    {
        $plan = $stored->plan();
        $status = $stored->status();
        if ($plan->revision() !== $expectedRevision) throw new \InvalidArgumentException('Upgrade Plan expected revision 不匹配');
        Db::startTrans();
        try {
            $now = date('Y-m-d H:i:s.u');
            $updated = Db::table('system_upgrade_plans')
                ->where('plan_id', $plan->planId())
                ->where('revision', $expectedRevision)
                ->update([
                    'status' => $status,
                    'current_step' => $plan->current()?->name(),
                    'contract_approved' => $stored->contractApproved() ? 1 : 0,
                    'context_json' => $this->encode($stored->context()),
                    'revision' => $expectedRevision + 1,
                    'updated_at' => $now,
                    'completed_at' => in_array($status, [UpgradePlan::COMPLETED, StoredUpgradePlan::RECOVERED], true) ? $now : null,
                    'failed_at' => $status === UpgradePlan::FAILED ? $now : null,
                ]);
            if ($updated !== 1) throw new \RuntimeException('Upgrade Plan CAS 冲突');
            $row = Db::table('system_upgrade_plans')->where('plan_id', $plan->planId())->find();
            if (!$row) throw new \RuntimeException('Upgrade Plan 持久化后丢失');
            $planPk = (int) $row['id'];
            $this->replaceSteps($planPk, $plan->steps(), $now);
            Db::commit();
            return $stored->withPlan($plan->withRevision($expectedRevision + 1));
        } catch (\Throwable $exception) {
            Db::rollback();
            throw $exception;
        }
    }

    /** @param UpgradePlanStep[] $steps */
    private function replaceSteps(int $planPk, array $steps, string $now): void
    {
        Db::table('system_upgrade_plan_steps')->where('plan_pk', $planPk)->delete();
        $rows = [];
        foreach ($steps as $ordinal => $step) {
            $rows[] = [
                'plan_pk' => $planPk,
                'step_name' => $step->name(),
                'ordinal' => $ordinal,
                'state' => $step->state(),
                'attempts' => $step->attempts(),
                'cursor' => $step->cursor(),
                'failure_code' => $step->failureCode(),
                'started_at' => $step->attempts() > 0 ? $now : null,
                'completed_at' => UpgradeStepState::isTerminal($step->state()) ? $now : null,
                'updated_at' => $now,
            ];
        }
        if ($rows !== []) Db::table('system_upgrade_plan_steps')->insertAll($rows);
    }

    private function hydrate(array $row): StoredUpgradePlan
    {
        $steps = Db::table('system_upgrade_plan_steps')
            ->where('plan_pk', (int) $row['id'])
            ->order('ordinal', 'asc')
            ->select()->toArray();
        $plan = UpgradePlan::restore([
            'plan_id' => $row['plan_id'],
            'release_id' => $row['release_id'],
            'revision' => (int) $row['revision'],
            'steps' => array_map(static fn(array $step): array => [
                'name' => $step['step_name'],
                'state' => $step['state'],
                'attempts' => (int) $step['attempts'],
                'cursor' => $step['cursor'],
                'failure_code' => $step['failure_code'],
            ], $steps),
        ]);
        try {
            $context = json_decode((string) ($row['context_json'] ?? '{}'), true, 128, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Upgrade Plan context_json 损坏', 0, $exception);
        }
        return new StoredUpgradePlan(
            $plan,
            isset($row['previous_release_id']) ? (string) $row['previous_release_id'] : null,
            (bool) $row['contract_approved'],
            is_array($context) ? $context : [],
            isset($row['created_by']) ? (int) $row['created_by'] : null
        );
    }

    /** @param array<string,mixed> $value */
    private function encode(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
