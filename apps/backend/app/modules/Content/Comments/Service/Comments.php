<?php

namespace app\modules\Content\Comments\Service;

use app\modules\Platform\Config\Public\Config as ConfigService;

use think\facade\Db;

use app\modules\Content\Infrastructure\Persistence\Model\Comments as CommentsModel;
use app\modules\Content\Infrastructure\Persistence\Model\Cards as CardsModel;
use app\modules\Content\Cards\Public\CardAccess;
use app\modules\Content\Mentions\Service\Mentions;
use app\modules\Platform\Eventing\Public\DomainEventBus;
use app\modules\Platform\Notifications\Contract\SystemNoticeIssuer;
use app\modules\Platform\Notifications\Domain\SystemNotice;

use app\shared\Infrastructure\Persistence\BulkFieldToggle;
use app\shared\Infrastructure\Persistence\ModelQuery;
use app\shared\Authorization\GrantSet;
use app\shared\Authorization\Scope;
use app\modules\Content\Comments\Service\Policy\CommentPolicy;
use app\shared\Observability\AdminMutationAudit;

class Comments
{
    public static function count(): int
    {
        return (int) CommentsModel::count();
    }
    public static function requiresApproval(): bool
    {
        return (bool) ConfigService::get('comments.approve');
    }
    /**
     * 审核评论（仅允许管理员修改审核字段）
     *
     * @param int   $uid
     * @param array $data
     * @param array $caps
     */
    public static function moderateComment(
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
            $commentId = (int) $data['id'];
            $updatesContent = array_key_exists('content', $data);
            $updatesModeration = array_key_exists('status', $data);
            if (!$updatesContent && !$updatesModeration) {
                throw \app\api\ApiException::badRequest('没有可更新的评论字段');
            }
            if ($updatesContent && trim((string) $data['content']) === '') {
                throw \app\api\ApiException::badRequest('评论不能为空');
            }
            if ($updatesContent) CommentPolicy::assertCan($grants, 'update', $commentId, $uid);
            if ($updatesModeration) CommentPolicy::assertCan($grants, 'moderate', $commentId, $uid);

            $before = CommentsModel::where('id', $commentId)->lock(true)->findOrEmpty();
            if ($before->isEmpty()) throw \app\api\ApiException::notFound('评论不存在');
            $wasPending = (int) $before->status === 3;

            // 移除敏感字段
            unset($data['user_id']);

            CommentsModel::update($data, ['id' => $data['id']]);
            if (array_key_exists('status', $data) && (int) $data['status'] !== 0) {
                self::clearPinsForComments([$commentId]);
            }

            $comment = CommentsModel::where('id', $commentId)->findOrEmpty();
            $isVisible = !$comment->isEmpty() && (int) $comment->status === 0;
            if ($isVisible) {
                $cardId = (int) $comment->pid;
                $card = CardsModel::where('id', $cardId)->where('status', 0)->findOrEmpty();
                $parentId = (int) ($comment->parent_id ?? 0);
                $parent = $parentId > 0
                    ? CommentsModel::where('id', $parentId)
                        ->where('aid', 1)
                        ->where('pid', $cardId)
                        ->where('status', 0)
                        ->findOrEmpty()
                    : null;
                $canPublish = !$card->isEmpty()
                    && ($parentId === 0 || ($parent !== null && !$parent->isEmpty()));
                if ($wasPending && !$canPublish) {
                    throw \app\api\ApiException::badRequest('卡片或父评论当前不可见，无法通过审核');
                }
                if ($canPublish) {
                    $mentions = Mentions::capture(
                        'comment',
                        (int) $comment->id,
                        $cardId,
                        (int) $comment->id,
                        (int) $comment->user_id,
                        [(string) $comment->content]
                    );
                    $primaryRecipientId = $parentId > 0
                        ? (int) $parent->user_id
                        : (int) $card->user_id;
                    if ($wasPending) {
                        self::publishApprovalNotice((int) $comment->user_id, $cardId, (int) $comment->id);
                        self::publishComment(
                            $comment->toArray(),
                            (int) $card->user_id,
                            $parentId > 0 ? (int) $parent->user_id : null,
                            $mentions
                        );
                    } else {
                        Mentions::publishAdded(
                            'comment',
                            (int) $comment->id,
                            $cardId,
                            (int) $comment->id,
                            (int) $comment->user_id,
                            $mentions,
                            [$primaryRecipientId]
                        );
                    }
                }
            }

            if ($shouldAudit) {
                app(AdminMutationAudit::class)->recordChanges(
                    $uid,
                    $updatesContent ? 'admin.comment.update' : 'admin.comment.moderate',
                    'comment',
                    [$commentId => $before->toArray()],
                    $comment->isEmpty() ? [] : [$commentId => $comment->toArray()],
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

    public static function newList(array $params, int $user_id = -1): array
    {
        $params['search_default_key'] = 'content';
        if ($user_id != -1) {
            $params['where'] = [
                'status' => [0, 1, 3],
                'user_id' => $user_id
            ];
        }
        $result = ModelQuery::make(CommentsModel::class)->getPaginate($params);
        return self::withUsernames($result->toArray());
    }

    /**
     * @param array $params
     * @param array $caps
     */
    public static function listAll(array $params, array $caps = []): array
    {
        $params['search_default_key'] = 'content';
        $canReadAll = in_array('comments.read.all', $caps, true);

        // 无 comments.read.all 能力 → 只看已发布
        if (!$canReadAll) {
            $params['where']['status'] = 0;
        }

        $result = ModelQuery::make(CommentsModel::class)
            ->trashScope((int) ($params['show_deleted'] ?? 0), $canReadAll)
            ->getPaginate($params);
        $rows = $result->toArray();
        $rows = $canReadAll ? $rows : self::withDeletedParentPlaceholders($rows, $params);
        return self::withUsernames($rows);
    }

    /** Public card-scoped list with the single pinned top-level comment first. */
    public static function listCard(int $cardId, array $params, int $viewerId = 0): array
    {
        // Public comment reads must follow the card visibility contract. The
        // comment table is not the source of truth for whether its parent
        // resource is still publicly available.
        $card = CardAccess::assertReadable($cardId, $viewerId);
        $page = max(1, (int) ($params['page'] ?? 1));
        $listRows = max(1, min(100, (int) ($params['list_rows'] ?? 15)));
        // 置顶只从卡片字段读取，旧关系表仅用于历史迁移。
        $pinnedId = (int) ($card['pinned_comment_id'] ?? 0);
        // A stale pin must never promote a reply, hidden comment, or comment
        // belonging to another card. The card-level pointer is authoritative
        // only after this visibility/top-level check.
        if ($pinnedId > 0) {
            $pinned = CommentsModel::where('id', $pinnedId)
                ->where('aid', 1)->where('pid', $cardId)->where('status', 0)->whereNull('deleted_at')
                ->findOrEmpty();
            if ($pinned->isEmpty() || (int) $pinned->parent_id !== 0) $pinnedId = 0;
        }
        $result = CommentsModel::where('aid', 1)
            ->where('pid', $cardId)
            ->where('status', 0)
            ->orderRaw('(`id` = ' . $pinnedId . ') DESC')
            ->order('id', 'desc')
            ->paginate(['list_rows' => $listRows, 'page' => $page])
            ->toArray();
        $params['where'] = ['aid' => 1, 'pid' => $cardId, 'status' => 0];
        $result = self::withUsernames(self::withDeletedParentPlaceholders($result, $params));
        $result['data'] = array_map(static function (array $row) use ($pinnedId): array {
            $row['is_pinned'] = $pinnedId > 0 && (int) ($row['id'] ?? 0) === $pinnedId;
            return $row;
        }, $result['data'] ?? []);
        return $result;
    }

    public static function get(int $id, bool $includeHidden = false, int $viewerId = 0): array
    {
        $query = CommentsModel::where('id', $id);
        if (!$includeHidden) $query->where('status', 0);
        $result = $query->findOrEmpty();
        if ($result->isEmpty()) {
            throw \app\api\ApiException::notFound('评论不存在');
        }
        if (!$includeHidden) {
            CardAccess::assertReadable((int) $result->pid, $viewerId);
        }
        return self::withUsername($result->toArray());
    }

    public static function createComment($params): array
    {
        $id = (int) $params['id'];
        unset($params['id']);
        $params['aid'] = 1;
        $params['pid'] = $id;
        $parentId = (int) ($params['parent_id'] ?? 0);
        if ($parentId < 0) throw \app\api\ApiException::badRequest('父评论标识不合法');
        $params['parent_id'] = $parentId;

        Db::startTrans();
        try {
            $card = CardsModel::where('id', $id)->where('status', 0)->lock(true)->findOrEmpty();
            if ($card->isEmpty()) throw \app\api\ApiException::notFound('卡片不存在');
            CardAccess::assertReadable($id, (int) ($params['user_id'] ?? 0));
            $parent = null;
            if ($parentId > 0) {
                $parent = CommentsModel::where('id', $parentId)
                    ->where('aid', 1)
                    ->where('pid', $id)
                    ->where('status', 0)
                    ->lock(true)
                    ->findOrEmpty();
                if ($parent->isEmpty()) {
                    throw \app\api\ApiException::badRequest('父评论不存在、不可见或不属于当前卡片');
                }
            }
            $comment = CommentsModel::create($params);
            if ((int) $comment->status === 0) {
                CardsModel::where('id', $id)->where('status', 0)->inc('comments')->update();
            }

            if ((int) $comment->status === 0) {
                $mentions = Mentions::capture(
                    'comment',
                    (int) $comment->id,
                    $id,
                    (int) $comment->id,
                    (int) $comment->user_id,
                    [(string) $comment->content]
                );
                self::publishComment(
                    $comment->toArray(),
                    (int) $card->user_id,
                    $parent !== null ? (int) $parent->user_id : null,
                    $mentions
                );
            }

            Db::commit();
            return ['data' => $comment];
        } catch (\Throwable $th) {
            Db::rollback();
            if ($th instanceof \app\api\ApiException) throw $th;
            throw \app\api\ApiException::error('创建失败', \app\api\ApiException::CODE_SYSTEM_ERROR, null, $th);
        }
    }

    public static function pinComment(
        int $cardId,
        int $commentId,
        int $actorId,
        GrantSet $grants,
        string $ipAddress = ''
    ): void {
        if (!$grants->allows('comments.pin', Scope::ALL)) {
            throw \app\api\ApiException::forbidden('当前账号无权置顶评论');
        }
        Db::startTrans();
        try {
            $card = CardsModel::where('id', $cardId)->where('status', 0)->lock(true)->findOrEmpty();
            if ($card->isEmpty()) throw \app\api\ApiException::notFound('卡片不存在或不可用');
            $comment = CommentsModel::where('id', $commentId)
                ->where('aid', 1)
                ->where('pid', $cardId)
                ->where('parent_id', 0)
                ->where('status', 0)
                ->lock(true)
                ->findOrEmpty();
            if ($comment->isEmpty()) {
                throw \app\api\ApiException::badRequest('只能置顶当前卡片下可见的一级评论');
            }

            $before = self::pinAuditRow($cardId, true);
            CardsModel::where('id', $cardId)->update(['pinned_comment_id' => $commentId]);
            app(AdminMutationAudit::class)->recordChanges(
                $actorId,
                'admin.comment.pin',
                'card_comment_pin',
                $before === [] ? [] : [$cardId => $before],
                [$cardId => self::pinAuditRow($cardId)],
                $ipAddress
            );
            Db::commit();
        } catch (\Throwable $exception) {
            Db::rollback();
            if ($exception instanceof \app\api\ApiException) throw $exception;
            throw \app\api\ApiException::error('置顶评论失败', \app\api\ApiException::CODE_SYSTEM_ERROR, null, $exception);
        }
    }

    /** Author-scoped pinning is a user action and does not write an administration audit event. */
    public static function pinOwnComment(int $cardId, int $commentId, int $actorId, GrantSet $grants): void
    {
        if (!$grants->allows('comments.pin', Scope::OWN)) {
            throw \app\api\ApiException::forbidden('当前账号无权置顶评论');
        }
        Db::startTrans();
        try {
            $card = CardsModel::where('id', $cardId)->where('status', 0)->lock(true)->findOrEmpty();
            if ($card->isEmpty()) throw \app\api\ApiException::notFound('卡片不存在或不可用');
            if ((int) $card->user_id !== $actorId) throw \app\api\ApiException::forbidden('只有卡片作者可以置顶评论');
            $comment = CommentsModel::where('id', $commentId)->where('aid', 1)->lock(true)->findOrEmpty();
            if ($comment->isEmpty()) throw \app\api\ApiException::notFound('评论不存在');
            if ((int) $comment->pid !== $cardId || (int) $comment->parent_id !== 0 || (int) $comment->status !== 0) {
                throw \app\api\ApiException::badRequest('只能置顶当前卡片下可见的一级评论');
            }

            CardsModel::where('id', $cardId)->update(['pinned_comment_id' => $commentId]);
            Db::commit();
        } catch (\Throwable $exception) {
            Db::rollback();
            if ($exception instanceof \app\api\ApiException) throw $exception;
            throw \app\api\ApiException::error('置顶评论失败', \app\api\ApiException::CODE_SYSTEM_ERROR, null, $exception);
        }
    }

    public static function unpinOwnComment(int $cardId, int $actorId, GrantSet $grants): void
    {
        if (!$grants->allows('comments.pin', Scope::OWN)) {
            throw \app\api\ApiException::forbidden('当前账号无权取消置顶评论');
        }
        Db::startTrans();
        try {
            $card = CardsModel::where('id', $cardId)->where('status', 0)->lock(true)->findOrEmpty();
            if ($card->isEmpty()) throw \app\api\ApiException::notFound('卡片不存在或不可用');
            if ((int) $card->user_id !== $actorId) throw \app\api\ApiException::forbidden('只有卡片作者可以取消置顶评论');
            CardsModel::where('id', $cardId)->update(['pinned_comment_id' => null]);
            Db::commit();
        } catch (\Throwable $exception) {
            Db::rollback();
            if ($exception instanceof \app\api\ApiException) throw $exception;
            throw \app\api\ApiException::error('取消置顶评论失败', \app\api\ApiException::CODE_SYSTEM_ERROR, null, $exception);
        }
    }

    public static function unpinComment(
        int $cardId,
        int $actorId,
        GrantSet $grants,
        string $ipAddress = ''
    ): void {
        if (!$grants->allows('comments.pin', Scope::ALL)) {
            throw \app\api\ApiException::forbidden('当前账号无权取消置顶评论');
        }
        Db::startTrans();
        try {
            $card = CardsModel::where('id', $cardId)->lock(true)->findOrEmpty();
            if ($card->isEmpty()) throw \app\api\ApiException::notFound('卡片不存在');
            $before = self::pinAuditRow($cardId, true);
            if ($before !== []) {
                CardsModel::where('id', $cardId)->update(['pinned_comment_id' => null]);
                app(AdminMutationAudit::class)->recordChanges(
                    $actorId,
                    'admin.comment.unpin',
                    'card_comment_pin',
                    [$cardId => $before],
                    [],
                    $ipAddress
                );
            }
            Db::commit();
        } catch (\Throwable $exception) {
            Db::rollback();
            if ($exception instanceof \app\api\ApiException) throw $exception;
            throw \app\api\ApiException::error('取消置顶评论失败', \app\api\ApiException::CODE_SYSTEM_ERROR, null, $exception);
        }
    }

    private static function withDeletedParentPlaceholders(array $result, array $params): array
    {
        $cardId = (int) ($params['where']['pid'] ?? 0);
        if ($cardId <= 0 || !isset($result['data']) || !is_array($result['data'])) return $result;
        $loaded = [];
        $parents = [];
        foreach ($result['data'] as $row) {
            $data = is_object($row) && method_exists($row, 'toArray') ? $row->toArray() : (array) $row;
            $id = (int) ($data['id'] ?? 0);
            $parentId = (int) ($data['parent_id'] ?? 0);
            if ($id > 0) $loaded[$id] = true;
            if ($parentId > 0) $parents[$parentId] = true;
        }
        $missing = array_values(array_diff(array_keys($parents), array_keys($loaded)));
        if ($missing === []) return $result;
        $deleted = Db::table('comments')
            ->whereIn('id', $missing)
            ->where('aid', 1)
            ->where('pid', $cardId)
            ->whereNotNull('deleted_at')
            ->select()
            ->toArray();
        foreach ($deleted as $row) {
            $result['data'][] = [
                'id' => (int) $row['id'],
                'aid' => 1,
                'pid' => $cardId,
                'parent_id' => (int) ($row['parent_id'] ?? 0),
                'user_id' => 0,
                'content' => '该评论已删除',
                'status' => 0,
                'created_at' => (string) ($row['created_at'] ?? ''),
                'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : null,
                'deleted' => true,
            ];
        }
        return $result;
    }

    private static function withUsername(array $comment): array
    {
        $result = self::withUsernames(['data' => [$comment]]);
        return $result['data'][0] ?? $comment;
    }

    private static function withUsernames(array $result): array
    {
        if (!isset($result['data']) || !is_array($result['data'])) return $result;
        $rows = array_map(static fn($row): array => is_object($row) && method_exists($row, 'toArray') ? $row->toArray() : (array) $row, $result['data']);
        $cardIds = array_values(array_unique(array_filter(array_map(
            static fn(array $row): int => (int) ($row['pid'] ?? 0),
            $rows
        ), static fn(int $id): bool => $id > 0)));
        $pinned = [];
        if ($cardIds !== []) {
            foreach (Db::table('cards')->whereIn('id', $cardIds)->field('id,pinned_comment_id')->select()->toArray() as $card) {
                $pinId = (int) ($card['pinned_comment_id'] ?? 0);
                if ($pinId > 0) $pinned[$pinId] = (int) $card['id'];
            }
        }
        $rows = array_map(static function (array $row) use ($pinned): array {
            $row['is_pinned'] = ($pinned[(int) ($row['id'] ?? 0)] ?? 0) === (int) ($row['pid'] ?? -1)
                && (int) ($row['aid'] ?? 0) === 1 && (int) ($row['parent_id'] ?? 0) === 0
                && (int) ($row['status'] ?? -1) === 0 && empty($row['deleted_at']);
            return $row;
        }, $rows);
        $mentions = class_exists(Mentions::class) ? Mentions::projectionsForResources('comment', $rows) : [];
        $rows = array_map(static function (array $row) use ($mentions): array {
            $row['mentions'] = $mentions[(int) ($row['id'] ?? 0)] ?? [];
            return $row;
        }, $rows);
        $userIds = array_values(array_unique(array_filter(array_map(
            static fn(array $row): int => (int) ($row['user_id'] ?? 0),
            $rows
        ), static fn(int $id): bool => $id > 0)));
        if ($userIds === []) {
            $result['data'] = $rows;
            return $result;
        }

        $users = Db::table('users')
            ->whereIn('id', $userIds)
            ->where('status', 0)
            ->whereNull('deleted_at')
            ->field('id,uid,username')
            ->select()
            ->toArray();
        $usernames = [];
        $uids = [];
        foreach ($users as $user) {
            $username = trim((string) ($user['username'] ?? ''));
            if ($username !== '') $usernames[(int) $user['id']] = $username;
            if (!empty($user['uid'])) $uids[(int) $user['id']] = (string) $user['uid'];
        }
        $result['data'] = array_map(static function (array $row) use ($usernames, $uids): array {
            $userId = (int) ($row['user_id'] ?? 0);
            if (isset($usernames[$userId])) $row['username'] = $usernames[$userId];
            $row['uid'] = $uids[$userId] ?? null;
            return $row;
        }, $rows);
        return $result;
    }

    /**
     * 删除评论
     *
     * @param array $ids
     * @param int   $uid
     * @param array $caps
     */
    public static function deleteComments(
        array $ids,
        int $uid,
        GrantSet $grants,
        string $ipAddress = '',
        bool $auditAdminMutation = false
    ): void
    {
        if (empty($ids)) {
            throw \app\api\ApiException::badRequest('未指定要删除的评论');
        }

        Db::startTrans();
        try {
            $before = $auditAdminMutation ? self::auditRows($ids, false, true) : [];
            CommentPolicy::assertCanBatch($grants, 'delete', $ids, $uid);
            CommentsModel::destroy($ids);
            self::clearPinsForComments($ids);
            if ($auditAdminMutation) {
                app(AdminMutationAudit::class)->recordChanges(
                    $uid,
                    'admin.comment.delete',
                    'comment',
                    $before,
                    self::auditRows($ids, true),
                    $ipAddress
                );
            }
            Db::commit();
        } catch (\Throwable $exception) {
            Db::rollback();
            throw $exception;
        }
    }

    /**
     * 批量操作
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

        $opCaps = [
            'approve' => 'comments.moderate',
            'ban'     => 'comments.moderate',
            'hide'    => 'comments.moderate',
            'delete'  => 'comments.delete',
            'trash'   => 'comments.delete',
            'restore' => 'comments.delete',
            'hard_delete' => 'comments.delete',
        ];

        $cap = $opCaps[$method] ?? null;
        if (!$cap) {
            throw \app\api\ApiException::badRequest('不支持的操作');
        }

        CommentPolicy::assertCanBatch($grants, substr($cap, strlen('comments.')), $ids, $uid, in_array($method, ['restore', 'hard_delete'], true));

        if ($method === 'approve') {
            self::approveComments($ids, $uid, $ipAddress, $auditAdminMutation);
            return;
        }

        Db::startTrans();
        try {
            $before = $auditAdminMutation ? self::auditRows($ids, true, true) : [];
            match ($method) {
                'ban' => BulkFieldToggle::toggle(CommentsModel::class, 'status', $ids, [0, 1], [2, 3]),
                'hide' => BulkFieldToggle::toggle(CommentsModel::class, 'status', $ids, [0, 2], [1, 3]),
                'delete', 'trash' => CommentsModel::destroy($ids),
                'restore' => CommentsModel::withTrashed()->onlyTrashed()->whereIn('id', $ids)->update(['deleted_at' => null]),
                'hard_delete' => Db::table('comments')->whereIn('id', $ids)->delete(),
            };
            if (in_array($method, ['ban', 'hide', 'delete', 'trash', 'hard_delete'], true)) {
                self::clearPinsForComments($ids);
            }
            if ($auditAdminMutation) {
                app(AdminMutationAudit::class)->recordChanges(
                    $uid,
                    'admin.comment.batch.' . $method,
                    'comment',
                    $before,
                    self::auditRows($ids, true),
                    $ipAddress
                );
            }
            Db::commit();
        } catch (\Throwable $exception) {
            Db::rollback();
            throw $exception;
        }
    }

    private static function approveComments(
        array $ids,
        int $actorId = 0,
        string $ipAddress = '',
        bool $auditAdminMutation = false
    ): void
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids, SORT_NUMERIC);
        Db::startTrans();
        try {
            $beforeAudit = $auditAdminMutation ? self::auditRows($ids, true, true) : [];
            $comments = CommentsModel::whereIn('id', $ids)
                ->where('status', 3)
                ->order('id', 'asc')
                ->lock(true)
                ->select()
                ->toArray();
            if ($comments === []) {
                Db::commit();
                return;
            }

            $cardIds = array_values(array_unique(array_map(static fn(array $row): int => (int) $row['pid'], $comments)));
            $cards = CardsModel::whereIn('id', $cardIds)->where('status', 0)->field('id,user_id')->select()->toArray();
            $cardsById = [];
            foreach ($cards as $card) $cardsById[(int) $card['id']] = $card;

            $parentIds = array_values(array_unique(array_filter(array_map(
                static fn(array $row): int => (int) ($row['parent_id'] ?? 0),
                $comments
            ), static fn(int $id): bool => $id > 0)));
            $parentsById = [];
            if ($parentIds !== []) {
                foreach (CommentsModel::whereIn('id', $parentIds)
                    ->where('status', 0)
                    ->field('id,aid,pid,user_id')
                    ->select()
                    ->toArray() as $parent) {
                    $parentsById[(int) $parent['id']] = $parent;
                }
            }

            $publishable = [];
            $contexts = [];
            foreach ($comments as $comment) {
                $cardId = (int) $comment['pid'];
                $parentId = (int) ($comment['parent_id'] ?? 0);
                $card = $cardsById[$cardId] ?? null;
                $parent = $parentId > 0 ? ($parentsById[$parentId] ?? null) : null;
                if ((int) ($comment['aid'] ?? 0) !== 1
                    || $card === null || (int) ($card['user_id'] ?? 0) < 1
                    || (int) ($comment['user_id'] ?? 0) < 1
                    || ($parentId > 0 && (
                        $parent === null
                        || (int) ($parent['aid'] ?? 0) !== 1
                        || (int) ($parent['pid'] ?? 0) !== $cardId
                        || (int) ($parent['user_id'] ?? 0) < 1
                    ))) continue;

                $commentId = (int) $comment['id'];
                $publishable[] = $comment;
                $contexts[$commentId] = ['card' => $card, 'parent' => $parent];
                // A parent included in this same sorted approval batch becomes
                // available to its later replies only after it is publishable.
                $parentsById[$commentId] = $comment;
            }

            if ($publishable === []) {
                Db::commit();
                return;
            }

            $approvedIds = array_map(static fn(array $row): int => (int) $row['id'], $publishable);
            CommentsModel::whereIn('id', $approvedIds)->where('status', 3)->update(['status' => 0]);

            $approvedCounts = [];
            foreach ($publishable as $comment) {
                $cardId = (int) $comment['pid'];
                $approvedCounts[$cardId] = ($approvedCounts[$cardId] ?? 0) + 1;
            }
            foreach ($approvedCounts as $cardId => $count) {
                CardsModel::where('id', $cardId)->where('status', 0)->inc('comments', $count)->update();
            }

            foreach ($publishable as $comment) {
                $commentId = (int) $comment['id'];
                $cardId = (int) $comment['pid'];
                $context = $contexts[$commentId];
                $card = $context['card'];
                $parent = $context['parent'];
                self::publishApprovalNotice((int) $comment['user_id'], $cardId, $commentId);
                $mentions = Mentions::capture(
                    'comment',
                    $commentId,
                    $cardId,
                    $commentId,
                    (int) $comment['user_id'],
                    [(string) ($comment['content'] ?? '')]
                );
                self::publishComment(
                    $comment,
                    (int) $card['user_id'],
                    $parent !== null ? (int) $parent['user_id'] : null,
                    $mentions
                );
            }
            if ($auditAdminMutation) {
                app(AdminMutationAudit::class)->recordChanges(
                    $actorId,
                    'admin.comment.batch.approve',
                    'comment',
                    $beforeAudit,
                    self::auditRows($ids, true),
                    $ipAddress
                );
            }
            Db::commit();
        } catch (\Throwable $exception) {
            Db::rollback();
            throw $exception;
        }
    }

    private static function publishApprovalNotice(int $authorId, int $cardId, int $commentId): void
    {
        app(SystemNoticeIssuer::class)->issue(
            $authorId,
            'moderation.comment.' . $commentId . '.approved',
            SystemNotice::MODERATION,
            '你的评论已通过审核。',
            '/post/' . $cardId . '/thread/' . $commentId,
            'success'
        );
    }

    /** @return array<int,array<string,mixed>> */
    private static function auditRows(array $ids, bool $includeDeleted = false, bool $lock = false): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if ($ids === []) return [];
        $query = Db::table('comments')->whereIn('id', $ids)->order('id', 'asc');
        if (!$includeDeleted) $query->whereNull('deleted_at');
        if ($lock) $query->lock(true);
        $result = [];
        foreach ($query->select()->toArray() as $row) $result[(int) $row['id']] = $row;
        return $result;
    }

    private static function clearPinsForComments(array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if ($ids !== []) {
            CardsModel::whereIn('pinned_comment_id', $ids)->update(['pinned_comment_id' => null]);
        }
    }

    private static function pinAuditRow(int $cardId, bool $lock = false): array
    {
        $query = CardsModel::where('id', $cardId)->field('id,pinned_comment_id');
        if ($lock) $query->lock(true);
        $row = $query->find();
        if ($row === null || (int) ($row->pinned_comment_id ?? 0) < 1) return [];
        return ['card_id' => $cardId, 'comment_id' => (int) $row->pinned_comment_id];
    }

    private static function publishComment(
        array $comment,
        int $cardAuthorId,
        ?int $parentAuthorId,
        array $mentions
    ): void {
        $commentId = (int) ($comment['id'] ?? 0);
        $cardId = (int) ($comment['pid'] ?? 0);
        $parentId = (int) ($comment['parent_id'] ?? 0);
        $actorId = (int) ($comment['user_id'] ?? 0);
        app(DomainEventBus::class)->publish(
            'content.comment.published',
            'comment',
            (string) $commentId,
            [
                'actor_id' => $actorId,
                'card_id' => $cardId,
                'comment_id' => $commentId,
                'parent_comment_id' => $parentId > 0 ? $parentId : null,
                'card_author_id' => $cardAuthorId,
                'parent_author_id' => $parentId > 0 ? $parentAuthorId : null,
                'mentions' => array_values($mentions),
            ],
            $actorId
        );
    }
}
