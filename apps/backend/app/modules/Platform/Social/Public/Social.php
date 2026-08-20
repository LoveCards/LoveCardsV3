<?php

namespace app\modules\Platform\Social\Public;

use app\modules\Platform\Social\Application\FollowUser;
use app\modules\Platform\Social\Application\ListRelations;
use app\modules\Platform\Social\Contract\SocialGraphRepository;

final class Social
{
    public static function profile(string $handle): ?array { return app(SocialGraphRepository::class)->profileByHandle($handle); }
    public static function follow(int $actorId, int $targetId): bool
    {
        $created = (new FollowUser(app(SocialGraphRepository::class)))->follow($actorId, $targetId);
        if ($created) app(\app\modules\Platform\Eventing\Public\DomainEventBus::class)->publish('social.followed', 'user', (string) $targetId, ['actor_id' => $actorId], $actorId);
        return $created;
    }

    public static function unfollow(int $actorId, int $targetId): bool
    {
        $removed = (new FollowUser(app(SocialGraphRepository::class)))->unfollow($actorId, $targetId);
        if ($removed) app(\app\modules\Platform\Eventing\Public\DomainEventBus::class)->publish('social.unfollowed', 'user', (string) $targetId, ['actor_id' => $actorId], $actorId);
        return $removed;
    }

    public static function isFollowing(int $actorId, int $targetId): bool
    {
        return app(SocialGraphRepository::class)->isFollowing($actorId, $targetId);
    }

    public static function followers(int $userId, ?string $cursor = null, int $limit = 20): array
    {
        return (new ListRelations(app(SocialGraphRepository::class)))->followers($userId, $cursor, $limit);
    }

    public static function following(int $userId, ?string $cursor = null, int $limit = 20): array
    {
        return (new ListRelations(app(SocialGraphRepository::class)))->following($userId, $cursor, $limit);
    }

    public static function counts(int $userId): array
    {
        return app(SocialGraphRepository::class)->counts($userId);
    }
}
