<?php

declare(strict_types=1);

namespace app\modules\Platform\Files\Contract;

interface FilePurgePublisher
{
    public function requested(int $fileId, int $actorId): void;
}
