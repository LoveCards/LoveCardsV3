<?php
declare(strict_types=1);

namespace app\modules\Platform\Files\Contract;

/** Optional storage capability for safely serving a host-local object. */
interface LocalStorageReader
{
    /** @return array{managed:bool,path:?string} */
    public function resolveLocalFile(string $channelSlug, string $driverPath): array;
}
