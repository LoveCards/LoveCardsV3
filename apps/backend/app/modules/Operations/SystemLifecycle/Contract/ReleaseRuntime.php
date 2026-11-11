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
    /** 必须停止所有消费入口并确认在途结束；无可靠停止证据时失败关闭。 */
    public function assertQuiescent(string $releaseId): void;
}
