<?php

namespace app\modules\Platform\Social\Infrastructure;

use app\modules\Platform\Social\Contract\SocialGraphRepository;
use think\facade\Db;

final class ThinkOrmSocialGraphRepository implements SocialGraphRepository
{
    public function profileByHandle(string $handle): ?array
    {
        $handle = trim($handle);
        $query = Db::table('users')->where('status', 0)->whereNull('deleted_at');
        $query->where(function ($lookup) use ($handle): void {
            $lookup->where('username', $handle);
        });
        $row = $query->field('id,uid,username,avatar,bio,location,gender,birth_date')->find();
        return $row ? array_merge($row, $this->counts((int) $row['id'])) : null;
    }

    public function profileByUid(string $uid): ?array
    {
        if (!preg_match('/^[0-9]{12}$/', $uid)) return null;
        $row = Db::table('users')->where('uid', $uid)->where('status', 0)->whereNull('deleted_at')
            ->field('id,uid,username,avatar,bio,location,gender,birth_date')->find();
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

    public function listFollowers(int $userId, ?string $cursor, int $limit, array $excludedUserIds = []): array
    {
        return $this->listRelation('followee_id', 'follower_id', $userId, $cursor, $limit, $excludedUserIds);
    }

    public function listFollowing(int $userId, ?string $cursor, int $limit, array $excludedUserIds = []): array
    {
        return $this->listRelation('follower_id', 'followee_id', $userId, $cursor, $limit, $excludedUserIds);
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
    private function listRelation(string $ownerColumn, string $relatedColumn, int $userId, ?string $cursor, int $limit, array $excludedUserIds): array
    {
        $query = Db::table('user_follows')->alias('f')
            ->join('users u', "u.id = f.{$relatedColumn}")
            ->where("f.{$ownerColumn}", $userId)
            ->where('u.status', 0)
            ->whereNull('u.deleted_at')
            ->field('u.id,u.uid,u.number,u.username,u.avatar')
            ->order("f.{$relatedColumn}", 'desc')
            ->limit($limit + 1);

        $excludedUserIds = array_values(array_unique(array_filter(array_map('intval', $excludedUserIds), static fn(int $id): bool => $id > 0)));
        if ($excludedUserIds !== []) $query->whereNotIn("f.{$relatedColumn}", $excludedUserIds);

        if ($cursor !== null) {
            $decodedCursor = base64_decode($cursor, true);
            if ($decodedCursor !== false && ctype_digit($decodedCursor)) $query->where("f.{$relatedColumn}", '<', (int) $decodedCursor);
        }

        $rows = $query->select()->toArray();
        $hasMore = count($rows) > $limit;
        if ($hasMore) array_pop($rows);
        $next = $hasMore && $rows !== [] ? base64_encode((string) end($rows)['id']) : null;
        foreach ($rows as &$row) unset($row['id'], $row['number']);
        unset($row);
        return ['items' => $rows, 'next_cursor' => $next, 'has_more' => $hasMore];
    }
}
