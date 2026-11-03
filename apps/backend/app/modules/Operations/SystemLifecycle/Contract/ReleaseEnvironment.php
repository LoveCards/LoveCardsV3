<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Contract;

interface ReleaseEnvironment
{
    public function phpVersion(): string;
    public function mysqlVersion(): string;
    public function hasExtension(string $extension): bool;
    public function handlerCatalogHash(): string;
}
