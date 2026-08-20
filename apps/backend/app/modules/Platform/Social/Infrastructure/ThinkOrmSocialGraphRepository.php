<?php

namespace app\modules\Platform\Social\Infrastructure;

use app\modules\Platform\Social\Contract\SocialGraphRepository;
use think\facade\Db;

final class ThinkOrmSocialGraphRepository implements SocialGraphRepository
{
    public function profileByHandle(string $handle): ?array
    {
        $row = Db::table('users')->where('username', trim($handle))->where('status', 0)->whereNull('deleted_at')->field('id,number,username,avatar')->find();
        return $row ? array_merge($row, $this->counts((int) $row['id'])) : null;
    }
    public function userExists(int $userId): bool
    {
        return Db::table('users')->where('id', $userId)->where('status', 0)->whereNull('deleted_at')->value('id') !== null;
    }

    public function follow(int $actorId, int $targetId): bool
    {
        if ($this->isFollowing($actorId, $targetId)) return false;

        try {
            return Db::table('user_follows')->insert([
                'follower_id' => $actorId,
                'followee_id' => $targetId,
                'created_at' => date('Y-m-d H:i:s'),
            ]) === 1;
        } catch (\Throwable $exception) {
            if ($this->isFollowing($actorId, $targetId)) return false;
            throw $exception;
        }
    }

    public function unfollow(int $actorId, int $targetId): bool
    {
        return Db::table('user_follows')
            ->where('follower_id', $actorId)
            ->where('followee_id', $targetId)
            ->delete() > 0;
    }

    public function isFollowing(int $actorId, int $targetId): bool
    {
        return Db::table('user_follows')
            ->where('follower_id', $actorId)
            ->where('followee_id', $targetId)
            ->find() !== null;
    }

    public function listFollowers(int $userId, ?string $cursor, int $limit): array
    {
        return $this->listRelation('followee_id', 'follower_id', $userId, $cursor, $limit);
    }

    public function listFollowing(int $userId, ?string $cursor, int $limit): array
    {
        return $this->listRelation('follower_id', 'followee_id', $userId, $cursor, $limit);
    }

    public function counts(int $userId): array
    {
        return [
            'followers' => (int) Db::table('user_follows')->where('followee_id', $userId)->count(),
            'following' => (int) Db::table('user_follows')->where('follower_id', $userId)->count(),
        ];
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, next_cursor: ?string, has_more: bool}
     */
    private function listRelation(string $ownerColumn, string $relatedColumn, int $userId, ?string $cursor, int $limit): array
    {
        $query = Db::table('user_follows')->alias('f')
            ->join('users u', "u.id = f.{$relatedColumn}")
            ->where("f.{$ownerColumn}", $userId)
            ->where('u.status', 0)
            ->whereNull('u.deleted_at')
            ->field('u.id,u.number,u.username,u.avatar')
            ->order("f.{$relatedColumn}", 'desc')
            ->limit($limit + 1);

        if ($cursor !== null && ctype_digit($cursor)) {
            $query->where("f.{$relatedColumn}", '<', (int) $cursor);
        }

        $rows = $query->select()->toArray();
        $hasMore = count($rows) > $limit;
        if ($hasMore) array_pop($rows);
        $next = $hasMore && $rows !== [] ? (string) end($rows)['id'] : null;
        return ['items' => $rows, 'next_cursor' => $next, 'has_more' => $hasMore];
    }
}
