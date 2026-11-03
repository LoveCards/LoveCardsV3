<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Domain;

final class MigrationPlanStep
{
    public const PENDING = 'pending';
    public const RESUME = 'resume';
    public const WAITING = 'waiting';
    public const COMPLETE = 'complete';

    private MigrationDescriptor $descriptor;
    private ?MigrationProgress $progress;
    private string $state;

    public function __construct(MigrationDescriptor $descriptor, ?MigrationProgress $progress)
    {
        $this->descriptor = $descriptor;
        $this->progress = $progress;
        if ($progress === null) $this->state = self::PENDING;
        elseif ($progress->isComplete()) $this->state = self::COMPLETE;
        elseif ($progress->status() === MigrationStatus::WAITING) $this->state = self::WAITING;
        else $this->state = self::RESUME;
    }

    public function descriptor(): MigrationDescriptor { return $this->descriptor; }
    public function progress(): ?MigrationProgress { return $this->progress; }
    public function state(): string { return $this->state; }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'migration' => $this->descriptor->toArray(),
            'state' => $this->state,
            'progress' => $this->progress ? $this->progress->toArray() : null,
        ];
    }
}
