<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Application;

use app\modules\Platform\Eventing\Contract\EventWorkerHeartbeatPort;
use app\modules\Platform\Eventing\Contract\ClaimTokenGenerator;
use app\modules\Platform\Eventing\Contract\Clock;
use app\modules\Platform\Eventing\Contract\DeliveryRepository;
use app\modules\Platform\Eventing\Contract\Sleeper;
use app\modules\Platform\Eventing\Domain\DeliveryClaim;
use app\modules\Platform\Eventing\Domain\ExecutionReport;
use app\modules\Platform\Eventing\Domain\WorkerOptions;
use app\modules\Platform\Eventing\Public\ExecutionMode;
use app\modules\Platform\Eventing\Public\EventConsumptionConflict;
use app\modules\Platform\Eventing\Public\HandlerRegistry;

final class WorkerLoop
{
    private DeliveryRepository $deliveries;
    private DeliveryProcessor $processor;
    private HandlerRegistry $handlers;
    private Clock $clock;
    private ClaimTokenGenerator $tokens;
    private Sleeper $sleeper;
    private EventWorkerHeartbeatPort $heartbeats;

    public function __construct(
        DeliveryRepository $deliveries,
        DeliveryProcessor $processor,
        HandlerRegistry $handlers,
        Clock $clock,
        ClaimTokenGenerator $tokens,
        Sleeper $sleeper,
        EventWorkerHeartbeatPort $heartbeats
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
        $this->beat($options, 'starting', 0);
        $this->beat($options, 'running', 0);

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
                    $this->beat($options, 'running', $report->claimed());
                    $lastHeartbeatAt = $monotonicNow;
                }

                $now = $this->clock->now();
                try {
                    $claimed = $this->deliveries->claim(new DeliveryClaim(
                        $options->workerId(),
                        $options->queueNames(),
                        [ExecutionMode::REQUEST_SAFE, ExecutionMode::DAEMON_REQUIRED],
                        1,
                        $this->tokens->generate(),
                        $now,
                        $now->modify('+' . $options->leaseSeconds() . ' seconds'),
                        'resident'
                    ));
                } catch (EventConsumptionConflict $exception) {
                    if (!in_array($exception->reasonCode(), ['mode-not-selected', 'transition-draining', 'lifecycle-not-qualified'], true)) {
                        throw $exception;
                    }
                    // 进程可保持存活供后续切换使用，但在未选策略下绝不领取。
                    $this->sleeper->sleepMilliseconds($options->idleSleepMilliseconds());
                    continue;
                }
                if ($claimed === []) {
                    $this->sleeper->sleepMilliseconds($options->idleSleepMilliseconds());
                    continue;
                }

                $report->addClaimed();
                $report->addOutcome($this->processor->process($claimed[0]));
            }
        } finally {
            $this->beat($options, 'stopping', $report->claimed());
            $this->beat($options, 'stopped', $report->claimed());
        }

        return $report;
    }

    private function beat(WorkerOptions $options, string $state, int $jobsProcessed): void
    {
        $this->heartbeats->record(
            $options->workerId(),
            $state,
            $options->releaseId(),
            $options->schemaVersion(),
            $this->handlers->catalogHash(),
            $options->queueNames(),
            $jobsProcessed,
            $this->clock->now()
        );
    }
}
