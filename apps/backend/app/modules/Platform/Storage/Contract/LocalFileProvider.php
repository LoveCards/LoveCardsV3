<?php
declare(strict_types=1);

namespace app\modules\Platform\Storage\Contract;

/** Optional capability implemented only by storage drivers backed by this host. */
interface LocalFileProvider
{
    public function resolveLocalFile(string $driverPath): ?string;
}
