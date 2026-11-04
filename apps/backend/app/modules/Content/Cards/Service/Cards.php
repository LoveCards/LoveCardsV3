<?php

namespace app\modules\Content\Cards\Service;

use app\modules\Platform\Config\Public\Config as ConfigService;

use think\facade\Db;

use app\modules\Content\Infrastructure\Persistence\Model\Cards as CardsModel;
use app\modules\Content\Infrastructure\Persistence\Model\Tags as TagsModel;
use app\modules\Content\Infrastructure\Persistence\Model\TagsMap as TagsMapModel;
use app\modules\Content\Infrastructure\Persistence\Model\Comments as CommentsModel;
use app\modules\Content\Mentions\Service\Mentions;
use app\modules\Content\Tags\Public\Tags as TagsFacade;
use app\modules\Platform\Files\Infrastructure\Persistence\Model\Files as FilesModel;
use app\modules\Platform\Files\Public\Files as PublicFiles;
use app\modules\Platform\Safety\Public\Safety;
use app\modules\Platform\Social\Public\Social;
use app\modules\Platform\Notifications\Contract\SystemNoticeIssuer;
use app\modules\Platform\Notifications\Domain\SystemNotice;

use app\shared\Infrastructure\Persistence\BulkFieldToggle;
use app\shared\Infrastructure\Persistence\ModelQuery;
use app\modules\Content\Cards\Domain\PublicationStatus;
use app\modules\Content\Cards\Service\Policy\CardPolicy;
use app\shared\Authorization\GrantSet;
use app\shared\Observability\AdminMutationAudit;
use app\modules\Content\Cards\Service\CardVisibilityPolicy;

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
    public static function pendingStatus(): int
    {
        return PublicationStatus::pendingApproval();
    }
    const TOP_LISTS_MAX = 32;
    const HOT_LISTS_MAX = 8;

    public static function hotList(int $viewerId = 0): array
    {
        $excluded = $viewerId > 0 ? Safety::blockedUserIds($viewerId) : [];
        $topQuery = CardsModel::where('status', PublicationStatus::visible())
            ->where('is_top', 1)
            ->order('id', 'desc')
            ->limit(self::TOP_LISTS_MAX);
        CardVisibilityPolicy::apply($topQuery, $viewerId);
        if ($excluded !== []) $topQuery->whereNotIn('user_id', $excluded);
        $top = $topQuery->select()->toArray();

        $hotQuery = CardsModel::fieldRaw('*, comments * 0.3 + goods * 0.7 as hot_score')
            ->where('status', PublicationStatus::visible())
            ->where('is_top', 0)
            ->order('hot_score', 'desc')
            ->limit(self::HOT_LISTS_MAX);
        CardVisibilityPolicy::apply($hotQuery, $viewerId);
        if ($excluded !== []) $hotQuery->whereNotIn('user_id', $excluded);
        $hot = $hotQuery->select()->toArray();

        return self::hydrateCards(array_merge($top, $hot), true);
    }

    /**
     * @param array $params
     * @param array $caps 用户能力列表（公开路由传空数组）
     */
    public static function list(array $params, array $caps = [], int $viewerId = 0): array
    {
        $params['search_default_key'] = 'content';

        if (isset($params['tag_id'])) {
            $tagId = (int) $params['tag_id'];
            // 非公开状态（待审核/封禁/隐藏）的标签不得作为公开列表筛选条件，
            // 否则等于绕过审核借卡片列表泄露其内容。
            $visibleTag = TagsModel::where('aid', 1)->where('id', $tagId)->where('status', 0)->findOrEmpty();
            $pids = $visibleTag->isEmpty()
                ? [0]
                : (TagsMapModel::where('aid', 1)->where('tag_id', $tagId)->column('pid') ?: [0]);
            $params['where'][] = ['id', 'in', $pids];
        }

        // 无 cards.read.all 能力 → 只看已发布
        if (!in_array('cards.read.all', $caps)) {
            $params['where']['status'] = PublicationStatus::visible();
            $params['where'][] = static function ($query) use ($viewerId): void {
                CardVisibilityPolicy::apply($query, $viewerId);
            };
        }

        $result = ModelQuery::make(CardsModel::class)->getPaginate($params);
        return self::hydratePaginated($result->toArray(), !in_array('cards.read.all', $caps, true));
    }

    /**
     * Public cards published by users followed by the viewer.
     *
     * The stream is deliberately server-ordered and ignores discovery filters:
     * only visible cards from active, non-deleted followees are eligible.
     */
    public static function listFollowing(array $params, int $uid): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $listRows = min(100, max(1, (int) ($params['list_rows'] ?? 30)));
        $followeeIds = self::activeFolloweeIds($uid);

        $query = CardsModel::whereIn('user_id', $followeeIds ?: [0])
            ->where('status', PublicationStatus::visible())
            ->whereNull('deleted_at')
            ->order('created_at', 'desc')
            ->order('id', 'desc');
        CardVisibilityPolicy::apply($query, $uid);

        return self::hydratePaginated($query->paginate([
            'list_rows' => $listRows,
            'page' => $page,
        ])->toArray(), true);
    }

    /** @return array<int,int> */
    private static function activeFolloweeIds(int $uid): array
    {
        if ($uid < 1) return [];

        $ids = Db::table('user_follows')
            ->alias('f')
            ->join('users u', 'u.id = f.followee_id')
            ->where('f.follower_id', $uid)
            ->where('u.status', 0)
            ->whereNull('u.deleted_at')
            ->field('f.followee_id')
            ->column('f.followee_id');
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if ($ids === []) return [];
        $blockedIds = array_merge(
            Db::table('user_safety_relations')->where(['actor_id' => $uid, 'relation' => 'block'])->column('subject_id'),
            Db::table('user_safety_relations')->where(['subject_id' => $uid, 'relation' => 'block'])->column('actor_id')
        );
        $blocked = array_fill_keys(array_map('intval', $blockedIds), true);
        if ($blocked !== []) $ids = array_values(array_filter($ids, static fn(int $id): bool => !isset($blocked[$id])));
        if ($ids === []) return [];

        // Private profiles never enter a follower's public content stream.
        // The followers visibility mode is allowed because the relationship
        // was already established by the user_follows query above.
        $privateIds = Db::table('privacy_preferences')
            ->whereIn('user_id', $ids)
            ->where('profile_visibility', 'private')
            ->column('user_id');
        if ($privateIds === []) return $ids;

        $private = array_fill_keys(array_map('intval', $privateIds), true);
        return array_values(array_filter($ids, static fn(int $id): bool => !isset($private[$id])));
    }

    /**
     * Public cards for a canonical username handle.
     *
     * The endpoint deliberately has its own query path instead of adding an
     * author filter to the discovery list. This keeps the public visibility
     * policy fixed and prevents client-supplied moderation filters from
     * changing the result set.
     */
    public static function listPublicByUser(string $handle, array $params = [], int $viewerId = 0): array
    {
        $handle = trim($handle);
        $profile = $handle === '' ? null : Social::profile($handle, $viewerId);
        $username = trim((string) ($profile['username'] ?? ''));

        // Social::profile retains its legacy numeric-id compatibility. The
        // public user-card endpoint is handle-only, so reject that fallback
        // when the resolved username does not equal the requested handle.
        if (!is_array($profile) || $username === '' || $username !== $handle) {
            throw \app\api\ApiException::notFound(
                '用户不存在',
                \app\api\ApiException::CODE_PROFILE_NOT_FOUND
            );
        }

        $page = max(1, (int) ($params['page'] ?? 1));
        $listRows = min(100, max(1, (int) ($params['list_rows'] ?? 30)));
        // The profile check above is not a substitute for card-level access.
        // Re-apply the shared policy so followers/private cards cannot leak.
        $result = CardsModel::where('user_id', (int) $profile['id'])
            ->where('status', PublicationStatus::visible())
            ->whereNull('deleted_at');
        CardVisibilityPolicy::apply($result, $viewerId);
        $result = $result->order('created_at', 'desc')->order('id', 'desc')->paginate(['list_rows' => $listRows, 'page' => $page]);

        return self::hydratePaginated($result->toArray(), true);
    }

    public static function listPublicByUid(string $uid, array $params = [], int $viewerId = 0): array
    {
        $profile = Social::profileByUid($uid, $viewerId);
        if (!is_array($profile)) throw \app\api\ApiException::notFound('用户不存在', \app\api\ApiException::CODE_PROFILE_NOT_FOUND);
        $page = max(1, (int) ($params['page'] ?? 1));
        $listRows = min(100, max(1, (int) ($params['list_rows'] ?? 30)));
        $result = CardsModel::where('user_id', (int) $profile['id'])
            ->where('status', PublicationStatus::visible())->whereNull('deleted_at');
        CardVisibilityPolicy::apply($result, $viewerId);
        $result = $result->order('created_at', 'desc')->order('id', 'desc')
            ->paginate(['list_rows' => $listRows, 'page' => $page]);
        return self::hydratePaginated($result->toArray(), true);
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
    public static function get(int $id, array $caps = [], int $viewerId = 0): array
    {
        $result = CardsModel::where('id', $id)->findOrEmpty();
        if ($result->isEmpty()) {
            throw \app\api\ApiException::notFound('卡片不存在');
        }

        // 无 cards.read.all 能力 → 只看已发布
        if (!in_array('cards.read.all', $caps) && $result->status !== PublicationStatus::visible()) {
            throw \app\api\ApiException::notFound('卡片不存在');
        }
        if (!in_array('cards.read.all', $caps, true)
            && !CardVisibilityPolicy::canRead($result->toArray(), $viewerId)) {
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

        return self::hydrateCard(self::decodeJsonFields($result->toArray()), false, null, null, null, true);
    }

    /**
     * 批量操作（能力在 Service 层检查）
     *
     * @param string $method
     * @param array  $ids
     * @param int    $uid
     * @param array  $caps
     */
    public static function batchOperate(
        string $method,
        array $ids,
        int $uid,
        GrantSet $grants,
        string $ipAddress = '',
        bool $auditAdminMutation = false
    ): void
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
            $before = $auditAdminMutation ? self::auditRows($ids, true, true) : [];
            match ($method) {
                'top', 'unset_top' => BulkFieldToggle::toggle(CardsModel::class, 'is_top', $ids, [0, 1]),
                'approve' => self::approveCards($ids),
                'ban', 'hide' => BulkFieldToggle::toggle(
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
            if ($auditAdminMutation) {
                app(AdminMutationAudit::class)->recordChanges(
                    $uid,
                    'admin.card.batch.' . $method,
                    'card',
                    $before,
                    self::auditRows($ids, true),
                    $ipAddress
                );
            }
            Db::commit();
        } catch (\Throwable $th) {
            Db::rollback();
            if ($th instanceof \app\api\ApiException) throw $th;
            throw \app\api\ApiException::error('批量操作失败', \app\api\ApiException::CODE_SYSTEM_ERROR, null, $th);
        }
    }

    public static function createCard($data, ?GrantSet $grants = null): string
    {
        Db::startTrans();
        try {
            $pictureIds = self::mediaIds($data['media_file_ids'] ?? $data['picture_file_ids'] ?? null);
            $coverId = array_key_exists('cover_file_id', $data) ? (int) $data['cover_file_id'] : null;
            $uid = (int) ($data['user_id'] ?? 0);
            $data['visibility'] = CardVisibilityPolicy::normalize($data['visibility'] ?? CardVisibilityPolicy::PUBLIC);
            $data = self::normalizeCardMetadata($data);
            if (array_key_exists('tags', $data) && $data['tags'] !== null) {
                $data['tags'] = self::resolveTagIds($data['tags'], $uid, $grants);
            }
            $data = self::encodeJsonFields(self::normalizeMedia($data, $uid, false));
            $result = CardsModel::create($data);
            $data['id'] = $result->id;
            self::bindMedia($pictureIds, $uid, (int) $result->id,
                (int) ($data['status'] ?? 0) === PublicationStatus::visible()
                && ($data['visibility'] ?? CardVisibilityPolicy::PUBLIC) === CardVisibilityPolicy::PUBLIC,
                false);
            if ($coverId !== null) {
                PublicFiles::replaceReferences('card', (string) $result->id, 'cover', $coverId > 0 ? [$coverId] : [], $uid,
                    (int) ($data['status'] ?? 0) === PublicationStatus::visible()
                    && ($data['visibility'] ?? CardVisibilityPolicy::PUBLIC) === CardVisibilityPolicy::PUBLIC,
                    false);
            }
            if (isset($data['tags'])) {
                self::updateCardTags($data, true);
            }
            if ($data['visibility'] !== CardVisibilityPolicy::PUBLIC) CardMedia::assertRestrictedStorage((int) $result->id);
            if ((int) ($data['status'] ?? 0) === PublicationStatus::visible()) {
                self::captureCardMentions(self::decodeJsonFields($result->toArray()));
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
    public static function updateCard(
        array $data,
        int $uid,
        GrantSet $grants,
        string $ipAddress = '',
        bool $auditAdminMutation = false
    ): void
    {
        $shouldAudit = $auditAdminMutation;
        Db::startTrans();
        try {
            $cardId = (int) $data['id'];
            if (array_key_exists('visibility', $data)) {
                $data['visibility'] = CardVisibilityPolicy::normalize($data['visibility']);
            }
            if (isset($data['data']) && is_array($data['data']) && array_key_exists('visibility', $data['data'])) {
                throw \app\api\ApiException::validation(['data.visibility' => ['请使用卡片顶层 visibility 字段。']]);
            }
            $beforeAudit = $shouldAudit ? self::auditRows([$cardId], false, true) : [];
            // 验证能力 + 归属
            CardPolicy::assertCan($grants, 'update', $cardId, $uid);

            // 管理端的通用编辑能力不能隐式获得置顶或审核能力。即使调用方
            // 绕过前端直接提交字段，Service 仍按字段执行最终授权与归属检查。
            if (array_key_exists('is_top', $data)) {
                CardPolicy::assertCan($grants, 'pin', (int) $data['id'], $uid);
            }
            if (array_key_exists('status', $data)) {
                CardPolicy::assertCan($grants, 'approve', (int) $data['id'], $uid);
            }

            // 作者与派生统计只能由专用业务流程维护，永远不接受通用更新。
            unset($data['user_id'], $data['comments'], $data['views'], $data['good'], $data['goods']);

            // Text and video cards do not retain an image cover when the
            // caller switches kind without sending an explicit cover field.
            $metadata = $data['data'] ?? null;
            if (is_string($metadata)) $metadata = json_decode($metadata, true);
            $kind = is_array($metadata) ? strtolower(trim((string) ($metadata['kind'] ?? ''))) : '';
            if ($kind !== '' && in_array($kind, ['text', 'video', 'discussion'], true) && !array_key_exists('cover_file_id', $data)) {
                $data['cover_file_id'] = 0;
            }
            $hasMediaIds = array_key_exists('media_file_ids', $data) || array_key_exists('picture_file_ids', $data);
            $coverId = array_key_exists('cover_file_id', $data) ? (int) $data['cover_file_id'] : null;
            $pictureIds = $hasMediaIds
                ? self::mediaIds($data['media_file_ids'] ?? $data['picture_file_ids'])
                : null;
            if (array_key_exists('tags', $data) && $data['tags'] !== null) {
                $data['tags'] = self::resolveTagIds($data['tags'], $uid, $grants, (int) ($data['id'] ?? 0));
            }
            $data = self::normalizeCardMetadata($data);
            $data = self::encodeJsonFields(
                self::normalizeMedia($data, $uid, $grants->allows('cards.update', \app\shared\Authorization\Scope::ALL))
            );

            if (isset($data['tags'])) {
                self::updateCardTags($data);
            }

            CardsModel::update($data);
            if ($pictureIds !== null) {
                $currentCard = CardsModel::where('id', (int) $data['id'])->field('status,visibility')->find();
                $effectiveStatus = array_key_exists('status', $data) ? (int) $data['status'] : (int) ($currentCard->status ?? -1);
                $effectiveVisibility = array_key_exists('visibility', $data) ? (string) $data['visibility'] : (string) ($currentCard->visibility ?? CardVisibilityPolicy::PUBLIC);
                self::bindMedia(
                    $pictureIds,
                    $uid,
                    (int) $data['id'],
                    $effectiveStatus === PublicationStatus::visible() && $effectiveVisibility === CardVisibilityPolicy::PUBLIC,
                    $grants->allows('cards.update', \app\shared\Authorization\Scope::ALL)
                );
            }
            if ($coverId !== null) {
                $currentCard = CardsModel::where('id', (int) $data['id'])->field('status,visibility')->find();
                $effectiveStatus = array_key_exists('status', $data) ? (int) $data['status'] : (int) ($currentCard->status ?? -1);
                $effectiveVisibility = array_key_exists('visibility', $data) ? (string) $data['visibility'] : (string) ($currentCard->visibility ?? CardVisibilityPolicy::PUBLIC);
                PublicFiles::replaceReferences('card', (string) $data['id'], 'cover', $coverId > 0 ? [$coverId] : [], $uid,
                    $effectiveStatus === PublicationStatus::visible() && $effectiveVisibility === CardVisibilityPolicy::PUBLIC,
                    $grants->allows('cards.update', \app\shared\Authorization\Scope::ALL));
            }
            if (array_key_exists('status', $data) || array_key_exists('visibility', $data)) {
                self::syncMediaVisibility([(int) $data['id']]);
            }
            if (array_key_exists('visibility', $data) || $hasMediaIds || $coverId !== null) {
                $current = CardsModel::where('id', $cardId)->find();
                if ($current && (string) $current->visibility !== CardVisibilityPolicy::PUBLIC) CardMedia::assertRestrictedStorage($cardId);
            }

            $card = CardsModel::where('id', (int) $data['id'])
                ->where('status', PublicationStatus::visible())
                ->findOrEmpty();
            if (!$card->isEmpty()) self::captureCardMentions(self::decodeJsonFields($card->toArray()));

            if ($shouldAudit) {
                app(AdminMutationAudit::class)->recordChanges(
                    $uid,
                    'admin.card.update',
                    'card',
                    $beforeAudit,
                    self::auditRows([$cardId]),
                    $ipAddress
                );
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
            $tagId = (int) $tag_id;
            if ($tagId <= 0) continue;
            $item = [
                'aid' => 1,
                'pid' => $pid,
                'tag_id' => $tagId,
            ];
            TagsMapModel::create($item);
        }
    }

    /** Explicit PENDING -> VISIBLE transition; repeated approval is a no-op. */
    private static function approveCards(array $ids): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids, SORT_NUMERIC);
        $cards = CardsModel::whereIn('id', $ids)
            ->where('status', PublicationStatus::pendingApproval())
            ->order('id', 'asc')
            ->lock(true)
            ->select()
            ->toArray();
        if ($cards === []) return;

        $approvedIds = array_map(static fn(array $row): int => (int) $row['id'], $cards);
        CardsModel::whereIn('id', $approvedIds)
            ->where('status', PublicationStatus::pendingApproval())
            ->update(['status' => PublicationStatus::visible()]);
        foreach ($cards as $card) {
            $card['status'] = PublicationStatus::visible();
            app(SystemNoticeIssuer::class)->issue(
                (int) $card['user_id'],
                'moderation.card.' . (int) $card['id'] . '.approved',
                SystemNotice::MODERATION,
                '你的卡片已通过审核并公开发布。',
                '/post/' . (int) $card['id'],
                'success'
            );
            self::captureCardMentions(self::decodeJsonFields($card));
        }
    }

    private static function captureCardMentions(array $card): void
    {
        $cardId = (int) ($card['id'] ?? 0);
        $actorId = (int) ($card['user_id'] ?? 0);
        if ($cardId < 1 || $actorId < 1) return;
        $metadata = is_array($card['data'] ?? null) ? $card['data'] : [];
        $mentions = Mentions::capture(
            'card',
            $cardId,
            $cardId,
            null,
            $actorId,
            [(string) ($metadata['title'] ?? ''), (string) ($card['content'] ?? '')]
        );
        Mentions::publishAdded('card', $cardId, $cardId, null, $actorId, $mentions);
    }

    /**
     * Accept legacy tag names as well as canonical IDs, then persist IDs only.
     * @param mixed $value
     * @return array<int,int>
     */
    private static function resolveTagIds($value, int $uid, ?GrantSet $grants = null, ?int $pid = null): array
    {
        $tags = is_string($value) ? json_decode($value, true) : $value;
        if (!is_array($tags)) {
            throw \app\api\ApiException::badRequest('标签数据格式错误');
        }

        $ids = [];
        foreach ($tags as $raw) {
            $name = trim(ltrim((string) $raw, '#'));
            if ($name === '') continue;

            if (ctype_digit($name)) {
                $tag = TagsModel::where('aid', 1)->where('status', 0)->where('id', (int) $name)->find();
                if ($tag === null || $tag->isEmpty()) {
                    throw \app\api\ApiException::badRequest('话题不存在或不可用');
                }
                $id = (int) $tag->id;
            } else {
                // 编辑保存场景：卡片已关联的同名待审核标签幂等重挂，
                // 不因创建开关拒绝或放行重复行（编辑视图已把它回显给用户）。
                $mappedPending = $pid !== null && $pid > 0 ? self::mappedPendingTagId($pid, $name) : 0;
                if ($mappedPending > 0) {
                    $id = $mappedPending;
                } else {
                    // 名称解析统一走 Tags 模块 Public 门面：开关、审核与去重都在那里裁决。
                    $id = TagsFacade::ensureTag($name, $uid, $grants);
                }
            }

            if ($id > 0 && !in_array($id, $ids, true)) $ids[] = $id;
        }

        return $ids;
    }

    /**
     * 查询该卡片是否已关联指定名称的待审核标签（编辑保存的幂等依据）。
     */
    private static function mappedPendingTagId(int $pid, string $name): int
    {
        $row = Db::table('tags_map')
            ->alias('tm')
            ->join('tags t', 't.id = tm.tag_id')
            ->where('tm.aid', 1)
            ->where('tm.pid', $pid)
            ->where('t.aid', 1)
            ->where('t.status', 3)
            ->where('t.name', $name)
            ->where('tm.status', 0)
            ->whereNull('tm.deleted_at')
            ->whereNull('t.deleted_at')
            ->field('tm.tag_id')
            ->find();
        return (int) (is_array($row) ? ($row['tag_id'] ?? 0) : 0);
    }

    /**
     * 删除卡片
     *
     * @param array $ids 资源 ID 列表
     * @param int   $uid 当前用户 ID
     * @param array $caps 用户能力列表
     */
    public static function deleteCards(
        array $ids,
        int $uid,
        GrantSet $grants,
        string $ipAddress = '',
        bool $auditAdminMutation = false
    ): void
    {
        if (empty($ids)) {
            throw \app\api\ApiException::badRequest('未指定要删除的卡片');
        }

        Db::startTrans();
        try {
            $beforeAudit = $auditAdminMutation ? self::auditRows($ids, false, true) : [];
            // 验证能力 + 归属
            CardPolicy::assertCanBatch($grants, 'delete', $ids, $uid);

            CardsModel::destroy($ids);
            Db::table('cards')->whereIn('id', $ids)->update(['pinned_comment_id' => null]);
            self::syncMediaVisibility($ids);

            if ($auditAdminMutation) {
                app(AdminMutationAudit::class)->recordChanges(
                    $uid,
                    'admin.card.delete',
                    'card',
                    $beforeAudit,
                    self::auditRows($ids, true),
                    $ipAddress
                );
            }

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
        Db::table('cards')->whereIn('id', $ids)->update(['pinned_comment_id' => null]);
    }

    private static function restoreCards(array $ids): void
    {
        CardsModel::withTrashed()->onlyTrashed()->whereIn('id', $ids)->update(['deleted_at' => null]);
    }

    private static function hardDeleteCards(array $ids): void
    {
        CardsModel::whereIn('id', $ids)->update(['pinned_comment_id' => null]);
        Db::table('tags_map')->where('aid', 1)->whereIn('pid', $ids)->delete();
        Db::table('comments')->where('aid', 1)->whereIn('pid', $ids)->delete();
        Db::table('cards')->whereIn('id', $ids)->delete();
    }

    /** @return array<int,array<string,mixed>> */
    private static function auditRows(array $ids, bool $includeDeleted = false, bool $lock = false): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if ($ids === []) return [];
        $query = Db::table('cards')->whereIn('id', $ids)->order('id', 'asc');
        if (!$includeDeleted) $query->whereNull('deleted_at');
        if ($lock) $query->lock(true);
        $result = [];
        foreach ($query->select()->toArray() as $row) $result[(int) $row['id']] = $row;
        return $result;
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
        // 卡片已在查询入口完成授权；受限媒体地址仍需独立凭据。
        $restrictedIds = array_values(array_map(static fn(array $card): int => (int) $card['id'], array_filter($decoded, static fn($card): bool => is_array($card) && ($card['visibility'] ?? 'public') !== 'public')));
        $publicIds = array_values(array_diff($cardIds, $restrictedIds));
        $mediaFiles = self::mediaFilesForCards($publicIds, $publicOnly) + self::mediaFilesForCards($restrictedIds, false);
        // Keep the pure hydration baseline usable when the persistence model is not bootstrapped.
        $topics = class_exists(TagsMapModel::class) ? self::topicsForCards($cardIds) : [];
        $usernames = self::usernamesForCards($decoded);
        $uids = self::uidsForCards($decoded);
        $mentions = class_exists(Mentions::class) ? Mentions::projectionsForResources('card', $decoded) : [];

        return array_map(static function ($card) use ($publicOnly, $mediaFiles, $usernames, $uids, $topics, $mentions) {
            if (!is_array($card)) return $card;
            $cardId = (int) ($card['id'] ?? 0);
            return self::hydrateCard($card, $publicOnly, $mediaFiles[$cardId] ?? [], $usernames, $topics[$cardId] ?? [], false, $uids, $mentions[$cardId] ?? []);
        }, $decoded);
    }

    private static function hydrateCard(array $card, bool $publicOnly = false, ?array $preloadedFiles = null, ?array $preloadedUsernames = null, ?array $topics = null, bool $editable = false, ?array $preloadedUids = null, ?array $preloadedMentions = null): array
    {
        $cardId = (int) ($card['id'] ?? 0);
        $card['mentions'] = $preloadedMentions ?? (class_exists(Mentions::class) ? (self::mentionsForCards([$card])[$cardId] ?? []) : []);
        if ($topics === null && $cardId > 0) {
            // 编辑视图（editable）需包含本人卡片上待审核的标签，保证"先审后发"下编辑保存不丢关联。
            $topics = self::topicsForCards([$cardId], $editable)[$cardId] ?? [];
        }
        $card['topics'] = $topics ?? [];
        // Card.tags is the persisted relation-id projection.  Normalize old
        // JSON/string values so every CardResource exposes number[] and the
        // human-readable names remain exclusively in topics[].
        $rawTags = $card['tags'] ?? [];
        if (is_string($rawTags)) {
            $decodedTags = json_decode($rawTags, true);
            $rawTags = json_last_error() === JSON_ERROR_NONE ? $decodedTags : [];
        }
        $card['tags'] = is_array($rawTags)
            ? array_values(array_filter(array_map('intval', $rawTags), static fn(int $id): bool => $id > 0))
            : [];
        $userId = (int) ($card['user_id'] ?? 0);
        $usernames = $preloadedUsernames ?? self::usernamesForCards([$card]);
        $username = trim((string) ($usernames[$userId] ?? ''));
        if ($username !== '') $card['username'] = $username;
        $uids = $preloadedUids ?? self::uidsForCards([$card]);
        $card['uid'] = isset($uids[$userId]) ? (string) $uids[$userId] : null;

        $pictures = $card['pictures'] ?? [];
        if (is_string($pictures)) {
            $decoded = json_decode($pictures, true);
            $pictures = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        }
        $pictures = is_array($pictures)
            ? array_values(array_filter(array_map('strval', $pictures)))
            : [];
        if ($publicOnly) {
            $cover = trim((string) ($card['cover'] ?? ''));
            if ($cover !== '' && preg_match('#^/media/[a-f0-9]{16,64}$#', $cover) !== 1) {
                $card['cover'] = null;
            }
        }
        if ($cardId > 0) {
            $files = $preloadedFiles ?? (self::mediaFilesForCards([$cardId], $publicOnly && ($card['visibility'] ?? 'public') === 'public')[$cardId] ?? []);
            $media = [];
            foreach ($files as $position => $file) {
                $item = self::mediaResource($file, $position + 1, true);
                if ($item !== null && ($card['visibility'] ?? 'public') !== 'public') {
                    $item['url'] = '/media/card/' . $cardId . '/' . (int) $file['id'];
                    $item['poster_url'] = null;
                }
                if ($item !== null) $media[] = $item;
            }
            if ($media !== []) {
                $card['media'] = $media;
                $card['pictures'] = array_column($media, 'url');
                return $card;
            }
        }

        // A legacy card can still contain arbitrary URLs in `pictures` even
        // when no file-reference row exists. Public projections must fail
        // closed for those unverified URLs; only the stable public media
        // route is safe to carry forward without a file ledger.
        if ($publicOnly) {
            $pictures = array_values(array_filter(
                $pictures,
                static fn(string $url): bool => preg_match('#^/media/[a-f0-9]{16,64}$#', $url) === 1
            ));
        }

        $card['pictures'] = $pictures;
        $legacyMedia = array_map(static fn(string $url, int $position): array => [
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
        $card['media'] = $editable
            ? array_values(array_filter($legacyMedia, static fn(array $item): bool => (int) ($item['file_id'] ?? 0) > 0))
            : $legacyMedia;

        return $card;
    }

    /** @return array<int,array<int,array<string,mixed>>> */
    private static function mentionsForCards(array $cards): array
    {
        return Mentions::projectionsForResources('card', $cards);
    }

    /** @return array<int,array<int,array{id:int,name:string}>> */
    private static function topicsForCards(array $cardIds, bool $includePending = false): array
    {
        if ($cardIds === []) return [];

        $query = Db::table('tags_map')
            ->alias('tm')
            ->join('tags t', 't.id = tm.tag_id')
            ->where('tm.aid', 1)
            ->whereIn('tm.pid', $cardIds)
            ->where('t.aid', 1);
        if ($includePending) {
            // 编辑视图包含待审核标签（status=3），避免用户保存编辑时静默丢掉未放行的关联。
            $query->whereIn('t.status', [0, 3]);
        } else {
            $query->where('t.status', 0);
        }
        $rows = $query->where('tm.status', 0)
            ->whereNull('tm.deleted_at')
            ->whereNull('t.deleted_at')
            ->field('tm.pid, t.id, t.name')
            ->order('tm.id', 'asc')
            ->select()
            ->toArray();

        $result = [];
        foreach ($rows as $row) {
            $pid = (int) ($row['pid'] ?? 0);
            $id = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($pid <= 0 || $id <= 0 || $name === '') continue;
            $result[$pid][] = ['id' => $id, 'name' => $name];
        }
        return $result;
    }

    /**
     * Only active, non-deleted users receive a public profile handle.
     *
     * @return array<int,string>
     */
    private static function usernamesForCards(array $cards): array
    {
        $userIds = array_values(array_unique(array_filter(array_map(
            static fn($card): int => is_array($card) ? (int) ($card['user_id'] ?? 0) : 0,
            $cards
        ), static fn(int $id): bool => $id > 0)));
        if ($userIds === []) return [];

        $rows = Db::table('users')
            ->whereIn('id', $userIds)
            ->where('status', 0)
            ->whereNull('deleted_at')
            ->field('id,username')
            ->select()
            ->toArray();

        $result = [];
        foreach ($rows as $row) {
            $username = trim((string) ($row['username'] ?? ''));
            if ($username !== '') $result[(int) $row['id']] = $username;
        }
        return $result;
    }

    /** @return array<int,string> */
    private static function uidsForCards(array $cards): array
    {
        $userIds = array_values(array_unique(array_filter(array_map(
            static fn($card): int => is_array($card) ? (int) ($card['user_id'] ?? 0) : 0,
            $cards
        ), static fn(int $id): bool => $id > 0)));
        if ($userIds === []) return [];
        $rows = Db::table('users')->whereIn('id', $userIds)->where('status', 0)->whereNull('deleted_at')->field('id,uid')->select()->toArray();
        $result = [];
        foreach ($rows as $row) if (!empty($row['uid'])) $result[(int) $row['id']] = (string) $row['uid'];
        return $result;
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
            array_map('strval', $cardIds),
            'attachment'
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

    /**
     * Ordinary Cards have one canonical body. Historical data.title/summary
     * remains readable, but new writes cannot recreate a title+body layer.
     */
    private static function normalizeCardMetadata(array $data): array
    {
        if (!array_key_exists('data', $data)) return $data;
        $metadata = $data['data'];
        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);
            $metadata = is_array($decoded) ? $decoded : null;
        }
        if (is_array($metadata)) {
            unset($metadata['title'], $metadata['summary']);
            $data['data'] = $metadata;
        }
        return $data;
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
        if ($publicOnly) {
            // Public cards must never fall back to a legacy/private file URL.
            // The stable media route performs the final visibility check.
            if (preg_match('/^[a-f0-9]{16,64}$/', $hash) !== 1) return null;
            $url = '/media/' . $hash;
        } else {
            $url = $storedUrl;
        }
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
        if ($publicOnly && $poster !== '' && preg_match('#^/media/[a-f0-9]{16,64}$#', $poster) !== 1) {
            $poster = '';
        }
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
              $card = $clear ? null : Db::table('cards')->where('id', $cardId)->field('status,deleted_at,visibility')->find();
              $isPublic = $card !== null
                  && (int) ($card['status'] ?? -1) === PublicationStatus::visible()
                  && empty($card['deleted_at'])
                  && (string) ($card['visibility'] ?? 'public') === 'public';
              // A card's cover is also a public media reference. Keep both
              // purposes in sync when publication visibility changes.
              foreach (['attachment', 'cover'] as $purpose) {
                  $fileIds = $clear ? [] : PublicFiles::referenceFileIds('card', (string) $cardId, $purpose);
                  PublicFiles::replaceReferences('card', (string) $cardId, $purpose, $fileIds, 0, $isPublic, true);
              }
        }
    }
}
