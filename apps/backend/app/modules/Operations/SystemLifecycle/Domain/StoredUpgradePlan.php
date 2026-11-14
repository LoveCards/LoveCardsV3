<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Domain;

final class StoredUpgradePlan
{
    public const RECOVERED = 'recovered';

    /** @param array<string, mixed> $context */
    public function __construct(
        private UpgradePlan $plan,
        private ?string $previousReleaseId,
        private bool $contractApproved,
        private array $context,
        private ?int $createdBy = null
    ) {
        if ($previousReleaseId !== null
            && !preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $previousReleaseId)) {
            throw new \InvalidArgumentException('Previous Release ID 不合法');
        }
        if ($createdBy !== null && $createdBy < 1) throw new \InvalidArgumentException('Upgrade Plan actor 不合法');
    }

    public function planVersion(): int { return (int) ($this->context['plan_version'] ?? 1); }

    public function plan(): UpgradePlan { return $this->plan; }
    public function previousReleaseId(): ?string { return $this->previousReleaseId; }
    public function contractApproved(): bool { return $this->contractApproved; }
    /** @return array<string, mixed> */
    public function context(): array { return $this->context; }
    public function createdBy(): ?int { return $this->createdBy; }
    public function status(): string
    {
        return isset($this->context['recovered_at']) ? self::RECOVERED : $this->plan->status();
    }

    public function withPlan(UpgradePlan $plan): self
    {
        return new self($plan, $this->previousReleaseId, $this->contractApproved, $this->context, $this->createdBy);
    }

    /** @param array<string, mixed> $context */
    public function withContext(array $context): self
    {
        return new self($this->plan, $this->previousReleaseId, $this->contractApproved, $context, $this->createdBy);
    }

    public function approveContract(): self
    {
        return new self($this->plan, $this->previousReleaseId, true, $this->context, $this->createdBy);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $data = $this->plan->toArray();
        $data['status'] = $this->status();
        $data['plan_version'] = $this->planVersion();
        $data['legacy_plan'] = $this->planVersion() === 1;
        return $data + [
            'previous_release_id' => $this->previousReleaseId,
            'contract_approved' => $this->contractApproved,
            'context' => $this->context,
            'created_by' => $this->createdBy,
        ];
    }
}
