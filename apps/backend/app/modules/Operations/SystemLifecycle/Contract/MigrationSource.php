<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Contract;

interface MigrationSource
{
    /** @return Migration[] */
    public function discover(string $directory): array;
}
