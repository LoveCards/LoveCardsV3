<?php

declare(strict_types=1);

namespace app\modules\Platform\Files\Contract;

interface FilePurgeRequester
{
    /** @param int[] $fileIds */
    public function requestMany(array $fileIds, int $actorId, string $ipAddress = ''): void;
}
