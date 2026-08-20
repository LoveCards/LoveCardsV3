<?php

declare(strict_types=1);

namespace app\modules\Platform\Files\Contract;

interface FilePurgeRepository
{
    /** @param int[] $fileIds @return array<int,array<string,mixed>> keyed by file id */
    public function lockForRequest(array $fileIds): array;

    /** @param int[] $fileIds */
    public function markRequested(array $fileIds, \DateTimeImmutable $at): void;

    /** @return array<string,mixed>|null */
    public function claim(int $fileId, \DateTimeImmutable $at): ?array;

    public function markStorageDeleted(int $fileId, \DateTimeImmutable $at): void;

    public function markFailed(int $fileId, string $error, \DateTimeImmutable $at): void;

    /** @return array<string,mixed>|null */
    public function lockForFinalize(int $fileId): ?array;

    public function deleteRecord(int $fileId): void;
}
