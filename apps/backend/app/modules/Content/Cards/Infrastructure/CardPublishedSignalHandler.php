<?php

declare(strict_types=1);

namespace app\modules\Content\Cards\Infrastructure;

use app\modules\Content\Cards\Service\CardVisibilityPolicy;
use app\modules\Operations\Runtime\Public\RuntimeCommunication;
use app\modules\Platform\Eventing\Public\DeliveryContext;
use app\modules\Platform\Eventing\Public\DomainEventBus;
use app\modules\Platform\Eventing\Public\EventEnvelope;
use app\modules\Platform\Eventing\Public\EventHandler;
use app\modules\Platform\Eventing\Public\RealtimeSignal;
use app\modules\Platform\Eventing\Public\RealtimeSignalPublisher;
use app\modules\Platform\Eventing\Public\SignalPolicies;
use app\modules\Platform\Eventing\Public\SignalTransportUnavailable;
use think\facade\Db;

/** 可见卡片的无内容提示；关注者按固定大小的持久化批次投递。 */
final class CardPublishedSignalHandler implements EventHandler
{
    private const BATCH_SIZE = 200;
    private const CONTINUATION = 'content.card.feed.batch.requested';

    public function handle(EventEnvelope $event, DeliveryContext $context): void
    {
        $first = $event->eventType() === 'content.card.published';
        if ((!$first && $event->eventType() !== self::CONTINUATION) || $event->eventVersion() !== 1) {
            throw new \UnexpectedValueException('信息流信号事件类型不匹配');
        }
        $payload = $event->payload();
        $cardId = (int) ($payload['card_id'] ?? 0);
        $ownerId = (int) ($payload['owner_id'] ?? 0);
        $publishedVisibility = (string) ($payload['visibility'] ?? '');
        $afterId = $first ? 0 : (int) ($payload['after_follower_id'] ?? -1);
        $rootId = $first ? $context->eventId() : (int) ($payload['root_event_id'] ?? 0);
        if ($cardId < 1 || $ownerId < 1 || $afterId < 0 || $rootId < 1
            || !in_array($publishedVisibility, [CardVisibilityPolicy::PUBLIC, CardVisibilityPolicy::FOLLOWERS], true)) {
            throw new \UnexpectedValueException('信息流信号事件载荷无效');
        }
        if (!SignalPolicies::enabled('feed.published')) {
            $context->markSignalDecision('skipped');
            return;
        }
        $state = app(RuntimeCommunication::class)->signalDeliveryState();
        if ($state === 'skip') {
            $context->markSignalDecision('skipped');
            return;
        }
        if ($state !== 'deliver') throw new SignalTransportUnavailable('Realtime 信号链路不可用');

        $card = Db::table('cards')->where('id', $cardId)->where('user_id', $ownerId)->find();
        $owner = Db::table('users')->where('id', $ownerId)->where('status', 0)->whereNull('deleted_at')->find();
        if (!is_array($card) || !$owner || (int) ($card['status'] ?? -1) !== 0 || !empty($card['deleted_at'])) {
            $context->markSignalDecision('skipped');
            return;
        }
        $visibility = (string) ($card['visibility'] ?? CardVisibilityPolicy::PUBLIC);
        if ($visibility !== $publishedVisibility) {
            $context->markSignalDecision('skipped');
            return;
        }

        $publisher = app(RealtimeSignalPublisher::class);
        if ($first && $publishedVisibility === CardVisibilityPolicy::PUBLIC) {
            $publisher->publish(new RealtimeSignal('feed', 'all', 'public'));
        }
        if (!$this->profileAllowsFollowing($ownerId)) {
            $context->markSignalDecision('applied');
            return;
        }
        $followers = $this->eligibleFollowerIds($ownerId, $afterId);
        $batch = array_slice($followers, 0, self::BATCH_SIZE);
        if ($batch !== []) {
            $publisher->publish(new RealtimeSignal(
                'feed',
                array_map(static fn(int $followerId): string => 'user:' . $followerId, $batch),
                'following'
            ));
        }
        if (count($followers) > self::BATCH_SIZE) {
            $nextAfter = (int) end($batch);
            $this->recordContinuation($rootId, $cardId, $ownerId, $publishedVisibility, $nextAfter, $context->deliveryId());
        }
        $context->markSignalDecision('applied');
    }

    private function profileAllowsFollowing(int $ownerId): bool
    {
        $profile = Db::table('privacy_preferences')->where('user_id', $ownerId)->field('profile_visibility')->find();
        return (string) ($profile['profile_visibility'] ?? 'public') !== 'private';
    }

    /** @return int[] */
    private function eligibleFollowerIds(int $ownerId, int $afterId): array
    {
        $rows = Db::query(
            "SELECT f.follower_id FROM user_follows f
             JOIN users u ON u.id = f.follower_id AND u.status = 0 AND u.deleted_at IS NULL
             WHERE f.followee_id = ? AND f.follower_id > ?
               AND NOT EXISTS (
                 SELECT 1 FROM user_safety_relations b WHERE b.relation = 'block'
                   AND ((b.actor_id = f.follower_id AND b.subject_id = f.followee_id)
                     OR (b.actor_id = f.followee_id AND b.subject_id = f.follower_id))
               )
             ORDER BY f.follower_id ASC LIMIT 201",
            [$ownerId, $afterId]
        );
        return array_map(static fn(array $row): int => (int) $row['follower_id'], $rows);
    }

    /** 同一个父投递加锁，并按根事件与游标去重续批。 */
    private function recordContinuation(int $rootId, int $cardId, int $ownerId, string $visibility, int $afterId, int $deliveryId): void
    {
        $key = $rootId . ':' . $afterId;
        Db::transaction(static function () use ($key, $rootId, $cardId, $ownerId, $visibility, $afterId, $deliveryId): void {
            Db::table('event_deliveries')->where('id', $deliveryId)->lock(true)->find();
            $exists = Db::table('outbox_events')
                ->where('event_type', self::CONTINUATION)
                ->where('aggregate_key', $key)
                ->find();
            if ($exists) return;
            app(DomainEventBus::class)->publish(self::CONTINUATION, 'card-feed-batch', $key, [
                'card_id' => $cardId,
                'owner_id' => $ownerId,
                'visibility' => $visibility,
                'root_event_id' => $rootId,
                'after_follower_id' => $afterId,
            ], $ownerId);
        });
    }
}
