<?php

namespace app\modules\Content\Tags\Service;

use think\facade\Db;

use app\modules\Content\Infrastructure\Persistence\Model\Tags as TagsModel;
use app\modules\Content\Infrastructure\Persistence\Model\TagsMap as TagsMapModel;

use app\shared\Infrastructure\Persistence\BulkFieldToggle;
use app\shared\Infrastructure\Persistence\ModelQuery;
use app\shared\Authorization\GrantSet;
use app\shared\Authorization\Scope;
use app\modules\Content\Tags\Service\Policy\TagPolicy;
use app\shared\Observability\AdminMutationAudit;

class Tags
{
    public static function noPaginateIndex($params): array
    {
        $params['search_default_key'] = 'name';
        $result = ModelQuery::make(TagsModel::class)->getNoPaginate($params);
        return $result->toArray();
    }

    public static function get(int $id): array
    {
        $result = TagsModel::where('id', $id)->findOrEmpty();
        if ($result->isEmpty()) {
            throw \app\api\ApiException::notFound('标签不存在');
        }
        return $result->toArray();
    }

    public static function Index($params): array
    {
        $params['search_default_key'] = 'name';
        $result = ModelQuery::make(TagsModel::class)->getPaginate($params);
        return $result->toArray();
    }

    public static function listAll(array $params, array $caps = []): array
    {
        $params['search_default_key'] = 'name';
        $canReadAll = in_array('tags.read.all', $caps, true);

        if (!$canReadAll) {
            $params['where']['status'] = 0;
        }

        return ModelQuery::make(TagsModel::class)
            ->trashScope((int) ($params['show_deleted'] ?? 0), $canReadAll)
            ->getPaginate($params)
            ->toArray();
    }

    public static function createTag($params): array
    {
        $params['aid'] = 1;
        $name = trim(ltrim((string) ($params['name'] ?? ''), '#'));
        $existing = TagsModel::where('aid', 1)->where('status', 0)->where('name', $name)->find();
        if ($existing !== null && !$existing->isEmpty()) return $existing->toArray();
        $params['name'] = $name;
        return TagsModel::create($params)->toArray();
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
            'approve' => 'tags.update',
            'ban'     => 'tags.update',
            'hide'    => 'tags.update',
            'delete'  => 'tags.delete',
            'trash'   => 'tags.delete',
            'restore' => 'tags.delete',
            'hard_delete' => 'tags.delete',
        ];

        $cap = $opCaps[$method] ?? null;
        if (!$cap) {
            throw \app\api\ApiException::badRequest('不支持的操作');
        }

        TagPolicy::assertCanBatch($grants, substr($cap, strlen('tags.')), $ids, $uid, in_array($method, ['restore', 'hard_delete'], true));

        Db::startTrans();
        try {
            $before = $auditAdminMutation ? self::auditRows($ids, true, true) : [];
            match ($method) {
                'approve' => BulkFieldToggle::toggle(TagsModel::class, 'status', $ids, [0, 3], [1, 2]),
                'ban' => BulkFieldToggle::toggle(TagsModel::class, 'status', $ids, [0, 1], [2, 3]),
                'hide' => BulkFieldToggle::toggle(TagsModel::class, 'status', $ids, [0, 2], [1, 3]),
                'delete', 'trash' => TagsModel::destroy($ids),
                'restore' => TagsModel::withTrashed()->onlyTrashed()->whereIn('id', $ids)->update(['deleted_at' => null]),
                'hard_delete' => self::hardDeleteTagsWithMap($ids),
            };
            if ($auditAdminMutation) {
                app(AdminMutationAudit::class)->recordChanges(
                    $uid,
                    'admin.tag.batch.' . $method,
                    'tag',
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
     * 删除标签
     *
     * @param array $ids
     * @param int   $uid
     * @param array $caps
     */
    public static function deleteTags(
        array $ids,
        int $uid,
        GrantSet $grants,
        string $ipAddress = '',
        bool $auditAdminMutation = false
    ): void
    {
        if (empty($ids)) {
            throw \app\api\ApiException::badRequest('未指定要删除的标签');
        }

        // 验证能力 + 归属
        TagPolicy::assertCanBatch($grants, 'delete', $ids, $uid);

        Db::startTrans();
        try {
            // Tags are shared references in the card taxonomy. An owner may
            // not delete a tag that is attached to another user's card; doing
            // so would silently remove that user's classification. Admins
            // with ALL scope retain the explicit managed operation.
            if (!$grants->allows('tags.delete', Scope::ALL)) {
                $mappedCardIds = Db::table('tags_map')
                    ->where('aid', 1)
                    ->whereIn('tag_id', $ids)
                    ->column('pid');
                $mappedCardIds = array_values(array_unique(array_filter(array_map('intval', $mappedCardIds), static fn(int $id): bool => $id > 0)));
                if ($mappedCardIds !== []) {
                    $foreignUse = Db::table('cards')
                        ->whereIn('id', $mappedCardIds)
                        ->where('user_id', '<>', $uid)
                        ->where('status', 0)
                        ->whereNull('deleted_at')
                        ->value('id');
                    if ($foreignUse !== null) {
                        throw \app\api\ApiException::conflict('标签仍被其他用户的公开卡片使用，不能删除');
                    }
                }
            }
            $before = $auditAdminMutation ? self::auditRows($ids, false, true) : [];
            self::deleteTagsMap($ids);
            TagsModel::destroy($ids);
            if ($auditAdminMutation) {
                app(AdminMutationAudit::class)->recordChanges(
                    $uid,
                    'admin.tag.delete',
                    'tag',
                    $before,
                    self::auditRows($ids, true),
                    $ipAddress
                );
            }
            Db::commit();
        } catch (\Throwable $th) {
            Db::rollback();
            if ($th instanceof \app\api\ApiException) throw $th;
            throw \app\api\ApiException::error('删除失败', \app\api\ApiException::CODE_SYSTEM_ERROR, null, $th);
        }
    }

    public static function deleteTagsMap(array $ids): void
    {
        TagsMapModel::where('tag_id', 'in', $ids)->delete();
    }

    /**
     * 删除标签及其关联映射
     */
    private static function deleteTagsWithMap(array $ids): void
    {
        Db::startTrans();
        try {
            self::deleteTagsMap($ids);
            TagsModel::destroy($ids);
            Db::commit();
        } catch (\Throwable $th) {
            Db::rollback();
            throw \app\api\ApiException::error('删除失败', \app\api\ApiException::CODE_SYSTEM_ERROR, null, $th);
        }
    }

    public static function listOwn(array $params, int $uid): array
    {
        $params['search_default_key'] = 'name';
        $params['where']['user_id'] = $uid;
        return ModelQuery::make(TagsModel::class)->getPaginate($params)->toArray();
    }

    private static function hardDeleteTagsWithMap(array $ids): void
    {
        Db::table('tags_map')->whereIn('tag_id', $ids)->delete();
        Db::table('tags')->whereIn('id', $ids)->delete();
    }

    /**
     * 更新标签
     *
     * @param array $data
     * @param int   $uid
     * @param array $caps
     */
    public static function updateTag(
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
            $tagId = (int) $data['id'];
            $before = $shouldAudit ? self::auditRows([$tagId], false, true) : [];
            // 验证能力 + 归属
            TagPolicy::assertCan($grants, 'update', (int) $data['id'], $uid);

            // 移除敏感字段
            unset($data['aid'], $data['user_id']);

            TagsModel::update($data);

            if ($shouldAudit) {
                app(AdminMutationAudit::class)->recordChanges(
                    $uid,
                    'admin.tag.update',
                    'tag',
                    $before,
                    self::auditRows([$tagId]),
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

    /** @return array<int,array<string,mixed>> */
    private static function auditRows(array $ids, bool $includeDeleted = false, bool $lock = false): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        if ($ids === []) return [];
        $query = Db::table('tags')->whereIn('id', $ids)->order('id', 'asc');
        if (!$includeDeleted) $query->whereNull('deleted_at');
        if ($lock) $query->lock(true);
        $result = [];
        foreach ($query->select()->toArray() as $row) $result[(int) $row['id']] = $row;
        return $result;
    }
}
