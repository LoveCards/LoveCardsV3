<?php

declare(strict_types=1);

namespace app\modules\Platform\User\Service;

use think\facade\Db;

/** 公开主页搜索；资格过滤必须先于计数和分页。 */
final class PublicUserSearch
{
    public static function search(array $params, int $viewerId = 0): array
    {
        $term = trim((string) ($params['search_value'] ?? ''));
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = min(30, max(1, (int) ($params['list_rows'] ?? 20)));
        if ($term === '') {
            return ['total' => 0, 'per_page' => $perPage, 'current_page' => $page, 'last_page' => 1, 'data' => []];
        }

        $viewer = max(0, $viewerId);
        $guestRole = (int) config('system.system_roles.guest');
        $prefix = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';
        $query = Db::table('users')->alias('u')
            ->where('u.status', 0)
            ->whereNull('u.deleted_at')
            ->whereRaw("(u.username LIKE :public_user_prefix ESCAPE '\\\\' OR u.uid = :public_user_uid)", [
                'public_user_prefix' => $prefix,
                'public_user_uid' => preg_match('/^[0-9]{12}$/D', $term) === 1 ? $term : '',
            ]);

        if ($guestRole > 0) {
            $query->whereRaw("NOT EXISTS (SELECT 1 FROM user_roles search_ur WHERE search_ur.user_id = u.id AND search_ur.role_id = {$guestRole})");
        }
        if ($viewer > 0) {
            $query->whereRaw("NOT EXISTS (SELECT 1 FROM user_safety_relations search_block WHERE search_block.relation = 'block' AND ((search_block.actor_id = {$viewer} AND search_block.subject_id = u.id) OR (search_block.actor_id = u.id AND search_block.subject_id = {$viewer})))");
            $query->whereRaw("(u.id = {$viewer} OR NOT EXISTS (SELECT 1 FROM privacy_preferences search_private WHERE search_private.user_id = u.id AND search_private.profile_visibility <> 'public' AND (search_private.profile_visibility <> 'followers' OR NOT EXISTS (SELECT 1 FROM user_follows search_follow WHERE search_follow.follower_id = {$viewer} AND search_follow.followee_id = u.id))))");
        } else {
            $query->whereRaw("NOT EXISTS (SELECT 1 FROM privacy_preferences search_private WHERE search_private.user_id = u.id AND search_private.profile_visibility <> 'public')");
        }

        return $query->field('u.uid,u.username,u.avatar,u.bio')
            ->orderRaw('CASE WHEN u.uid = :user_rank_uid THEN 0 WHEN u.username = :user_rank_name THEN 1 ELSE 2 END', [
                'user_rank_uid' => preg_match('/^[0-9]{12}$/D', $term) === 1 ? $term : '',
                'user_rank_name' => $term,
            ])
            ->order('u.username', 'asc')->order('u.id', 'asc')
            ->paginate(['list_rows' => $perPage, 'page' => $page])->toArray();
    }
}
