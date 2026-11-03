<?php

declare(strict_types=1);

namespace app\modules\Platform\Files\Application;

use app\api\ApiException;
use app\modules\Platform\Files\Contract\FilePurgePublisher;
use app\modules\Platform\Files\Contract\FilePurgeRepository;
use app\modules\Platform\Files\Contract\FilePurgeRequester;
use app\modules\Platform\Files\Contract\FileReferenceRepository;
use app\modules\Platform\Files\Domain\FileConstants;
use app\shared\Transaction\TransactionManager;
use app\shared\Observability\AuditLogger;

/** 在业务事务内冻结文件并记录可靠 purge 事件。 */
final class RequestFilePurge implements FilePurgeRequester
{
    public function __construct(
        private FilePurgeRepository $files,
        private FileReferenceRepository $references,
        private FilePurgePublisher $events,
        private TransactionManager $transactions,
        private AuditLogger $audit
    ) {
    }

    public function requestMany(array $fileIds, int $actorId, string $ipAddress = ''): void
    {
        if ($actorId < 1) throw ApiException::unauthorized('请先登入');
        $fileIds = array_values(array_unique(array_filter(
            array_map('intval', $fileIds),
            static fn (int $id): bool => $id > 0
        )));
        sort($fileIds, SORT_NUMERIC);
        if ($fileIds === []) return;

        $this->transactions->run(function () use ($fileIds, $actorId, $ipAddress): void {
            $rows = $this->files->lockForRequest($fileIds);
            $missing = array_diff($fileIds, array_keys($rows));
            if ($missing !== []) throw ApiException::notFound('文件不存在: ' . implode(', ', $missing));

            $pending = [];
            foreach ($fileIds as $fileId) {
                $state = (string) ($rows[$fileId]['purge_status'] ?? FileConstants::PURGE_NONE);
                if (in_array($state, [FileConstants::PURGE_PENDING, FileConstants::PURGE_PROCESSING, FileConstants::PURGE_STORAGE_DELETED, FileConstants::PURGE_FAILED], true)) {
                    continue;
                }
                if ($this->references->countForFile($fileId) > 0) {
                    throw ApiException::badRequest('文件仍被业务资源引用，不能永久删除');
                }
                $pending[] = $fileId;
            }

            if ($pending === []) return;
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            $this->files->markRequested($pending, $now);
            $after = $this->files->lockForRequest($pending);
            foreach ($pending as $fileId) {
                $this->events->requested($fileId, $actorId);
                $this->audit->record($actorId, 'files.purge.requested', 'file', (string) $fileId, [
                    'before' => $rows[$fileId] ?? null,
                    'after' => $after[$fileId] ?? null,
                    '_ip' => $ipAddress,
                ]);
            }
        });
    }
}
