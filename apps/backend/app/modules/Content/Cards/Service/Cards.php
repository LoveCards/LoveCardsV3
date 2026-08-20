<?php

namespace app\modules\Content\Cards\Service;

use app\modules\Platform\Config\Public\Config as ConfigService;

use think\facade\Db;

use app\modules\Content\Infrastructure\Persistence\Model\Cards as CardsModel;
use app\modules\Content\Infrastructure\Persistence\Model\TagsMap as TagsMapModel;
use app\modules\Content\Infrastructure\Persistence\Model\Comments as CommentsModel;
use app\modules\Platform\Files\Infrastructure\Persistence\Model\Files as FilesModel;
use app\modules\Platform\Files\Public\Files as PublicFiles;

use app\shared\Infrastructure\Persistence\BulkFieldToggle;
use app\shared\Infrastructure\Persistence\ModelQuery;
use app\modules\Content\Cards\Domain\PublicationStatus;
use app\modules\Content\Cards\Service\Policy\CardPolicy;
use app\shared\Authorization\GrantSet;

class Cards
{
    public static function count(): int
    {
        return (int) CardsModel::count();
    }
    public static function requiresApproval(): bool
    {
        return (bool) ConfigService::get('cards.approve');
    }
    const TOP_LISTS_MAX = 32;
    const HOT_LISTS_MAX = 8;

    public static function hotList(): array
    {
        $top = CardsModel::where('status', PublicationStatus::visible())
            ->where('is_top', 1)
            ->order('id', 'desc')
            ->limit(self::TOP_LISTS_MAX)
            ->select()
            ->toArray();

        $hot = CardsModel::fieldRaw('*, comments * 0.3 + goods * 0.7 as hot_score')
            ->where('status', PublicationStatus::visible())
            ->where('is_top', 0)
            ->order('hot_score', 'desc')
            ->limit(self::HOT_LISTS_MAX)
            ->select()
            ->toArray();

        return self::hydrateCards(array_merge($top, $hot), true);
    }

    /**
     * @param array $params
     * @param array $caps 用户能力列表（公开路由传空数组）
     */
    public static function list(array $params, array $caps = []): array
    {
        $params['search_default_key'] = 'content';

        if (isset($params['tag_id'])) {
            $tagId = (int) $params['tag_id'];
            $params['where'][] = ['id', 'in', TagsMapModel::where('aid', 1)->where('tag_id', $tagId)->column('pid') ?: [0]];
        }

        // 无 cards.read.all 能力 → 只看已发布
        if (!in_array('cards.read.all', $caps)) {
            $params['where']['status'] = PublicationStatus::visible();
        }

        $result = ModelQuery::make(CardsModel::class)->getPaginate($params);
        return self::hydratePaginated($result->toArray(), !in_array('cards.read.all', $caps, true));
    }

    /** 管理端列表：默认 active，可按权限查看回收站。 */
    public static function listAll(array $params, array $caps = []): array
    {
        $params['search_default_key'] = 'content';
        $canReadAll = in_array('cards.read.all', $caps, true);
        $query = ModelQuery::make(CardsModel::class)
            ->trashScope((int) ($params['show_deleted'] ?? 0), $canReadAll);

        if (!$canReadAll) {
            $params['where']['status'] = PublicationStatus::visible();
        }

        return self::hydratePaginated($query->getPaginate($params)->toArray(), !$canReadAll);
    }

    /**
     * @param int   $id
     * @param array $caps
     */
    public static function get(int $id, array $caps = []): array
    {
        $result = CardsModel::where('id', $id)->findOrEmpty();
        if ($result->isEmpty()) {
            throw \app\api\ApiException::notFound('卡片不存在');
        }

        // 无 cards.read.all 能力 → 只看已发布
        if (!in_array('cards.read.all', $caps) && $result->status !== PublicationStatus::visible()) {
            throw \app\api\ApiException::notFound('卡片不存在');
        }

        return self::hydrateCard(self::decodeJsonFields($result->toArray()), !in_array('cards.read.all', $caps, true));
    }

    public static function listOwn(array $params, int $uid): array
    {
        $params['search_default_key'] = 'content';
        $params['where'] = [
            'user_id' => $uid
        ];
        $result = ModelQuery::make(CardsModel::class)->getPaginate($params);
        return self::hydratePaginated($result->toArray());
    }

    public static function getOwn(int $id, int $uid): array
    {
        $result = CardsModel::where('id', $id)->where('user_id', $uid)->findOrEmpty();
        if ($result->isEmpty()) {
            throw \app\api\ApiException::notFound('卡片不存在');
        }

        return self::hydrateCard(self::decodeJsonFields($result->toArray()));
    }

    /**
     * 批量操作（能力在 Service 层检查）
     *
     * @param string $method
     * @param array  $ids
     * @param int    $uid
     * @param array  $caps
     */
    public static function batchOperate(string $method, array $ids, int $uid, GrantSet $grants): void
    {
        if (empty($ids)) {
            throw \app\api\ApiException::badRequest('未指定要操作的资源');
        }

        $cap = PublicationStatus::batchCapability($method);
        if (in_array($method, ['trash', 'restore', 'hard_delete'], true)) {
            $cap = 'cards.delete';
        }
        if (!$cap) {
            throw \app\api\ApiException::badRequest('不支持的操作');
        }

        // 能力 + 归属一体化检查
        CardPolicy::assertCanBatch(
            $grants,
            substr($cap, strlen('cards.')),
            $ids,
            $uid,
            in_array($method, ['restore', 'hard_delete'], true)
        );

        Db::startTrans();
        try {
            match ($method) {
                'top', 'unset_top' => BulkFieldToggle::toggle(CardsModel::class, 'is_top', $ids, [0, 1]),
                'approve', 'ban', 'hide' => BulkFieldToggle::toggle(
                    CardsModel::class,
                    'status',
                    $ids,
                    PublicationStatus::toggleRange($method)[0],
                    PublicationStatus::toggleRange($method)[1]
                ),
                'unhide' => CardsModel::whereIn('id', $ids)
                    ->where('status', PublicationStatus::HIDDEN)
                    ->update(['status' => PublicationStatus::NORMAL]),
                'delete', 'trash' => self::trashCards($ids),
                'restore' => self::restoreCards($ids),
                'hard_delete' => self::hardDeleteCards($ids),
            };
            self::syncMediaVisibility($ids, $method === 'hard_delete');
            Db::commit();
        } catch (\Throwable $th) {
            Db::rollback();
            if ($th instanceof \app\api\ApiException) throw $th;
            throw \app\api\ApiException::error('批量操作失败', \app\api\ApiException::CODE_SYSTEM_ERROR, null, $th);
        }
    }

    public static function createCard($data): string
    {
        Db::startTrans();
        try {
            $pictureIds = self::mediaIds($data['media_file_ids'] ?? $data['picture_file_ids'] ?? null);
            $uid = (int) ($data['user_id'] ?? 0);
            $data = self::encodeJsonFields(self::normalizeMedia($data, $uid, false));
            $result = CardsModel::create($data);
            $data['id'] = $result->id;
            self::bindMedia($pictureIds, $uid, (int) $result->id, (int) ($data['status'] ?? 0) === PublicationStatus::visible(), false);
            if (isset($data['tags'])) {
                self::updateCardTags($data, true);
            }
            Db::commit();
            return $result->id;
        } catch (\Throwable $th) {
            Db::rollback();
            if ($th instanceof \app\api\ApiException) throw $th;
            throw \app\api\ApiException::error('创建失败', \app\api\ApiException::CODE_SYSTEM_ERROR, null, $th);
        }
    }

    /**
     * 更新卡片
     *
     * @param array $data 卡片数据
     * @param int   $uid  当前用户 ID
     * @param array $caps 用户能力列表
     */
    public static function updateCard(array $data, int $uid, GrantSet $grants): void
    {
        Db::startTrans();
        try {
            // 验证能力 + 归属
            CardPolicy::assertCan($grants, 'update', (int) $data['id'], $uid);

            // 移除敏感字段
            $hasMediaIds = array_key_exists('media_file_ids', $data) || array_key_exists('picture_file_ids', $data);
            $pictureIds = $hasMediaIds
                ? self::mediaIds($data['media_file_ids'] ?? $data['picture_file_ids'])
                : null;
            unset($data['user_id']);
            $data = self::encodeJsonFields(
                self::normalizeMedia($data, $uid, $grants->allows('cards.update', \app\shared\Authorization\Scope::ALL))
            );

            if (isset($data['tags'])) {
                self::updateCardTags($data);
            }

            CardsModel::update($data);
            if ($pictureIds !== null) {
                $effectiveStatus = array_key_exists('status', $data)
                    ? (int) $data['status']
                    : (int) CardsModel::where('id', (int) $data['id'])->value('status');
                self::bindMedia(
                    $pictureIds,
                    $uid,
                    (int) $data['id'],
                    $effectiveStatus === PublicationStatus::visible(),
                    $grants->allows('cards.update', \app\shared\Authorization\Scope::ALL)
                );
            } elseif (array_key_exists('status', $data)) {
                self::syncMediaVisibility([(int) $data['id']]);
            }

            Db::commit();
        } catch (\Throwable $th) {
            Db::rollback();
            if ($th instanceof \app\api\ApiException) {
                throw $th;
            }
            throw \app\api\ApiException::error('更新失败', \app\api\ApiException::CODE_SYSTEM_ERROR, null, $th);
        }
    }

    /**
     * 更新标签关联
     *
     * @param array $data 包含 id 和 tags 的数据
     * @param bool  $create 是否为创建模式（不删除旧关联）
     */
    public static function updateCardTags(array $data, bool $create = false): void
    {
        $pid = (int) $data['id'];
        $tags = is_string($data['tags']) ? json_decode($data['tags'], true) : $data['tags'];

        if (!is_array($tags)) {
            throw \app\api\ApiException::badRequest('标签数据格式错误');
        }

        if (!$create) {
            TagsMapModel::where('aid', 1)->where('pid', $pid)->delete();
        }

        foreach ($tags as $tag_id) {
            $item = [
                'aid' => 1,
                'pid' => $pid,
                'tag_id' => $tag_id,
            ];
            TagsMapModel::create($item);
        }
    }

    /**
     * 删除卡片
     *
     * @param array $ids 资源 ID 列表
     * @param int   $uid 当前用户 ID
     * @param array $caps 用户能力列表
     */
    public static function deleteCards(array $ids, int $uid, GrantSet $grants): void
    {
        if (empty($ids)) {
            throw \app\api\ApiException::badRequest('未指定要删除的卡片');
        }

        Db::startTrans();
        try {
            // 验证能力 + 归属
            CardPolicy::assertCanBatch($grants, 'delete', $ids, $uid);

            CardsModel::destroy($ids);
            self::syncMediaVisibility($ids);

            Db::commit();
        } catch (\Throwable $th) {
            Db::rollback();
            if ($th instanceof \app\api\ApiException) {
                throw $th;
            }
            throw \app\api\ApiException::error('删除失败', \app\api\ApiException::CODE_SYSTEM_ERROR, null, $th);
        }
    }

    public static function deleteCardsTags($pids): void
    {
        TagsMapModel::where('aid', 1)->where('pid', 'in', $pids)->delete();
    }

    public static function deleteCardsComments($pids): void
    {
        CommentsModel::where('aid', 1)->where('pid', 'in', $pids)->delete();
    }

    /**
     * 删除卡片及其关联数据
     */
    private static function deleteCardsWithRelated(array $ids): void
    {
        Db::startTrans();
        try {
            self::trashCards($ids);
            Db::commit();
        } catch (\Throwable $th) {
            Db::rollback();
            throw \app\api\ApiException::error('删除失败', \app\api\ApiException::CODE_SYSTEM_ERROR, null, $th);
        }
    }

    private static function trashCards(array $ids): void
    {
        CardsModel::whereIn('id', $ids)->delete();
    }

    private static function restoreCards(array $ids): void
    {
        CardsModel::withTrashed()->onlyTrashed()->whereIn('id', $ids)->update(['deleted_at' => null]);
    }

    private static function hardDeleteCards(array $ids): void
    {
        Db::table('tags_map')->where('aid', 1)->whereIn('pid', $ids)->delete();
        Db::table('comments')->where('aid', 1)->whereIn('pid', $ids)->delete();
        Db::table('cards')->whereIn('id', $ids)->delete();
    }

    private static function decodeJsonFields(array $card): array
    {
        foreach (['data', 'tags', 'pictures'] as $field) {
            if (isset($card[$field]) && is_string($card[$field])) {
                $decoded = json_decode($card[$field], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $card[$field] = $decoded;
                }
            }
        }
        return $card;
    }

    private static function encodeJsonFields(array $data): array
    {
        foreach (['data', 'tags', 'pictures'] as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field]);
            }
        }

        return $data;
    }

    private static function hydratePaginated(array $result, bool $publicOnly = false): array
    {
        if (isset($result['data']) && is_array($result['data'])) {
            $result['data'] = self::hydrateCards($result['data'], $publicOnly);
        }

        return $result;
    }

    // Older cards may have bound files even though their pictures JSON was never persisted.
    private static function hydrateCards(array $cards, bool $publicOnly = false): array
    {
        $decoded = array_map(
            static fn($card) => is_array($card) ? self::decodeJsonFields($card) : $card,
            $cards
        );
        $cardIds = array_values(array_unique(array_filter(array_map(
            static fn($card): int => is_array($card) ? (int) ($card['id'] ?? 0) : 0,
            $decoded
        ), static fn(int $id): bool => $id > 0)));
        $mediaFiles = self::mediaFilesForCards($cardIds, $publicOnly);

        return array_map(static function ($card) use ($publicOnly, $mediaFiles) {
            if (!is_array($card)) return $card;
            $cardId = (int) ($card['id'] ?? 0);
            return self::hydrateCard($card, $publicOnly, $mediaFiles[$cardId] ?? []);
        }, $decoded);
    }

    private static function hydrateCard(array $card, bool $publicOnly = false, ?array $preloadedFiles = null): array
    {
        $pictures = $card['pictures'] ?? [];
        if (is_string($pictures)) {
            $decoded = json_decode($pictures, true);
            $pictures = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }
        $pictures = is_array($pictures)
            ? array_values(array_filter(array_map('strval', $pictures)))
            : [];
        $cardId = (int) ($card['id'] ?? 0);
        if ($cardId > 0) {
            $files = $preloadedFiles ?? (self::mediaFilesForCards([$cardId], $publicOnly)[$cardId] ?? []);
            $media = [];
            foreach ($files as $position => $file) {
                $item = self::mediaResource($file, $position + 1, $publicOnly);
                if ($item !== null) $media[] = $item;
            }
            if ($media !== []) {
                $card['media'] = $media;
                $card['pictures'] = array_column($media, 'url');
                return $card;
            }
        }

        $card['pictures'] = $pictures;
        $card['media'] = array_map(static fn(string $url, int $position): array => [
            'file_id' => null,
            'type' => preg_match('/\.(?:m4v|mov|mp4|ogv|webm)(?:$|[?#])/i', $url) ? 'video' : 'image',
            'url' => $url,
            'mime_type' => null,
            'size' => null,
            'width' => null,
            'height' => null,
            'duration_ms' => null,
            'poster_url' => null,
            'is_animated' => (bool) preg_match('/\.gif(?:$|[?#])/i', $url),
            'position' => $position + 1,
        ], $pictures, array_keys($pictures));

        return $card;
    }

    /**
     * Load references and files once per card page. Reference order remains the media order;
     * legacy files.ref_type/ref_id bindings are used only when a card has no ledger rows.
     *
     * @param int[] $cardIds
     * @return array<int,array<int,array<string,mixed>>>
     */
    private static function mediaFilesForCards(array $cardIds, bool $publicOnly): array
    {
        $cardIds = array_values(array_unique(array_filter(array_map('intval', $cardIds), static fn(int $id): bool => $id > 0)));
        if ($cardIds === []) return [];

        $filesByCard = array_fill_keys($cardIds, []);
        $referenceIdsByCard = PublicFiles::referenceFileIdsForResources(
            'card',
            array_map('strval', $cardIds)
        );
        foreach ($cardIds as $cardId) {
            $referenceIdsByCard[$cardId] = array_values(array_filter(
                array_map('intval', $referenceIdsByCard[(string) $cardId] ?? []),
                static fn(int $fileId): bool => $fileId > 0
            ));
        }

        $referenceFileIds = array_values(array_unique(array_merge(...array_values($referenceIdsByCard))));
        $legacyCardIds = array_values(array_filter(
            $cardIds,
            static fn(int $cardId): bool => $referenceIdsByCard[$cardId] === []
        ));
        $files = PublicFiles::cardMediaFiles($referenceFileIds, $legacyCardIds, $publicOnly);

        $filesById = [];
        $legacyFilesByCard = array_fill_keys($legacyCardIds, []);
        foreach ($files as $file) {
            $fileId = (int) ($file['id'] ?? 0);
            if ($fileId > 0) $filesById[$fileId] = $file;
            $legacyCardId = (int) ($file['ref_id'] ?? 0);
            if (($file['ref_type'] ?? null) === 'card' && array_key_exists($legacyCardId, $legacyFilesByCard)) {
                $legacyFilesByCard[$legacyCardId][] = $file;
            }
        }
        foreach ($cardIds as $cardId) {
            if ($referenceIdsByCard[$cardId] !== []) {
                $filesByCard[$cardId] = array_values(array_filter(array_map(
                    static fn(int $fileId): ?array => $filesById[$fileId] ?? null,
                    $referenceIdsByCard[$cardId]
                )));
            } else {
                $filesByCard[$cardId] = $legacyFilesByCard[$cardId] ?? [];
            }
        }

        return $filesByCard;
    }

    private static function normalizeMedia(array $data, int $uid, bool $allowAll): array
    {
        $hasMediaIds = array_key_exists('media_file_ids', $data);
        $hasPictureIds = array_key_exists('picture_file_ids', $data);
        if ($hasMediaIds && $hasPictureIds
            && self::mediaIds($data['media_file_ids']) !== self::mediaIds($data['picture_file_ids'])) {
            throw \app\api\ApiException::badRequest('media_file_ids 与 picture_file_ids 不能冲突');
        }
        if ($hasMediaIds || $hasPictureIds) {
            $ids = self::mediaIds($data['media_file_ids'] ?? $data['picture_file_ids']);
            $query = FilesModel::whereIn('id', $ids)
                ->where('scene', FilesModel::SCENE_CARD)
                ->where('status', FilesModel::STATUS_NORMAL)
                ->where('upload_status', FilesModel::UPLOAD_COMPLETED)
                ->whereNull('purge_status')
                ->order('id', 'asc')
                ->lock(true);
            if (!$allowAll) $query->where('user_id', $uid);
            $files = $ids === [] ? [] : $query->select()->toArray();
            if (count($files) !== count($ids)) throw \app\api\ApiException::forbidden('无权使用未完成、不可用或非卡片场景的文件');
            $byId = [];
            foreach ($files as $file) {
                $mime = strtolower(trim((string) ($file['mime_type'] ?? '')));
                if (!str_starts_with($mime, 'image/') && !str_starts_with($mime, 'video/')) {
                    throw \app\api\ApiException::badRequest('卡片媒体只允许图片或视频');
                }
                $url = trim((string) ($file['file_url'] ?? ''));
                if ($url === '') throw \app\api\ApiException::badRequest('卡片媒体尚未生成可访问地址');
                $byId[(int) $file['id']] = $url;
            }
            $data['pictures'] = array_values(array_filter(array_map(static fn($id) => $byId[$id] ?? '', $ids)));
        }
        unset($data['media_file_ids'], $data['picture_file_ids']);
        if (array_key_exists('cover_file_id', $data)) {
            $coverId = (int) $data['cover_file_id'];
            $query = FilesModel::where('id', $coverId)
                ->where('scene', FilesModel::SCENE_CARD)
                ->where('status', FilesModel::STATUS_NORMAL)
                ->where('upload_status', FilesModel::UPLOAD_COMPLETED)
                ->whereNull('purge_status')
                ->lock(true);
            if (!$allowAll) $query->where('user_id', $uid);
            $file = $coverId > 0 ? $query->find() : null;
            if ($coverId > 0 && !$file) throw \app\api\ApiException::forbidden('无权使用指定封面文件');
            if ($file && !str_starts_with(strtolower((string) ($file->mime_type ?? '')), 'image/')) {
                throw \app\api\ApiException::badRequest('封面文件必须是图片');
            }
            $data['cover'] = $file ? (string) ($file->file_url ?? '') : null;
            unset($data['cover_file_id']);
        }
        return $data;
    }

    private static function mediaResource(array $file, int $position, bool $publicOnly): ?array
    {
        $hash = strtolower(trim((string) ($file['hash'] ?? '')));
        $storedUrl = trim((string) ($file['file_url'] ?? ''));
        $url = $publicOnly && preg_match('/^[a-f0-9]{16,64}$/', $hash)
            ? '/media/' . $hash
            : $storedUrl;
        if ($url === '') return null;
        $mime = strtolower(trim((string) ($file['mime_type'] ?? '')));
        if (!str_starts_with($mime, 'image/') && !str_starts_with($mime, 'video/')) return null;
        $metadata = $file['metadata'] ?? [];
        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            $metadata = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($metadata)) $metadata = [];
        $positive = static fn(mixed $value): ?int => is_numeric($value) && (int) $value > 0 ? (int) $value : null;
        $poster = trim((string) ($metadata['poster_url'] ?? ''));
        return [
            'file_id' => (int) $file['id'],
            'type' => str_starts_with($mime, 'video/') ? 'video' : 'image',
            'url' => $url,
            'mime_type' => $mime,
            'size' => $positive($file['file_size'] ?? null),
            'width' => $positive($metadata['width'] ?? null),
            'height' => $positive($metadata['height'] ?? null),
            'duration_ms' => $positive($metadata['duration_ms'] ?? null),
            'poster_url' => $poster !== '' ? $poster : null,
            'is_animated' => $mime === 'image/gif' || (bool) ($metadata['is_animated'] ?? false),
            'position' => $position,
        ];
    }

    private static function mediaIds($value): array
    {
        if (is_string($value)) $value = json_decode($value, true);
        if (!is_array($value)) return [];
        return array_values(array_unique(array_filter(array_map('intval', $value), static fn(int $id): bool => $id > 0)));
    }

    private static function bindMedia(array $ids, int $uid, int $cardId, bool $public, bool $allowAll): void
    {
        PublicFiles::replaceReferences('card', (string) $cardId, 'attachment', $ids, $uid, $public, $allowAll);
    }

    private static function syncMediaVisibility(array $cardIds, bool $clear = false): void
    {
        foreach (array_values(array_unique(array_map('intval', $cardIds))) as $cardId) {
            if ($cardId <= 0) continue;
            $fileIds = $clear ? [] : PublicFiles::referenceFileIds('card', (string) $cardId);
            $card = $clear ? null : Db::table('cards')->where('id', $cardId)->field('status,deleted_at')->find();
            $isPublic = $card !== null
                && (int) ($card['status'] ?? -1) === PublicationStatus::visible()
                && empty($card['deleted_at']);
            PublicFiles::replaceReferences('card', (string) $cardId, 'attachment', $fileIds, 0, $isPublic, true);
        }
    }
}
