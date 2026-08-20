<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Domain;

final class UpgradePlanStep
{
    private string $name;
    private string $state;
    private int $attempts;
    private ?string $cursor;
    private ?string $failureCode;

    private function __construct(string $name, string $state, int $attempts, ?string $cursor, ?string $failureCode)
    {
        UpgradeStep::assertValid($name);
        UpgradeStepState::assertValid($state);
        if ($attempts < 0) throw new \InvalidArgumentException('Upgrade Step attempts 不能为负数');
        if ($cursor !== null && ($cursor === '' || strlen($cursor) > 2048)) throw new \InvalidArgumentException('Upgrade Step cursor 不合法');
        if ($state === UpgradeStepState::FAILED && ($failureCode === null || $failureCode === '')) {
            throw new \InvalidArgumentException('失败的 Upgrade Step 必须包含失败代码');
        }
        if ($state !== UpgradeStepState::FAILED && $failureCode !== null) {
            throw new \InvalidArgumentException('非失败 Upgrade Step 不能包含失败代码');
        }
        $this->name = $name;
        $this->state = $state;
        $this->attempts = $attempts;
        $this->cursor = $cursor;
        $this->failureCode = $failureCode;
    }

    public static function pending(string $name): self { return new self($name, UpgradeStepState::PENDING, 0, null, null); }

    /** @param array<string, mixed> $data */
    public static function restore(array $data): self
    {
        return new self(
            (string) ($data['name'] ?? ''),
            (string) ($data['state'] ?? ''),
            (int) ($data['attempts'] ?? -1),
            isset($data['cursor']) ? (string) $data['cursor'] : null,
            isset($data['failure_code']) ? (string) $data['failure_code'] : null
        );
    }

    public function start(): self
    {
        if (!in_array($this->state, [UpgradeStepState::PENDING, UpgradeStepState::FAILED, UpgradeStepState::RUNNING, UpgradeStepState::WAITING], true)) {
            throw new \LogicException('当前 Upgrade Step 不能开始');
        }
        return new self($this->name, UpgradeStepState::RUNNING, $this->attempts + 1, $this->cursor, null);
    }

    public function checkpoint(?string $cursor): self
    {
        if ($this->state !== UpgradeStepState::RUNNING) throw new \LogicException('只有运行中的 Upgrade Step 可以保存游标');
        return new self($this->name, $this->state, $this->attempts, $cursor, null);
    }

    public function waitForApproval(): self
    {
        if (!in_array($this->state, [UpgradeStepState::PENDING, UpgradeStepState::RUNNING], true)) {
            throw new \LogicException('当前 Upgrade Step 不能等待审批');
        }
        return new self($this->name, UpgradeStepState::WAITING, $this->attempts, $this->cursor, null);
    }

    public function succeed(): self
    {
        if ($this->state !== UpgradeStepState::RUNNING) throw new \LogicException('只有运行中的 Upgrade Step 可以成功');
        return new self($this->name, UpgradeStepState::SUCCEEDED, $this->attempts, null, null);
    }

    public function fail(string $failureCode): self
    {
        $failureCode = trim($failureCode);
        if ($this->state !== UpgradeStepState::RUNNING || $failureCode === '' || strlen($failureCode) > 128) {
            throw new \LogicException('Upgrade Step 失败状态不合法');
        }
        return new self($this->name, UpgradeStepState::FAILED, $this->attempts, $this->cursor, $failureCode);
    }

    public function skip(): self
    {
        if ($this->state !== UpgradeStepState::PENDING || !UpgradeStep::isOptional($this->name)) {
            throw new \LogicException('只有未开始的可选 Upgrade Step 可以跳过');
        }
        return new self($this->name, UpgradeStepState::SKIPPED, $this->attempts, null, null);
    }

    public function name(): string { return $this->name; }
    public function state(): string { return $this->state; }
    public function attempts(): int { return $this->attempts; }
    public function cursor(): ?string { return $this->cursor; }
    public function failureCode(): ?string { return $this->failureCode; }

    /** @return array<string, int|string|null> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'state' => $this->state,
            'attempts' => $this->attempts,
            'cursor' => $this->cursor,
            'failure_code' => $this->failureCode,
        ];
    }
}
