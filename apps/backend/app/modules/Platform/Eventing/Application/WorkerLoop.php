<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Application;

use app\modules\Operations\Runtime\Public\WorkerHeartbeat;
use app\modules\Operations\Runtime\Public\WorkerHeartbeatStore;
use app\modules\Operations\Runtime\Public\WorkerState;
use app\modules\Platform\Eventing\Contract\ClaimTokenGenerator;
use app\modules\Platform\Eventing\Contract\Clock;
use app\modules\Platform\Eventing\Contract\DeliveryRepository;
use app\modules\Platform\Eventing\Contract\Sleeper;
use app\modules\Platform\Eventing\Domain\DeliveryClaim;
use app\modules\Platform\Eventing\Domain\ExecutionReport;
use app\modules\Platform\Eventing\Domain\WorkerOptions;
use app\modules\Platform\Eventing\Public\ExecutionMode;
use app\modules\Platform\Eventing\Public\HandlerRegistry;

final class WorkerLoop
{
    private DeliveryRepository $deliveries;
    private DeliveryProcessor $processor;
    private HandlerRegistry $handlers;
    private Clock $clock;
    private ClaimTokenGenerator $tokens;
    private Sleeper $sleeper;
    private WorkerHeartbeatStore $heartbeats;

    public function __construct(
        DeliveryRepository $deliveries,
        DeliveryProcessor $processor,
        HandlerRegistry $handlers,
        Clock $clock,
        ClaimTokenGenerator $tokens,
        Sleeper $sleeper,
        WorkerHeartbeatStore $heartbeats
    ) {
        $this->deliveries = $deliveries;
        $this->processor = $processor;
        $this->handlers = $handlers;
        $this->clock = $clock;
        $this->tokens = $tokens;
        $this->sleeper = $sleeper;
        $this->heartbeats = $heartbeats;
    }

    public function run(WorkerOptions $options, ?callable $shouldStop = null): ExecutionReport
    {
        $report = new ExecutionReport();
        $startedAt = $this->clock->monotonicMilliseconds();
        $lastHeartbeatAt = $startedAt;
        $this->beat($options, WorkerState::STARTING, 0);
        $this->beat($options, WorkerState::RUNNING, 0);

        try {
            while (true) {
                if ($shouldStop !== null && $shouldStop()) {
                    break;
                }
                if ($options->maxJobs() > 0 && $report->claimed() >= $options->maxJobs()) {
                    break;
                }
                $monotonicNow = $this->clock->monotonicMilliseconds();
                if ($options->maxRuntimeMilliseconds() > 0
                    && $monotonicNow - $startedAt >= $options->maxRuntimeMilliseconds()) {
                    break;
                }
                if ($monotonicNow - $lastHeartbeatAt >= $options->heartbeatMilliseconds()) {
                    $this->beat($options, WorkerState::RUNNING, $report->claimed());
                    $lastHeartbeatAt = $monotonicNow;
                }

                $now = $this->clock->now();
                $claimed = $this->deliveries->claim(new DeliveryClaim(
                    $options->workerId(),
                    $options->queueNames(),
                    [ExecutionMode::REQUEST_SAFE, ExecutionMode::DAEMON_REQUIRED],
                    1,
                    $this->tokens->generate(),
                    $now,
                    $now->modify('+' . $options->leaseSeconds() . ' seconds')
                ));
                if ($claimed === []) {
                    $this->sleeper->sleepMilliseconds($options->idleSleepMilliseconds());
                    continue;
                }

                $report->addClaimed();
                $report->addOutcome($this->processor->process($claimed[0]));
            }
        } finally {
            $this->beat($options, WorkerState::STOPPING, $report->claimed());
            $this->beat($options, WorkerState::STOPPED, $report->claimed());
        }

        return $report;
    }

    private function beat(WorkerOptions $options, string $state, int $jobsProcessed): void
    {
        $this->heartbeats->record(new WorkerHeartbeat(
            $options->workerId(),
            'eventing',
            $state,
            $options->releaseId(),
            $options->schemaVersion(),
            $this->handlers->catalogHash(),
            $jobsProcessed,
            $this->clock->now()
        ));
    }
}
