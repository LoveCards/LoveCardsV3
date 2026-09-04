<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/app/api/ApiException.php';
require $root . '/app/shared/Transaction/TransactionManager.php';
require $root . '/app/shared/Observability/AuditLogger.php';
require $root . '/app/modules/Platform/Files/Domain/FileConstants.php';
require $root . '/app/modules/Platform/Files/Contract/FilePurgePublisher.php';
require $root . '/app/modules/Platform/Files/Contract/FilePurgeRepository.php';
require $root . '/app/modules/Platform/Files/Contract/FilePurgeRequester.php';
require $root . '/app/modules/Platform/Files/Contract/FileReferenceRepository.php';
require $root . '/app/modules/Platform/Files/Contract/StorageDriver.php';
require $root . '/app/modules/Platform/Files/Application/RequestFilePurge.php';
require $root . '/app/modules/Platform/Files/Application/ProcessFilePurge.php';

use app\modules\Platform\Files\Application\ProcessFilePurge;
use app\modules\Platform\Files\Application\RequestFilePurge;
use app\modules\Platform\Files\Contract\FilePurgePublisher;
use app\modules\Platform\Files\Contract\FilePurgeRepository;
use app\modules\Platform\Files\Contract\FileReferenceRepository;
use app\modules\Platform\Files\Contract\StorageDriver;
use app\modules\Platform\Files\Domain\FileConstants;
use app\shared\Transaction\TransactionManager;
use app\shared\Observability\AuditLogger;

final class FakePurgeRepository implements FilePurgeRepository
{
    public array $rows = [
        1 => ['id' => 1, 'channel_slug' => 'local', 'driver_path' => 'a.jpg', 'purge_status' => null],
        2 => ['id' => 2, 'channel_slug' => 'local', 'driver_path' => 'b.jpg', 'purge_status' => null],
    ];
    public array $requested = [];
    public array $failed = [];
    public array $deleted = [];

    public function lockForRequest(array $fileIds): array { return array_intersect_key($this->rows, array_flip($fileIds)); }
    public function markRequested(array $fileIds, DateTimeImmutable $at): void
    {
        $this->requested[] = $fileIds;
        foreach ($fileIds as $id) $this->rows[$id]['purge_status'] = FileConstants::PURGE_PENDING;
    }
    public function claim(int $fileId, DateTimeImmutable $at): ?array
    {
        if (!isset($this->rows[$fileId])) return null;
        if ($this->rows[$fileId]['purge_status'] !== FileConstants::PURGE_STORAGE_DELETED) {
            $this->rows[$fileId]['purge_status'] = FileConstants::PURGE_PROCESSING;
        }
        return $this->rows[$fileId];
    }
    public function markStorageDeleted(int $fileId, DateTimeImmutable $at): void { $this->rows[$fileId]['purge_status'] = FileConstants::PURGE_STORAGE_DELETED; }
    public function markFailed(int $fileId, string $error, DateTimeImmutable $at): void { $this->rows[$fileId]['purge_status'] = FileConstants::PURGE_FAILED; $this->failed[$fileId] = $error; }
    public function lockForFinalize(int $fileId): ?array { return $this->rows[$fileId] ?? null; }
    public function deleteRecord(int $fileId): void { $this->deleted[] = $fileId; unset($this->rows[$fileId]); }
}

final class FakeReferences implements FileReferenceRepository
{
    public array $counts = [];
    public function forFile(int $fileId): array { return []; }
    public function fileIdsForResource(string $resourceType, string $resourceId, ?string $purpose = null): array { return []; }
    public function fileIdsForResources(string $resourceType, array $resourceIds, ?string $purpose = null): array { return []; }
    public function countForFile(int $fileId): int { return (int) ($this->counts[$fileId] ?? 0); }
    public function replaceResource(string $resourceType, string $resourceId, string $purpose, array $fileIds, int $ownerId, bool $isPublic, bool $allowAll): void {}
}

final class FakePurgePublisher implements FilePurgePublisher
{
    public array $events = [];
    public function requested(int $fileId, int $actorId): void { $this->events[] = [$fileId, $actorId]; }
}

final class FakeTransactions implements TransactionManager
{
    public int $calls = 0;
    public function run(callable $callback): mixed { $this->calls++; return $callback(); }
}

final class FakePurgeAudit implements AuditLogger
{
    public array $records = [];
    public function record(?int $actorId, string $action, string $resourceType, ?string $resourceId, array $metadata = []): void
    {
        $this->records[] = [$actorId, $action, $resourceType, $resourceId, $metadata];
    }
}

final class FakePurgeStorage implements StorageDriver
{
    public bool $result = true;
    public array $deleted = [];
    public function uploadToDefault(object $file, string $path): array { return []; }
    public function deleteFile(string $channelSlug, string $driverPath): bool { $this->deleted[] = [$channelSlug, $driverPath]; return $this->result; }
    public function getUrl(string $channelSlug, string $filePath): string { return ''; }
    public function getDirectUploadCredential(string $channelSlug, string $filename, string $mime, int $size, string $path, int $expire): array { return []; }
    public function supportsDirectUpload(string $channelSlug): bool { return false; }
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$files = new FakePurgeRepository();
$references = new FakeReferences();
$events = new FakePurgePublisher();
$transactions = new FakeTransactions();
$audit = new FakePurgeAudit();
$requester = new RequestFilePurge($files, $references, $events, $transactions, $audit);
$requester->requestMany([2, 1, 2], 9, '127.0.0.1');
$assert($files->requested === [[1, 2]], 'purge request must lock and update files in stable order');
$assert($events->events === [[1, 9], [2, 9]], 'each requested purge must publish an attributed Outbox event');
$assert(count($audit->records) === 2 && $audit->records[0][1] === 'files.purge.requested', 'purge request must be audited in the transaction');

$files = new FakePurgeRepository();
$references = new FakeReferences();
$references->counts[2] = 1;
$events = new FakePurgePublisher();
$rejected = false;
try { (new RequestFilePurge($files, $references, $events, $transactions, new FakePurgeAudit()))->requestMany([1, 2], 9); }
catch (\app\api\ApiException) { $rejected = true; }
$assert($rejected && $files->requested === [] && $events->events === [], 'referenced batch must fail before state or Outbox writes');

$files = new FakePurgeRepository();
$files->rows[1]['purge_status'] = FileConstants::PURGE_PENDING;
$references = new FakeReferences();
$storage = new FakePurgeStorage();
(new ProcessFilePurge($files, $references, $storage, $transactions))->execute(1);
$assert($storage->deleted === [['local', 'a.jpg']], 'processor must delete storage first');
$assert($files->deleted === [1], 'processor must remove DB record only after storage confirmation');

$files = new FakePurgeRepository();
$files->rows[1]['purge_status'] = FileConstants::PURGE_PENDING;
$storage = new FakePurgeStorage();
$storage->result = false;
$failed = false;
try { (new ProcessFilePurge($files, new FakeReferences(), $storage, $transactions))->execute(1); }
catch (RuntimeException) { $failed = true; }
$assert($failed && isset($files->failed[1]) && $files->deleted === [], 'storage failure must preserve the file ledger for retry');

$files = new FakePurgeRepository();
$files->rows[1]['purge_status'] = FileConstants::PURGE_PENDING;
$files->rows[1]['driver_path'] = '';
$files->rows[1]['file_path'] = '';
$storage = new FakePurgeStorage();
$failed = false;
try { (new ProcessFilePurge($files, new FakeReferences(), $storage, $transactions))->execute(1); }
catch (RuntimeException) { $failed = true; }
$assert($failed && $storage->deleted === [] && isset($files->failed[1]), 'missing storage location must fail closed before driver deletion');

$files = new FakePurgeRepository();
$files->rows[1]['purge_status'] = FileConstants::PURGE_STORAGE_DELETED;
$storage = new FakePurgeStorage();
(new ProcessFilePurge($files, new FakeReferences(), $storage, $transactions))->execute(1);
$assert($storage->deleted === [] && $files->deleted === [1], 'storage-deleted state must resume at DB finalization');

$referenceSource = file_get_contents($root . '/app/modules/Platform/Files/Infrastructure/ThinkDbFileReferenceRepository.php');
$assert(str_contains($referenceSource, "->whereNull('purge_status')") && str_contains($referenceSource, '->lock(true)'), 'reference replacement must lock purge-eligible files');

$migration = file_get_contents($root . '/database/migrations/20260819000002.sql');
foreach (['purge_status', 'purge_attempts', 'purge_requested_at', 'purge_storage_deleted_at', 'purge_error', 'idx_files_purge'] as $schemaPart) {
    $assert(str_contains($migration, $schemaPart), "files purge migration must include {$schemaPart}");
}

fwrite(STDOUT, "PASS file purge behavior baseline\n");
