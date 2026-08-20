<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Application;

use app\modules\Platform\Eventing\Contract\ClaimTokenGenerator;
use app\modules\Platform\Eventing\Contract\Clock;
use app\modules\Platform\Eventing\Contract\DeliveryRepository;
use app\modules\Platform\Eventing\Domain\DeliveryClaim;
use app\modules\Platform\Eventing\Domain\ExecutionReport;
use app\modules\Platform\Eventing\Domain\RequestExecutionBudget;
use app\modules\Platform\Eventing\Public\ExecutionMode;

final class RequestExecutor
{
    private DeliveryRepository $deliveries;
    private DeliveryProcessor $processor;
    private Clock $clock;
    private ClaimTokenGenerator $tokens;

    public function __construct(
        DeliveryRepository $deliveries,
        DeliveryProcessor $processor,
        Clock $clock,
        ClaimTokenGenerator $tokens
    ) {
        $this->deliveries = $deliveries;
        $this->processor = $processor;
        $this->clock = $clock;
        $this->tokens = $tokens;
    }

    public function run(RequestExecutionBudget $budget): ExecutionReport
    {
        $report = new ExecutionReport();
        $startedAt = $this->clock->monotonicMilliseconds();

        while ($report->claimed() < $budget->maxJobs()) {
            if ($this->clock->monotonicMilliseconds() - $startedAt >= $budget->maxMilliseconds()) {
                break;
            }

            $now = $this->clock->now();
            $claimed = $this->deliveries->claim(new DeliveryClaim(
                $budget->workerId(),
                $budget->queueNames(),
                [ExecutionMode::REQUEST_SAFE],
                1,
                $this->tokens->generate(),
                $now,
                $now->modify('+' . $budget->leaseSeconds() . ' seconds')
            ));
            if ($claimed === []) {
                break;
            }

            $report->addClaimed();
            $report->addOutcome($this->processor->process($claimed[0]));
        }

        return $report;
    }
}
