<?php

namespace app\modules\Content\Comments\Service;

use app\modules\Platform\Config\Public\Config as ConfigService;

use think\facade\Db;

use app\modules\Content\Infrastructure\Persistence\Model\Comments as CommentsModel;
use app\modules\Content\Infrastructure\Persistence\Model\Cards as CardsModel;

use app\shared\Infrastructure\Persistence\BulkFieldToggle;
use app\shared\Infrastructure\Persistence\ModelQuery;
use app\shared\Authorization\GrantSet;
use app\modules\Content\Comments\Service\Policy\CommentPolicy;

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
     * 更新评论（用户自己的）
     *
     * @param int   $uid
     * @param array $data
     * @param array $caps
     */
    public static function updateComment(array $data, int $uid, GrantSet $grants): void
    {
        Db::startTrans();
        try {
            // 验证能力 + 归属
            CommentPolicy::assertCan($grants, 'update', (int) $data['id'], $uid);

            // 移除敏感字段
            unset($data['user_id']);

            CommentsModel::update($data, ['id' => $data['id']]);

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
        return $result->toArray();
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
        return $canReadAll ? $rows : self::withDeletedParentPlaceholders($rows, $params);
    }

    public static function get(int $id, bool $includeHidden = false): array
    {
        $query = CommentsModel::where('id', $id);
        if (!$includeHidden) $query->where('status', 0);
        $result = $query->findOrEmpty();
        if ($result->isEmpty()) {
            throw \app\api\ApiException::notFound('评论不存在');
        }
        return $result->toArray();
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
            CardsModel::where('id', $id)->where('status', 0)->inc('comments')->update();

            Db::commit();
            return ['data' => $comment];
        } catch (\Throwable $th) {
            Db::rollback();
            if ($th instanceof \app\api\ApiException) throw $th;
            throw \app\api\ApiException::error('创建失败', \app\api\ApiException::CODE_SYSTEM_ERROR, null, $th);
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

    /**
     * 删除评论
     *
     * @param array $ids
     * @param int   $uid
     * @param array $caps
     */
    public static function deleteComments(array $ids, int $uid, GrantSet $grants): void
    {
        if (empty($ids)) {
            throw \app\api\ApiException::badRequest('未指定要删除的评论');
        }

        // 验证能力 + 归属
        CommentPolicy::assertCanBatch($grants, 'delete', $ids, $uid);

        CommentsModel::destroy($ids);
    }

    /**
     * 批量操作
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

        $opCaps = [
            'approve' => 'comments.update',
            'ban'     => 'comments.update',
            'hide'    => 'comments.update',
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

        match ($method) {
            'approve' => BulkFieldToggle::toggle(CommentsModel::class, 'status', $ids, [0, 3], [1, 2]),
            'ban' => BulkFieldToggle::toggle(CommentsModel::class, 'status', $ids, [0, 1], [2, 3]),
            'hide' => BulkFieldToggle::toggle(CommentsModel::class, 'status', $ids, [0, 2], [1, 3]),
            'delete', 'trash' => CommentsModel::destroy($ids),
            'restore' => CommentsModel::withTrashed()->onlyTrashed()->whereIn('id', $ids)->update(['deleted_at' => null]),
            'hard_delete' => Db::table('comments')->whereIn('id', $ids)->delete(),
        };
    }
}
