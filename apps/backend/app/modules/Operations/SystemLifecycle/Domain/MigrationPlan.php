<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Domain;

final class MigrationPlan
{
    /** @var MigrationPlanStep[] */
    private array $steps;

    /** @param MigrationPlanStep[] $steps */
    public function __construct(array $steps)
    {
        foreach ($steps as $step) {
            if (!$step instanceof MigrationPlanStep) throw new \InvalidArgumentException('MigrationPlan 只能包含 MigrationPlanStep');
        }
        $this->steps = array_values($steps);
    }

    /** @return MigrationPlanStep[] */
    public function steps(): array { return $this->steps; }

    public function hasWork(bool $allowContract = false): bool
    {
        foreach ($this->steps as $step) {
            if ($step->state() === MigrationPlanStep::COMPLETE) continue;
            if ($step->state() === MigrationPlanStep::WAITING && !$allowContract) continue;
            return true;
        }
        return false;
    }

    /** @return array<int, array<string, mixed>> */
    public function toArray(): array
    {
        return array_map(static function (MigrationPlanStep $step): array { return $step->toArray(); }, $this->steps);
    }
}
