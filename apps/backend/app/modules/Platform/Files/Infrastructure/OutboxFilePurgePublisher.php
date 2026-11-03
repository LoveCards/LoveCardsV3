<?php

declare(strict_types=1);

namespace app\modules\Platform\Files\Infrastructure;

use app\modules\Platform\Eventing\Public\DomainEventBus;
use app\modules\Platform\Files\Contract\FilePurgePublisher;

final class OutboxFilePurgePublisher implements FilePurgePublisher
{
    public function __construct(private DomainEventBus $events)
    {
    }

    public function requested(int $fileId, int $actorId): void
    {
        $this->events->publish(
            'file.purge.requested',
            'file',
            (string) $fileId,
            ['file_id' => $fileId, 'requested_by' => $actorId],
            $actorId
        );
    }
}
