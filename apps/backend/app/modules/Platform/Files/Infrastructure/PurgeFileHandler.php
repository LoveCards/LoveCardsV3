<?php

declare(strict_types=1);

namespace app\modules\Platform\Files\Infrastructure;

use app\modules\Platform\Eventing\Public\DeliveryContext;
use app\modules\Platform\Eventing\Public\EventEnvelope;
use app\modules\Platform\Eventing\Public\EventHandler;
use app\modules\Platform\Files\Application\ProcessFilePurge;

final class PurgeFileHandler implements EventHandler
{
    public function handle(EventEnvelope $event, DeliveryContext $context): void
    {
        $fileId = (int) ($event->payload()['file_id'] ?? 0);
        if ($fileId < 1 || $event->aggregateKey() !== (string) $fileId) {
            throw new \UnexpectedValueException('file.purge.requested payload 不合法');
        }
        app(ProcessFilePurge::class)->execute($fileId);
    }
}
