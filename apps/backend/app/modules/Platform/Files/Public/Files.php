<?php

namespace app\modules\Platform\Files\Public;

use app\modules\Platform\Files\Contract\FileRepository;
use app\modules\Platform\Files\Application\ManageFileReferences;
use app\modules\Platform\Files\Application\ResolvePublicMedia;

final class Files
{
    public static function repository(): FileRepository { return app(FileRepository::class); }
    public static function replaceReferences(string $type, string $id, string $purpose, array $fileIds, int $ownerId, bool $isPublic, bool $allowAll): void
    {
        app(ManageFileReferences::class)->replace($type, $id, $purpose, $fileIds, $ownerId, $isPublic, $allowAll);
    }
    public static function referenceFileIds(string $type, string $id, ?string $purpose = null): array { return app(ManageFileReferences::class)->fileIds($type, $id, $purpose); }
    public static function referenceFileIdsForResources(string $type, array $ids, ?string $purpose = null): array
    {
        return app(ManageFileReferences::class)->fileIdsForResources($type, $ids, $purpose);
    }
    public static function cardMediaFiles(array $fileIds, array $legacyCardIds, bool $publicOnly): array
    {
        return self::repository()->findUsableByIdsOrLegacyReferences(
            $fileIds,
            'card',
            'card',
            $legacyCardIds,
            $publicOnly
        );
    }
    public static function publicMedia(string $hash): ?array { return app(ResolvePublicMedia::class)->execute($hash); }
}
