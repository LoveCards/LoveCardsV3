<?php

namespace app\modules\Content\Tags\Service;

use think\facade\Db;

use app\modules\Content\Infrastructure\Persistence\Model\Tags as TagsModel;

use app\shared\Infrastructure\Persistence\BulkFieldToggle;
use app\shared\Infrastructure\Persistence\ModelQuery;
use app\shared\Authorization\GrantSet;
use app\shared\Authorization\Scope;
use app\modules\Content\Tags\Service\Policy\TagPolicy;
use app\modules\Platform\Config\Public\Config as ConfigService;
use app\modules\Platform\Notifications\Contract\SystemNoticeIssuer;
use app\modules\Platform\Notifications\Domain\SystemNotice;
use app\shared\Observability\AdminMutationAudit;

class Tags
{
    public static function noPaginateIndex($params): array
    {
        $params['search_default_key'] = 'name';
        $result = ModelQuery::make(TagsModel::class)->getNoPaginate($params);
        return $result->toArray();
    }

    public static function get(int $id, bool $publicView = false): array
    {
        $query = TagsModel::where('id', $id);
        if ($publicView) {
            // 公开详情只暴露已发布标签；待审核/封禁/隐藏标签对外等同不存在。
            $query->where('status', 0);
        }
        $result = $query->findOrEmpty();
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

    public static function createTag($params, ?GrantSet $grants = null): array
    {
        $name = trim(ltrim(trim((string) ($params['name'] ?? '')), '#'));
        if ($name === '') {
            throw \app\api\ApiException::badRequest('标签名称不能为空');
        }
        $id = self::ensureTag($name, (int) ($params['user_id'] ?? 0), $grants);
        return TagsModel::where('id', $id)->findOrEmpty()->toArray();
    }

    /**
     * 按名称取得或创建标签（隐式与显式两条创建通道共用的唯一入口）。
     *
     * 规则（社区规则页「标签」组）：
     * - 已有正常标签（status=0）按名复用，不受开关影响；
     * - tags.allow_user_create 关闭时，非特权用户创建新标签直接报错；
     * - tags.review_new_tags 开启时，非特权用户的新标签落 status=3（待审核），
     *   同名待审标签复用同一条记录，等待管理员在后台标签页「审核/取消」放行；
     * - 持有 admin.access（global）的角色豁免两个开关，维持直接发布的行为。
     */
    public static function ensureTag(string $rawName, int $uid, ?GrantSet $grants = null): int
    {
        $name = trim(ltrim(trim($rawName), '#'));
        if ($name === '') {
            throw \app\api\ApiException::badRequest('标签名称不能为空');
        }

        $existing = TagsModel::where('aid', 1)->where('status', 0)->where('name', $name)->find();
        if ($existing !== null && !$existing->isEmpty()) {
            return (int) $existing->id;
        }

        $privileged = $grants !== null && $grants->allows('admin.access', Scope::GLOBAL);

        if (!$privileged && !self::boolConfig('tags.allow_user_create', true)) {
            throw \app\api\ApiException::badRequest('暂不支持创建新标签，请从已有标签中选择');
        }

        if (!$privileged && self::boolConfig('tags.review_new_tags', false)) {
            $pending = TagsModel::where('aid', 1)->where('status', 3)->where('name', $name)->find();
            if ($pending !== null && !$pending->isEmpty()) {
                return (int) $pending->id;
            }
            return (int) TagsModel::create(['aid' => 1, 'user_id' => $uid, 'name' => $name, 'status' => 3])->id;
        }

        return (int) TagsModel::create(['aid' => 1, 'user_id' => $uid, 'name' => $name, 'status' => 0])->id;
    }

    /**
     * 标签创建规则（供主题等公开消费方读取，键名与配置组一致）。
     */
    public static function creationPolicy(): array
    {
        return [
            'allow_user_create' => self::boolConfig('tags.allow_user_create', true),
            'review_new_tags'   => self::boolConfig('tags.review_new_tags', false),
        ];
    }

    private static function boolConfig(string $key, bool $default): bool
    {
        $value = ConfigService::get($key, $default);
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (bool) (int) $value;
        }
        return $default;
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

            // 审核结果通知判定（NOTIFICATIONS.md §3.2）：仅对真实状态翻转的标签通知创建者；
            // 「待审核→公开」发通过通知，「公开→待审核」发复核通知；进入审核队列本身不通知。
            // 封禁/隐藏等治理动作的通知属于另一条通知线，另行排期补齐。
            $noticeTargets = [];
            if ($method === 'approve') {
                foreach (TagsModel::where('aid', 1)->whereIn('id', $ids)->select() as $row) {
                    if ((int) $row->user_id <= 0) {
                        continue;
                    }
                    if ((int) $row->status === 3) {
                        $noticeTargets[] = ['user_id' => (int) $row->user_id, 'id' => (int) $row->id, 'name' => (string) $row->name, 'kind' => 'approved'];
                    } elseif ((int) $row->status === 0) {
                        $noticeTargets[] = ['user_id' => (int) $row->user_id, 'id' => (int) $row->id, 'name' => (string) $row->name, 'kind' => 'rereview'];
                    }
                }
            }

            match ($method) {
                'approve' => BulkFieldToggle::toggle(TagsModel::class, 'status', $ids, [0, 3], [1, 2]),
                'ban' => BulkFieldToggle::toggle(TagsModel::class, 'status', $ids, [0, 1], [2, 3]),
                'hide' => BulkFieldToggle::toggle(TagsModel::class, 'status', $ids, [0, 2], [1, 3]),
                'delete', 'trash' => TagsModel::destroy($ids),
                'restore' => TagsModel::withTrashed()->onlyTrashed()->whereIn('id', $ids)->update(['deleted_at' => null]),
                'hard_delete' => self::hardDeleteTagsWithMap($ids),
            };

            $cycle = uniqid('tagbatch', true);
            foreach ($noticeTargets as $target) {
                app(SystemNoticeIssuer::class)->issue(
                    $target['user_id'],
                    'moderation.tag.' . $target['id'] . '.' . $target['kind'] . '.' . $cycle,
                    SystemNotice::MODERATION,
                    $target['kind'] === 'approved'
                        ? '标签「' . $target['name'] . '」已通过审核并公开发布。'
                        : '标签「' . $target['name'] . '」被转入人工复核，暂时不再公开展示。',
                    null,
                    $target['kind'] === 'approved' ? 'success' : 'warning',
                    $uid
                );
            }

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
        // 查询级 delete() 对软删模型 TagsMap 为空操作（实测），显式写 deleted_at 才真正解除映射。
        Db::table('tags_map')
            ->where('tag_id', 'in', $ids)
            ->whereNull('deleted_at')
            ->update(['deleted_at' => date('Y-m-d H:i:s')]);
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
