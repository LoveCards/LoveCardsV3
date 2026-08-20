<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Contract;

use app\modules\Operations\SystemLifecycle\Domain\BackupArtifact;

interface BackupArtifactStore
{
    /** @return array<string, array{backup:bool,restore:bool,mode:string,reason:?string}> */
    public function capabilities(): array;
    public function database(string $backupId): BackupArtifact;
    public function files(string $backupId): BackupArtifact;
    public function pointer(string $backupId): BackupArtifact;
    public function verify(BackupArtifact $artifact): void;
    public function restoreFiles(BackupArtifact $artifact): void;
}
