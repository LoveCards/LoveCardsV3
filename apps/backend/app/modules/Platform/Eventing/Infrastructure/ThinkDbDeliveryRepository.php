<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Infrastructure;

use app\modules\Platform\Eventing\Contract\DeliveryRepository;
use app\modules\Platform\Eventing\Contract\PolicyVersionSource;
use app\modules\Platform\Eventing\Domain\ClaimedDelivery;
use app\modules\Platform\Eventing\Domain\DeliveryClaim;
use app\modules\Platform\Eventing\Domain\DeliveryDefinition;
use app\modules\Platform\Eventing\Domain\DeliveryFailure;
use app\modules\Platform\Eventing\Domain\DeliveryStatus;
use app\modules\Operations\Runtime\Public\RuntimeCommunication;
use think\facade\Db;

final class ThinkDbDeliveryRepository implements DeliveryRepository
{
    public function __construct(private ?PolicyVersionSource $policyVersions = null)
    {
    }

    public function enqueue(int $eventId, array $definitions, \DateTimeImmutable $availableAt): array
    {
        if ($eventId < 1) {
            throw new \InvalidArgumentException('event_id 必须为正整数');
        }
        $deliveryIds = [];
        $now = $availableAt->format('Y-m-d H:i:s.u');
        // 登记时冻结当时的全局策略版本；无目录返回 null（not-configured）。
        $frozenRevision = $this->policyVersions?->currentRevision();
        $signalState = null;
        foreach ($definitions as $definition) {
            if (!$definition instanceof DeliveryDefinition) {
                throw new \InvalidArgumentException('Delivery definition 类型不合法');
            }
            if ($definition->queueName() === 'realtime' && $signalState === null) {
                $signalState = app(RuntimeCommunication::class)->selectedModeForEnqueue();
            }
            $skipSignal = $definition->queueName() === 'realtime' && $signalState === 'polling';
            $deliveryIds[] = (int) Db::table('event_deliveries')->insertGetId([
                'event_id' => $eventId,
                'handler_key' => $definition->handlerKey(),
                'queue_name' => $definition->queueName(),
                'execution_mode' => $definition->executionMode(),
                'policy_revision' => $frozenRevision,
                'status' => $skipSignal ? DeliveryStatus::COMPLETED : DeliveryStatus::PENDING,
                'policy_decision' => $skipSignal ? 'skipped' : null,
                'attempt_count' => 0,
                'available_at' => $now,
                'claim_token' => null,
                'claimed_by' => null,
                'lease_expires_at' => null,
                'last_error' => null,
                'completed_at' => $skipSignal ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $deliveryIds;
    }

    public function claim(DeliveryClaim $claim): array
    {
        $queues = $claim->queueNames();
        if (in_array('realtime', $queues, true)
            && app(RuntimeCommunication::class)->signalDeliveryState() !== 'deliver') {
            $queues = array_values(array_diff($queues, ['realtime']));
        }
        if ($queues === []) return [];
        $now = $claim->now()->format('Y-m-d H:i:s.u');
        $leaseUntil = $claim->leaseUntil()->format('Y-m-d H:i:s.u');

        // 单条 UPDATE + claim_token 回读兼容 MySQL 5.7，不依赖 SKIP LOCKED。
        Db::table('event_deliveries')
            ->whereIn('queue_name', $queues)
            ->whereIn('execution_mode', $claim->executionModes())
            ->where(function ($query) use ($now): void {
                $query->where(function ($pending) use ($now): void {
                    $pending->where('status', DeliveryStatus::PENDING)
                        ->where('available_at', '<=', $now);
                })->whereOr(function ($expired) use ($now): void {
                    $expired->where('status', DeliveryStatus::PROCESSING)
                        ->where('lease_expires_at', '<', $now);
                });
            })
            ->order('available_at', 'asc')
            ->order('id', 'asc')
            ->limit($claim->limit())
            ->update([
                'status' => DeliveryStatus::PROCESSING,
                'claim_token' => $claim->claimToken(),
                'claimed_by' => $claim->workerId(),
                'lease_expires_at' => $leaseUntil,
                'attempt_count' => Db::raw('attempt_count + 1'),
                'updated_at' => $now,
            ]);

        $rows = Db::table('event_deliveries')
            ->where('claim_token', $claim->claimToken())
            ->where('status', DeliveryStatus::PROCESSING)
            ->order('id', 'asc')
            ->select()
            ->toArray();

        return array_map(static function (array $row): ClaimedDelivery {
            return new ClaimedDelivery(
                (int) $row['id'],
                (int) $row['event_id'],
                (string) $row['handler_key'],
                (string) $row['queue_name'],
                (string) $row['execution_mode'],
                (int) $row['attempt_count'],
                (string) $row['claim_token'],
                new \DateTimeImmutable((string) $row['lease_expires_at'], new \DateTimeZone('UTC')),
                isset($row['policy_revision']) && $row['policy_revision'] !== null ? (int) $row['policy_revision'] : null
            );
        }, $rows);
    }

    /** 管理员切回轮询后，结算不再需要的主动提示；业务数据由 API 读取。 */
    public function settleRealtimeForPolling(): int
    {
        if (app(RuntimeCommunication::class)->signalDeliveryState() !== 'skip') {
            throw new \LogicException('只能在轮询模式结算实时信号');
        }
        $now = gmdate('Y-m-d H:i:s.u');
        return Db::table('event_deliveries')
            ->where('queue_name', 'realtime')
            ->whereIn('status', [DeliveryStatus::PENDING, DeliveryStatus::PROCESSING])
            ->update([
                'status' => DeliveryStatus::COMPLETED,
                'policy_decision' => 'skipped',
                'claim_token' => null,
                'claimed_by' => null,
                'lease_expires_at' => null,
                'last_error' => null,
                'completed_at' => $now,
                'updated_at' => $now,
            ]);
    }

    public function notePolicyDecision(int $deliveryId, string $decision): void
    {
        if (!in_array($decision, ['applied', 'skipped', 'not-configured'], true)) {
            throw new \InvalidArgumentException('policy decision 不合法');
        }
        Db::table('event_deliveries')
            ->where('id', $deliveryId)
            ->update(['policy_decision' => $decision, 'updated_at' => gmdate('Y-m-d H:i:s.u')]);
    }

    public function complete(int $deliveryId, string $claimToken, \DateTimeImmutable $completedAt, ?string $policyDecision = null): bool
    {
        if ($policyDecision !== null && !in_array($policyDecision, ['applied', 'skipped'], true)) {
            throw new \InvalidArgumentException('Realtime signal 决策不合法');
        }
        $now = $completedAt->format('Y-m-d H:i:s.u');
        $changes = [
            'status' => DeliveryStatus::COMPLETED,
            'claim_token' => null,
            'lease_expires_at' => null,
            'last_error' => null,
            'completed_at' => $now,
            'updated_at' => $now,
        ];
        if ($policyDecision !== null) $changes['policy_decision'] = $policyDecision;
        $updated = Db::table('event_deliveries')
            ->where('id', $deliveryId)
            ->where('claim_token', $claimToken)
            ->where('status', DeliveryStatus::PROCESSING)
            ->update($changes);

        return $updated === 1;
    }

    public function fail(
        int $deliveryId,
        string $claimToken,
        DeliveryFailure $failure,
        \DateTimeImmutable $failedAt
    ): bool {
        $updated = Db::table('event_deliveries')
            ->where('id', $deliveryId)
            ->where('claim_token', $claimToken)
            ->where('status', DeliveryStatus::PROCESSING)
            ->update([
                'status' => $failure->terminal() ? DeliveryStatus::DEAD : DeliveryStatus::PENDING,
                'claim_token' => null,
                'lease_expires_at' => null,
                'last_error' => $failure->error(),
                'available_at' => $failure->availableAt()->format('Y-m-d H:i:s.u'),
                'completed_at' => null,
                'updated_at' => $failedAt->format('Y-m-d H:i:s.u'),
            ]);

        return $updated === 1;
    }

    public function defer(int $deliveryId, string $claimToken, DeliveryFailure $failure, \DateTimeImmutable $deferredAt): bool
    {
        $updated = Db::table('event_deliveries')
            ->where('id', $deliveryId)
            ->where('claim_token', $claimToken)
            ->where('status', DeliveryStatus::PROCESSING)
            ->update([
                'status' => DeliveryStatus::PENDING,
                'claim_token' => null,
                'claimed_by' => null,
                'lease_expires_at' => null,
                'attempt_count' => Db::raw('GREATEST(attempt_count - 1, 0)'),
                'last_error' => $failure->error(),
                'available_at' => $failure->availableAt()->format('Y-m-d H:i:s.u'),
                'completed_at' => null,
                'updated_at' => $deferredAt->format('Y-m-d H:i:s.u'),
            ]);
        return $updated === 1;
    }
}
