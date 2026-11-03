<?php
declare(strict_types=1);

namespace app\modules\Platform\Files\Application;

use app\api\ApiException;
use app\modules\Platform\Files\Contract\FileReferenceRepository;
use app\shared\Transaction\TransactionManager;

final class ManageFileReferences
{
    public function __construct(
        private FileReferenceRepository $references,
        private TransactionManager $transactions
    ) {}

    /** @param int[] $fileIds */
    public function replace(
        string $resourceType,
        string $resourceId,
        string $purpose,
        array $fileIds,
        int $ownerId,
        bool $isPublic,
        bool $allowAll
    ): void {
        if (!preg_match('/^[a-z][a-z0-9_]{0,31}$/', $resourceType)) throw ApiException::badRequest('文件引用类型不合法');
        if ($resourceId === '' || strlen($resourceId) > 128) throw ApiException::badRequest('文件引用标识不合法');
        if (!preg_match('/^[a-z][a-z0-9_]{0,31}$/', $purpose)) throw ApiException::badRequest('文件引用用途不合法');
        try {
            $this->transactions->run(fn () => $this->references->replaceResource(
                $resourceType, $resourceId, $purpose, $fileIds, $ownerId, $isPublic, $allowAll
            ));
        } catch (\DomainException $exception) {
            throw ApiException::forbidden($exception->getMessage());
        }
    }

    /** @return int[] */
    public function fileIds(string $resourceType, string $resourceId, ?string $purpose = null): array
    {
        return $this->references->fileIdsForResource($resourceType, $resourceId, $purpose);
    }

    /**
     * @param string[] $resourceIds
     * @return array<string,int[]>
     */
    public function fileIdsForResources(string $resourceType, array $resourceIds, ?string $purpose = null): array
    {
        return $this->references->fileIdsForResources($resourceType, $resourceIds, $purpose);
    }
}
