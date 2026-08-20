<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Contract;

use app\modules\Operations\SystemLifecycle\Domain\StoredReleasePackage;

interface ReleasePackageStore
{
    public function store(string $planId, string $sourcePath): StoredReleasePackage;
    public function remove(string $planId): void;
}
