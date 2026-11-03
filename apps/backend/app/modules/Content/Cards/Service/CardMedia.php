<?php
declare(strict_types=1);

namespace app\modules\Content\Cards\Service;

use app\api\ApiException;
use app\modules\Platform\Files\Public\Files;
use app\modules\Platform\Auth\Public\MediaGrants;

final class CardMedia
{
    /** 受限媒体不能依赖永久公开的对象存储地址。校验在卡片写事务提交前执行。 */
    public static function assertRestrictedStorage(int $cardId): void
    {
        foreach (Files::referenceFileIds('card', (string) $cardId) as $fileId) {
            if (Files::authorizedLocalMedia($fileId) === null) {
                throw ApiException::validation(['media_file_ids' => ['当前媒体存储不支持受限访问，请更换为本地受保护存储中的文件。']]);
            }
        }
    }

    public static function access(int $cardId, int $viewerId, string $sessionId): array
    {
        CardVisibilityPolicy::assertReadable($cardId, $viewerId);
        $token = app(MediaGrants::class)->issue($viewerId, $sessionId, $cardId);
        $urls = [];
        foreach (Files::referenceFileIds('card', (string) $cardId) as $fileId) {
            if (Files::authorizedLocalMedia($fileId) === null) continue;
            $urls[(int) $fileId] = '/media/card/' . $cardId . '/' . $fileId . '?grant=' . $token;
        }
        $coverIds = Files::referenceFileIds('card', (string) $cardId, 'cover');
        return ['media' => array_map(static fn(int $id): array => ['file_id' => $id, 'url' => $urls[$id]], array_keys($urls)), 'cover_url' => $urls[$coverIds[0] ?? 0] ?? null, 'expires_in' => MediaGrants::TTL];
    }

    public static function read(int $cardId, int $fileId, string $grant): array
    {
        $viewerId = app(MediaGrants::class)->viewer($grant, $cardId);
        CardVisibilityPolicy::assertReadable($cardId, $viewerId);
        if (!in_array($fileId, Files::referenceFileIds('card', (string) $cardId), true)) throw ApiException::notFound('媒体不存在');
        $media = Files::authorizedLocalMedia($fileId);
        if ($media === null) throw ApiException::notFound('媒体不存在');
        return $media;
    }
}
