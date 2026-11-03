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
    public static function publicMedia(string $hash): ?array
    {
        $file = self::repository()->findByHash(strtolower(trim($hash)), 0, false);
        if ($file === null) return null;
        // is_public 是引用的派生值；读取时仍核对卡片现状，避免历史脏标记绕过审核或可见范围。
        $references = app(\app\modules\Platform\Files\Contract\FileReferenceRepository::class)->forFile((int) $file['id']);
        if ($references === [] && ($file['ref_type'] ?? '') === 'card') {
            $references = [['resource_type' => 'card', 'resource_id' => $file['ref_id'] ?? 0, 'is_public' => 1]];
        }
        if ($references !== []) {
            $allowed = false;
            $cardIds = [];
            foreach ($references as $reference) {
                if (!(bool) $reference['is_public']) continue;
                if ($reference['resource_type'] === 'card') $cardIds[] = (int) $reference['resource_id'];
                else $allowed = true;
            }
            if (!$allowed && \app\modules\Content\Cards\Public\CardAccess::readableIds($cardIds, 0) === []) return null;
        }
        return app(ResolvePublicMedia::class)->execute($hash);
    }
    public static function authorizedLocalMedia(int $fileId): ?array { return app(ResolvePublicMedia::class)->authorizedLocal($fileId); }
}
