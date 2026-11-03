<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Domain;

final class UpgradePlan
{
    public const PENDING = 'pending';
    public const RUNNING = 'running';
    public const WAITING = 'waiting';
    public const FAILED = 'failed';
    public const COMPLETED = 'completed';

    private string $planId;
    private string $releaseId;
    private int $revision;
    /** @var array<string, UpgradePlanStep> */
    private array $steps;

    /** @param UpgradePlanStep[] $steps */
    private function __construct(string $planId, string $releaseId, array $steps, int $revision = 0)
    {
        self::assertIdentifier($planId, 'Upgrade Plan ID');
        self::assertIdentifier($releaseId, 'Release ID');
        if ($revision < 0) throw new \InvalidArgumentException('Upgrade Plan revision 不能为负数');
        $indexed = [];
        foreach ($steps as $step) {
            if (!$step instanceof UpgradePlanStep) throw new \InvalidArgumentException('UpgradePlan 只能包含 UpgradePlanStep');
            if (isset($indexed[$step->name()])) throw new \InvalidArgumentException('Upgrade Step 重复: ' . $step->name());
            $indexed[$step->name()] = $step;
        }
        if ($indexed === []) throw new \InvalidArgumentException('UpgradePlan 至少需要一个 Step');
        self::assertStateOrder(array_values($indexed));
        $this->planId = $planId;
        $this->releaseId = $releaseId;
        $this->steps = $indexed;
        $this->revision = $revision;
    }

    /** @param string[]|null $stepNames */
    public static function create(string $planId, string $releaseId, ?array $stepNames = null): self
    {
        $stepNames = $stepNames ?? UpgradeStep::ordered();
        return new self(
            $planId,
            $releaseId,
            array_map(static function (string $name): UpgradePlanStep { return UpgradePlanStep::pending($name); }, $stepNames),
            0
        );
    }

    /** @param array<string, mixed> $data */
    public static function restore(array $data): self
    {
        $steps = $data['steps'] ?? null;
        if (!is_array($steps)) throw new \InvalidArgumentException('UpgradePlan steps 不合法');
        return new self(
            (string) ($data['plan_id'] ?? ''),
            (string) ($data['release_id'] ?? ''),
            array_map(static function ($step): UpgradePlanStep {
                if (!is_array($step)) throw new \InvalidArgumentException('UpgradePlan step 不合法');
                return UpgradePlanStep::restore($step);
            }, $steps),
            (int) ($data['revision'] ?? 0)
        );
    }

    public function start(string $stepName): self
    {
        $this->assertCurrent($stepName);
        return $this->replace($this->steps[$stepName]->start());
    }

    public function checkpoint(string $stepName, ?string $cursor): self
    {
        return $this->replace($this->requiredStep($stepName)->checkpoint($cursor));
    }

    public function waitForApproval(string $stepName): self
    {
        $this->assertCurrent($stepName);
        return $this->replace($this->steps[$stepName]->waitForApproval());
    }

    public function succeed(string $stepName): self
    {
        return $this->replace($this->requiredStep($stepName)->succeed());
    }

    public function fail(string $stepName, string $failureCode): self
    {
        return $this->replace($this->requiredStep($stepName)->fail($failureCode));
    }

    public function skip(string $stepName): self
    {
        $this->assertCurrent($stepName);
        return $this->replace($this->steps[$stepName]->skip());
    }

    public function status(): string
    {
        $hasProgress = false;
        foreach ($this->steps as $step) {
            if ($step->state() === UpgradeStepState::FAILED) return self::FAILED;
            if ($step->state() === UpgradeStepState::WAITING) return self::WAITING;
            if (!UpgradeStepState::isTerminal($step->state())) {
                if ($step->state() === UpgradeStepState::RUNNING) return self::RUNNING;
                continue;
            }
            $hasProgress = true;
        }
        foreach ($this->steps as $step) if (!UpgradeStepState::isTerminal($step->state())) return $hasProgress ? self::RUNNING : self::PENDING;
        return self::COMPLETED;
    }

    public function current(): ?UpgradePlanStep
    {
        foreach ($this->steps as $step) {
            if (!UpgradeStepState::isTerminal($step->state())) return $step;
        }
        return null;
    }

    public function planId(): string { return $this->planId; }
    public function releaseId(): string { return $this->releaseId; }
    public function revision(): int { return $this->revision; }
    /** @return UpgradePlanStep[] */
    public function steps(): array { return array_values($this->steps); }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'plan_id' => $this->planId,
            'release_id' => $this->releaseId,
            'revision' => $this->revision,
            'status' => $this->status(),
            'steps' => array_map(static function (UpgradePlanStep $step): array { return $step->toArray(); }, $this->steps()),
        ];
    }

    private function replace(UpgradePlanStep $replacement): self
    {
        $steps = $this->steps;
        $steps[$replacement->name()] = $replacement;
        return new self($this->planId, $this->releaseId, array_values($steps), $this->revision);
    }

    /** 由持久化实现完成 CAS 后调用。 */
    public function withRevision(int $revision): self
    {
        if ($revision <= $this->revision) throw new \InvalidArgumentException('Upgrade Plan revision 必须单调递增');
        return new self($this->planId, $this->releaseId, $this->steps(), $revision);
    }

    private function assertCurrent(string $stepName): void
    {
        $step = $this->requiredStep($stepName);
        $current = $this->current();
        if ($current === null || $current->name() !== $step->name()) {
            throw new \LogicException('Upgrade Step 不能越序执行: ' . $stepName);
        }
    }

    private function requiredStep(string $stepName): UpgradePlanStep
    {
        if (!isset($this->steps[$stepName])) throw new \InvalidArgumentException('UpgradePlan 不包含 Step: ' . $stepName);
        return $this->steps[$stepName];
    }

    /** @param UpgradePlanStep[] $steps */
    private static function assertStateOrder(array $steps): void
    {
        $foundUnresolved = false;
        $activeCount = 0;
        foreach ($steps as $step) {
            $terminal = UpgradeStepState::isTerminal($step->state());
            if ($foundUnresolved && $terminal) throw new \InvalidArgumentException('UpgradePlan 状态顺序不合法');
            if (!$terminal) $foundUnresolved = true;
            if (in_array($step->state(), [UpgradeStepState::RUNNING, UpgradeStepState::WAITING, UpgradeStepState::FAILED], true)) $activeCount++;
        }
        if ($activeCount > 1) throw new \InvalidArgumentException('UpgradePlan 同时存在多个活动 Step');
    }

    private static function assertIdentifier(string $value, string $field): void
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/', $value)) throw new \InvalidArgumentException($field . ' 不合法');
    }
}
