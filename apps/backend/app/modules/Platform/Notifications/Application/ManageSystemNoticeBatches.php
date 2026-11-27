<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Application;

use app\api\ApiException;
use app\modules\Platform\Eventing\Public\DeliveryContext;
use app\modules\Platform\Eventing\Public\DeliveryProgress;
use app\modules\Platform\Eventing\Public\DeliveryRetries;
use app\modules\Platform\Eventing\Public\DomainEventBus;
use app\modules\Platform\Notifications\Contract\SystemNoticeBatchRepository;
use app\modules\Platform\Notifications\Contract\SystemNoticeIssuer;
use app\modules\Platform\Notifications\Domain\SystemNotice;
use app\modules\Platform\Notifications\Domain\SystemNoticeBatch;
use app\modules\Platform\User\Public\NotificationRecipients;
use app\shared\Authorization\GrantSet;
use app\shared\Observability\AuditLogger;
use app\shared\Transaction\TransactionManager;

/** 冻结预览、受权发布和有界续批；通知正文与收件记录只由 Notifications 持有。 */
final class ManageSystemNoticeBatches
{
    public function __construct(
        private SystemNoticeBatchRepository $batches,
        private TransactionManager $transactions,
        private DomainEventBus $events,
        private SystemNoticeIssuer $issuer,
        private AuditLogger $audit
    ) {}

    public function prepare(array $input, int $actorId, GrantSet $grants, string $ip = ''): array
    {
        try { $normalized = SystemNoticeBatch::normalize($input); }
        catch (\InvalidArgumentException $error) { throw ApiException::badRequest($error->getMessage()); }
        $this->assertPublisher($actorId, $grants, $normalized['audience']);
        $hash = SystemNoticeBatch::requestHash($normalized);
        try {
            return $this->batches->inSnapshot(function () use ($normalized, $actorId, $hash): array {
                $existing = $this->batches->findRequest($actorId, $normalized['request_key']);
                if ($existing !== null) return $this->sameRequest($existing, $hash);
                $selected = $normalized['audience'] === 'selected' ? NotificationRecipients::snapshot($normalized['recipient_ids']) : [];
                $ids = array_column($selected, 'id');
                if ($normalized['audience'] === 'selected' && $ids !== $normalized['recipient_ids']) {
                    throw ApiException::validation(['recipient_ids' => ['包含不存在、已注销或访客账号，请重新选择收件人。']]);
                }
                if ($normalized['audience'] === 'selected' && $ids === []) throw ApiException::validation(['audience' => ['当前范围内没有可接收系统通知的正式账号。']]);
                $now = date('Y-m-d H:i:s');
                $batchId = $this->batches->insert([
                    'creator_id' => $actorId, 'request_key' => $normalized['request_key'], 'request_hash' => $hash,
                    'audience' => $normalized['audience'], 'message' => $normalized['message'],
                    'action_url' => $normalized['action_url'], 'severity' => $normalized['severity'],
                    'selected_recipients' => $selected, 'recipient_count' => count($ids),
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                if ($normalized['audience'] === 'all') {
                    $count = 0;
                    foreach (NotificationRecipients::snapshotIdChunks() as $chunk) {
                        $this->batches->addRecipients($batchId, $chunk);
                        $count += count($chunk);
                    }
                    if ($count === 0) throw ApiException::validation(['audience' => ['当前范围内没有可接收系统通知的正式账号。']]);
                    $this->batches->update($batchId, ['recipient_count' => $count]);
                } else {
                    $this->batches->addRecipients($batchId, $ids);
                }
                return $this->receipt($this->owned($batchId, $actorId));
            });
        } catch (\Throwable $error) {
            // 并发的相同请求由唯一键串行化；只有请求内容完全相同才重用已提交批次。
            $existing = $this->batches->findRequest($actorId, $normalized['request_key']);
            if ($existing !== null) {
                if (!hash_equals($existing['request_hash'], $hash)) throw $this->conflict('相同发布请求标识已用于其他内容，请重新预览。');
                return $this->get((int) $existing['id'], $actorId);
            }
            throw $error;
        }
    }

    public function get(int $batchId, int $actorId): array
    {
        return $this->batches->inSnapshot(fn(): array => $this->receipt($this->owned($batchId, $actorId)));
    }

    public function latest(int $actorId, ?string $requestKey = null): ?array
    {
        if ($actorId < 1) throw ApiException::forbidden('操作者身份无效');
        return $this->batches->inSnapshot(function () use ($actorId, $requestKey): ?array {
            $batch = $requestKey !== null ? $this->batches->findRequest($actorId, $requestKey) : $this->batches->latest($actorId);
            return $batch === null ? null : $this->receipt($batch);
        });
    }

    public function confirm(int $batchId, int $actorId, GrantSet $grants, string $ip = ''): array
    {
        $this->transactions->run(function () use ($batchId, $actorId, $grants, $ip): void {
            $batch = $this->owned($batchId, $actorId, true);
            $this->assertPublisher($actorId, $grants, $batch['audience']);
            if (!empty($batch['confirmed_at'])) return;
            $batch['confirmed_at'] = date('Y-m-d H:i:s');
            $batch['dispatch_generation'] = 1;
            $this->batches->update($batchId, ['confirmed_at' => $batch['confirmed_at'], 'dispatch_generation' => 1]);
            $this->queueChunk($batchId, $actorId, 1, 0);
            $this->audit->record($actorId, 'admin.system_notice.publish', 'system_notice_batch', (string) $batchId, [
                'audience' => $batch['audience'], 'recipient_count' => (int) $batch['recipient_count'],
                'content_hash' => hash('sha256', $batch['message']), 'severity' => $batch['severity'],
                'action_url' => $batch['action_url'], 'snapshot_at' => $batch['created_at'], '_ip' => $ip,
            ]);
        });
        return $this->get($batchId, $actorId);
    }

    public function retry(int $batchId, int $actorId, GrantSet $grants, array $input, string $ip = ''): array
    {
        $expected = $input['expected_generation'] ?? null;
        $reason = trim((string) ($input['reason'] ?? ''));
        if (!is_int($expected) || $expected < 1 || $reason === '' || mb_strlen($reason) > 255) {
            throw ApiException::validation(['reason' => ['重试须提供当前代次与 1 至 255 字的原因。']]);
        }
        $this->transactions->run(function () use ($batchId, $actorId, $grants, $expected, $reason, $ip): void {
            $batch = $this->owned($batchId, $actorId, true);
            $this->assertPublisher($actorId, $grants, $batch['audience']);
            $generation = (int) $batch['dispatch_generation'];
            if ($expected < $generation) return;
            if ($expected !== $generation || empty($batch['confirmed_at'])) throw $this->conflict('批次状态已变化，请刷新后核对。');
            $receipt = $this->receipt($batch);
            if (!$receipt['can_retry']) return;
            $nextGeneration = $generation + 1;
            $changes = ['dispatch_generation' => $nextGeneration, 'retry_reason' => $reason];
            if (!(bool) $batch['dispatch_complete']) {
                // 续批自身失败时恢复原投递，保留已提交游标；不能遗留被新任务取代的死信。
                $changed = DeliveryRetries::retry((int) $batch['dispatch_delivery_id'], function (array $before, array $after) use ($actorId, $batchId, $reason, $ip): void {
                    $this->audit->record($actorId, 'admin.system_notice.retry_delivery', 'event_delivery', (string) $before['id'], [
                        'batch_id' => $batchId, 'reason' => $reason, 'before_attempts' => (int) $before['attempt_count'],
                        'authorized_attempt_limit' => (int) $after['retry_attempt_limit'], '_ip' => $ip,
                    ]);
                });
                if (!$changed) return;
                $this->batches->update($batchId, $changes);
            } else {
                $this->batches->update($batchId, $changes + ['dispatch_cursor' => 0, 'dispatch_complete' => 0]);
                $this->queueChunk($batchId, $actorId, $nextGeneration, 0);
            }
            $this->audit->record($actorId, 'admin.system_notice.retry', 'system_notice_batch', (string) $batchId, [
                'before_generation' => $generation, 'after_generation' => $nextGeneration, 'reason' => $reason, '_ip' => $ip,
            ]);
        });
        return $this->get($batchId, $actorId);
    }

    public function dispatchChunk(int $batchId, int $generation, int $afterUserId, DeliveryContext $context): void
    {
        $this->transactions->run(function () use ($batchId, $generation, $afterUserId, $context): void {
            $batch = $this->batches->find($batchId, null, true);
            if ($batch === null || empty($batch['confirmed_at'])) throw new \UnexpectedValueException('系统通知发布批次不存在或未确认');
            // 游标、当前事件引用与批次锁共同防止重放或租约重叠重复签发。
            if ((bool) $batch['dispatch_complete'] || (int) $batch['dispatch_event_id'] !== $context->eventId()
                || (int) $batch['dispatch_cursor'] !== $afterUserId) return;
            if ($generation > (int) $batch['dispatch_generation']) throw new \UnexpectedValueException('续批代次超出已授权范围');
            $rows = $this->batches->recipientsAfter($batchId, $afterUserId, SystemNoticeBatch::CHUNK_SIZE + 1);
            $more = count($rows) > SystemNoticeBatch::CHUNK_SIZE;
            $rows = array_slice($rows, 0, SystemNoticeBatch::CHUNK_SIZE);
            $existing = array_map('intval', array_column(array_filter($rows, static fn(array $row): bool => $row['projected_at'] === null && $row['delivery_id'] !== null), 'delivery_id'));
            $states = (int) $batch['dispatch_generation'] > 1 && $existing !== [] ? DeliveryProgress::statuses($existing) : [];
            $cursor = $afterUserId;
            foreach ($rows as $row) {
                $cursor = (int) $row['user_id'];
                if ($row['projected_at'] !== null) continue;
                if ($row['issued_event_id'] === null) {
                    $recorded = $this->issuer->issue($cursor, SystemNoticeBatch::noticeKey($batchId), SystemNotice::NOTICE,
                        $batch['message'], $batch['action_url'], $batch['severity']);
                    $deliveries = $recorded->deliveryIds();
                    if (count($deliveries) !== 1) throw new \LogicException('系统通知处理器登记不完整');
                    $this->batches->attachDispatch($batchId, $cursor, $recorded->eventId(), $deliveries[0]);
                } elseif (($states[(int) $row['delivery_id']]['status'] ?? null) === 'dead') {
                    DeliveryRetries::retry((int) $row['delivery_id'], function (array $before, array $after) use ($batch, $batchId): void {
                        $this->audit->record((int) $batch['creator_id'], 'admin.system_notice.retry_delivery', 'event_delivery', (string) $before['id'], [
                            'batch_id' => $batchId, 'generation' => (int) $batch['dispatch_generation'],
                            'reason' => (string) $batch['retry_reason'], 'before_attempts' => (int) $before['attempt_count'],
                            'authorized_attempt_limit' => (int) $after['retry_attempt_limit'],
                        ]);
                    });
                }
            }
            $this->batches->update($batchId, ['dispatch_cursor' => $cursor, 'dispatch_complete' => $more ? 0 : 1]);
            if ($more) $this->queueChunk($batchId, (int) $batch['creator_id'], (int) $batch['dispatch_generation'], $cursor);
        });
    }

    private function queueChunk(int $batchId, int $creatorId, int $generation, int $afterUserId): void
    {
        $recorded = $this->events->publish(SystemNoticeBatch::CHUNK_EVENT, 'system_notice_batch', (string) $batchId,
            ['batch_id' => $batchId, 'generation' => $generation, 'after_user_id' => $afterUserId], $creatorId);
        $deliveries = $recorded->deliveryIds();
        if (count($deliveries) !== 1) throw new \LogicException('系统通知续批处理器登记不完整');
        $this->batches->update($batchId, ['dispatch_event_id' => $recorded->eventId(), 'dispatch_delivery_id' => $deliveries[0]]);
    }

    private function receipt(array $batch): array
    {
        $id = (int) $batch['id'];
        $counts = $this->batches->counts($id);
        $pendingIds = $this->batches->pendingDeliveryIds($id);
        $progress = DeliveryProgress::summarize($pendingIds);
        $fanout = empty($batch['dispatch_delivery_id']) ? null : (DeliveryProgress::statuses([(int) $batch['dispatch_delivery_id']])[(int) $batch['dispatch_delivery_id']] ?? null);
        if (!empty($batch['confirmed_at']) && (((int) ($progress['unknown'] ?? 0) > 0 || (int) ($progress['completed'] ?? 0) > 0)
            || (!(bool) $batch['dispatch_complete'] && ($fanout === null || $fanout['status'] === 'completed')))) {
            throw ApiException::capabilityUnavailable('发送回执与处理任务状态不一致，请到事件管理核对。', 'notification-batch-progress-unavailable');
        }
        $fanoutDead = !(bool) $batch['dispatch_complete'] && ($fanout['status'] ?? null) === 'dead';
        $failed = (int) ($progress['dead'] ?? 0);
        $pending = max(0, (int) $batch['recipient_count'] - $counts['generated'] - $counts['skipped'] - $failed);
        $processing = (int) ($progress['processing'] ?? 0) > 0 || ($fanout['status'] ?? null) === 'processing';
        $status = SystemNoticeBatch::status($batch, $counts['generated'], $counts['skipped'], $failed, $pending, $fanoutDead, $processing);
        $selection = $batch['selected_recipients'];
        return [
            'id' => $id, 'request_key' => $batch['request_key'], 'generation' => (int) $batch['dispatch_generation'],
            'audience' => $batch['audience'], 'recipient_ids' => array_column($selection, 'id'), 'recipients' => $selection,
            'message' => $batch['message'], 'action_url' => $batch['action_url'], 'severity' => $batch['severity'], 'status' => $status,
            'recipient_count' => (int) $batch['recipient_count'], 'generated_count' => $counts['generated'], 'skipped_count' => $counts['skipped'],
            'pending_count' => $pending, 'failed_count' => $failed, 'created_at' => $batch['created_at'],
            'confirmed_at' => $batch['confirmed_at'], 'completed_at' => $status === 'completed' ? $batch['completed_at'] : null,
            'can_confirm' => $status === 'prepared',
            'can_retry' => !empty($batch['confirmed_at']) && ($fanoutDead || ((bool) $batch['dispatch_complete'] && $failed > 0)),
            'diagnostic_event_id' => $fanoutDead ? (int) $batch['dispatch_event_id'] : ($progress['first_dead_event_id'] ?? null),
        ];
    }

    private function sameRequest(array $existing, string $hash): array
    {
        if (!hash_equals($existing['request_hash'], $hash)) throw $this->conflict('相同发布请求标识已用于其他内容，请重新预览。');
        return $this->receipt($existing);
    }

    private function owned(int $batchId, int $actorId, bool $lock = false): array
    {
        if ($actorId < 1) throw ApiException::forbidden('操作者身份无效');
        return $this->batches->find($batchId, $actorId, $lock) ?? throw ApiException::notFound('发布批次不存在或不可访问');
    }

    private function assertPublisher(int $actorId, GrantSet $grants, string $audience): void
    {
        if ($actorId < 1 || !$grants->allows('notifications.publish', 'global')) throw ApiException::forbidden('没有发布系统通知的权限');
        if ($audience === 'all' && !$grants->allows('notifications.broadcast', 'global')) throw ApiException::forbidden('没有向全站用户发送系统通知的权限');
    }

    private function conflict(string $message): ApiException
    {
        return ApiException::badRequest($message, ApiException::CODE_CONFIG_CONFLICT);
    }
}
