<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Contract;

interface ReleaseRuntime
{
    public function canDrain(): bool;
    public function drain(string $releaseId): void;
    public function canReload(): bool;
    public function reload(string $releaseId): void;
    public function healthCheck(string $releaseId): void;
}
