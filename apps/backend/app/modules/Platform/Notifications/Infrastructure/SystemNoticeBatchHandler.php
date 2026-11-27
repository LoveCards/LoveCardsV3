<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Infrastructure;

use app\modules\Platform\Eventing\Public\DeliveryContext;
use app\modules\Platform\Eventing\Public\EventEnvelope;
use app\modules\Platform\Eventing\Public\EventHandler;
use app\modules\Platform\Notifications\Application\ManageSystemNoticeBatches;
use app\modules\Platform\Notifications\Domain\SystemNoticeBatch;

/** 每次只签发一段收件快照，续批仍由既有消费者领取。 */
final class SystemNoticeBatchHandler implements EventHandler
{
    public function handle(EventEnvelope $event, DeliveryContext $context): void
    {
        $payload = $event->payload();
        $keys = array_keys($payload); sort($keys);
        if ($event->eventType() !== SystemNoticeBatch::CHUNK_EVENT || $event->eventVersion() !== 1
            || $event->aggregateType() !== 'system_notice_batch'
            || $keys !== ['after_user_id', 'batch_id', 'generation']
            || !is_int($payload['batch_id']) || $payload['batch_id'] < 1
            || !is_int($payload['generation']) || $payload['generation'] < 1
            || !is_int($payload['after_user_id']) || $payload['after_user_id'] < 0
            || $event->aggregateKey() !== (string) $payload['batch_id']) {
            throw new \UnexpectedValueException('系统通知续批事件无效');
        }
        app(ManageSystemNoticeBatches::class)->dispatchChunk($payload['batch_id'], $payload['generation'], $payload['after_user_id'], $context);
    }
}
