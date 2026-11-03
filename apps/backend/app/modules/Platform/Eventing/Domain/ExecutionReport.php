<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Domain;

final class ExecutionReport
{
    private int $claimed = 0;
    private int $completed = 0;
    private int $retryScheduled = 0;
    private int $dead = 0;
    private int $fenced = 0;

    public function addClaimed(): void { $this->claimed++; }

    public function addOutcome(string $outcome): void
    {
        if ($outcome === DeliveryOutcome::COMPLETED) $this->completed++;
        elseif ($outcome === DeliveryOutcome::RETRY_SCHEDULED) $this->retryScheduled++;
        elseif ($outcome === DeliveryOutcome::DEAD) $this->dead++;
        elseif ($outcome === DeliveryOutcome::FENCED) $this->fenced++;
        else throw new \InvalidArgumentException("未知 Delivery outcome：{$outcome}");
    }

    public function claimed(): int { return $this->claimed; }
    public function completed(): int { return $this->completed; }
    public function retryScheduled(): int { return $this->retryScheduled; }
    public function dead(): int { return $this->dead; }
    public function fenced(): int { return $this->fenced; }

    public function toArray(): array
    {
        return [
            'claimed' => $this->claimed,
            'completed' => $this->completed,
            'retry_scheduled' => $this->retryScheduled,
            'dead' => $this->dead,
            'fenced' => $this->fenced,
        ];
    }
}
