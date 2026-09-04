<?php
declare(strict_types=1);

namespace app\modules\Platform\Files\Contract;

interface FileReferenceRepository
{
    /** @return array<int,array<string,mixed>> */
    public function forFile(int $fileId): array;

    /** @return int[] */
    public function fileIdsForResource(string $resourceType, string $resourceId, ?string $purpose = null): array;

    /**
     * @param string[] $resourceIds
     * @return array<string,int[]>
     */
    public function fileIdsForResources(string $resourceType, array $resourceIds, ?string $purpose = null): array;

    public function countForFile(int $fileId): int;

    /** @param int[] $fileIds */
    public function replaceResource(
        string $resourceType,
        string $resourceId,
        string $purpose,
        array $fileIds,
        int $ownerId,
        bool $isPublic,
        bool $allowAll
    ): void;
}
