<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Infrastructure;

use app\modules\Operations\SystemLifecycle\Contract\ReleaseRuntime;

final class NullReleaseRuntime implements ReleaseRuntime
{
    public function canDrain(): bool { return false; }
    public function drain(string $releaseId): void { throw new \LogicException('当前运行时不支持 drain'); }
    public function canReload(): bool { return false; }
    public function reload(string $releaseId): void { throw new \LogicException('当前运行时不支持 reload'); }
    public function healthCheck(string $releaseId): void {}
}
