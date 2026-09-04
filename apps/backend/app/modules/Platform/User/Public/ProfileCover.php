<?php
declare(strict_types=1);

namespace app\modules\Platform\User\Public;

use app\api\ApiException;
use app\modules\Platform\Files\Domain\FileConstants;
use app\modules\Platform\Files\Public\Files;

final class ProfileCover
{
    private const RESOURCE_TYPE = 'user';
    private const PURPOSE = 'profile_cover';
    private const MAX_BYTES = 10485760;
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    public static function url(int $userId, bool $publicOnly = false): ?string
    {
        $fileIds = Files::referenceFileIds(self::RESOURCE_TYPE, (string) $userId, self::PURPOSE);
        if ($fileIds === []) return null;

        $file = Files::repository()->findById((int) $fileIds[0]);
        if (!self::isUsable($file, $userId, $publicOnly)) return null;

        $hash = strtolower(trim((string) ($file['hash'] ?? '')));
        return preg_match('/^[a-f0-9]{16,64}$/', $hash) === 1 ? '/media/' . $hash : null;
    }

    public static function replace(int $userId, ?int $fileId): void
    {
        if ($fileId === null) {
            Files::replaceReferences(self::RESOURCE_TYPE, (string) $userId, self::PURPOSE, [], $userId, true, false);
            return;
        }

        $file = Files::repository()->findById($fileId);
        if (!self::isUsable($file, $userId, false)) {
            throw ApiException::validation(['cover_file_id' => ['封面图片不可用。']]);
        }

        Files::replaceReferences(self::RESOURCE_TYPE, (string) $userId, self::PURPOSE, [$fileId], $userId, true, false);
    }

    private static function isUsable(?array $file, int $userId, bool $publicOnly): bool
    {
        if ($file === null
            || (int) ($file['user_id'] ?? 0) !== $userId
            || (string) ($file['scene'] ?? '') !== FileConstants::SCENE_PROFILE_COVER
            || (int) ($file['status'] ?? -1) !== FileConstants::STATUS_NORMAL
            || (int) ($file['upload_status'] ?? -1) !== FileConstants::UPLOAD_COMPLETED
            || !empty($file['deleted_at'])
            || !empty($file['purge_status'])
            || (int) ($file['file_size'] ?? 0) > self::MAX_BYTES
            || !in_array(strtolower((string) ($file['mime_type'] ?? '')), self::ALLOWED_MIME_TYPES, true)) {
            return false;
        }

        return !$publicOnly || (int) ($file['is_public'] ?? 0) === 1;
    }
}
