<?php

namespace app\modules\Platform\Social\Contract;

interface SocialGraphRepository
{
    public function profileByHandle(string $handle): ?array;
    public function userExists(int $userId): bool;

    /** 返回 true 表示本次新建关系，false 表示关系已存在。 */
    public function follow(int $actorId, int $targetId): bool;

    /** 返回 true 表示本次删除关系，false 表示关系不存在。 */
    public function unfollow(int $actorId, int $targetId): bool;

    public function isFollowing(int $actorId, int $targetId): bool;

    /**
     * @return array{items: array<int, array<string, mixed>>, next_cursor: ?string, has_more: bool}
     */
    public function listFollowers(int $userId, ?string $cursor, int $limit, array $excludedUserIds = []): array;

    /**
     * @return array{items: array<int, array<string, mixed>>, next_cursor: ?string, has_more: bool}
     */
    public function listFollowing(int $userId, ?string $cursor, int $limit, array $excludedUserIds = []): array;

    /** @return array{followers: int, following: int} */
    public function counts(int $userId): array;
}
