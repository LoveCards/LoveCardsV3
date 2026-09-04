<?php

namespace app\modules\Platform\Social\Public;

use app\modules\Platform\Social\Application\FollowUser;
use app\modules\Platform\Social\Application\ListRelations;
use app\modules\Platform\Social\Contract\SocialGraphRepository;
use app\modules\Platform\Eventing\Public\DomainEventBus;
use app\shared\Transaction\TransactionManager;
use app\modules\Platform\Safety\Contract\SafetyRepository;
use app\modules\Platform\User\Public\ProfileCover;
use app\modules\Platform\User\Public\UserProfileStats;
use app\api\ApiException;

final class Social
{
    public static function profile(string $handle, int $viewerId = 0): ?array
    {
        $repository = app(SocialGraphRepository::class);
        $profile = $repository->profileByHandle($handle);
        if ($profile === null) return null;
        $targetId = (int) $profile['id'];
        $safety = app(SafetyRepository::class);
        if ($viewerId > 0 && $viewerId !== $targetId && $safety->isBlockedEitherDirection($viewerId, $targetId)) {
            throw ApiException::forbidden('当前无法访问该用户', ApiException::CODE_PROFILE_RESTRICTED, ['reason_code' => 'blocked_relationship']);
        }
        $preferences = $safety->preferences($targetId)->toArray();
        $isSelf = $viewerId === $targetId;
        $isFollower = $viewerId > 0 && $repository->isFollowing($viewerId, $targetId);
        $visible = $isSelf
            || $preferences['profile_visibility'] === 'public'
            || ($preferences['profile_visibility'] === 'followers' && $isFollower);
        if (!$visible) throw ApiException::forbidden('该用户主页不可见', ApiException::CODE_PROFILE_RESTRICTED);
        if (!$isSelf) {
            $profile['gender'] = self::fieldVisible($preferences['gender_visibility'] ?? 'private', $isFollower)
                ? ($profile['gender'] ?? null) : null;
            $profile['birth_date'] = self::fieldVisible($preferences['birth_date_visibility'] ?? 'private', $isFollower)
                ? ($profile['birth_date'] ?? null) : null;
        }
        $profile['can_view_relations'] = $isSelf || (bool) $preferences['show_relations'];
        $profile['cover_url'] = ProfileCover::url($targetId, true);
        $profile['stats'] = ['received_likes' => UserProfileStats::receivedLikes($targetId)];
        return $profile;
    }

    public static function profileByUid(string $uid, int $viewerId = 0): ?array
    {
        $repository = app(SocialGraphRepository::class);
        $profile = method_exists($repository, 'profileByUid') ? $repository->profileByUid($uid) : null;
        if ($profile === null) return null;
        $targetId = (int) $profile['id'];
        $safety = app(SafetyRepository::class);
        if ($viewerId > 0 && $viewerId !== $targetId && $safety->isBlockedEitherDirection($viewerId, $targetId)) {
            throw ApiException::forbidden('当前无法访问该用户', ApiException::CODE_PROFILE_RESTRICTED, ['reason_code' => 'blocked_relationship']);
        }
        $preferences = $safety->preferences($targetId)->toArray();
        $isSelf = $viewerId === $targetId;
        $isFollower = $viewerId > 0 && $repository->isFollowing($viewerId, $targetId);
        $visible = $isSelf || $preferences['profile_visibility'] === 'public' || ($preferences['profile_visibility'] === 'followers' && $isFollower);
        if (!$visible) throw ApiException::forbidden('该用户主页不可见', ApiException::CODE_PROFILE_RESTRICTED);
        if (!$isSelf) {
            $profile['gender'] = self::fieldVisible($preferences['gender_visibility'] ?? 'private', $isFollower) ? ($profile['gender'] ?? null) : null;
            $profile['birth_date'] = self::fieldVisible($preferences['birth_date_visibility'] ?? 'private', $isFollower) ? ($profile['birth_date'] ?? null) : null;
        }
        $profile['can_view_relations'] = $isSelf || (bool) $preferences['show_relations'];
        $profile['cover_url'] = ProfileCover::url($targetId, true);
        $profile['stats'] = ['received_likes' => UserProfileStats::receivedLikes($targetId)];
        return $profile;
    }
    public static function follow(int $actorId, int $targetId): bool
    {
        return app(TransactionManager::class)->run(function () use ($actorId, $targetId): bool {
            $created = (new FollowUser(app(SocialGraphRepository::class), app(SafetyRepository::class)))->follow($actorId, $targetId);
            if ($created) {
                app(DomainEventBus::class)->publish('social.followed', 'user', (string) $targetId, ['actor_id' => $actorId], $actorId);
            }
            return $created;
        });
    }

    public static function unfollow(int $actorId, int $targetId): bool
    {
        return (new FollowUser(app(SocialGraphRepository::class)))->unfollow($actorId, $targetId);
    }

    public static function isFollowing(int $actorId, int $targetId): bool
    {
        return app(SocialGraphRepository::class)->isFollowing($actorId, $targetId);
    }

    public static function followers(int $userId, int $viewerId, ?string $cursor = null, int $limit = 20): array
    {
        self::assertRelationsVisible($userId, $viewerId);
        return (new ListRelations(app(SocialGraphRepository::class)))->followers($userId, $cursor, $limit, app(SafetyRepository::class)->blockedUserIds($viewerId));
    }

    public static function following(int $userId, int $viewerId, ?string $cursor = null, int $limit = 20): array
    {
        self::assertRelationsVisible($userId, $viewerId);
        return (new ListRelations(app(SocialGraphRepository::class)))->following($userId, $cursor, $limit, app(SafetyRepository::class)->blockedUserIds($viewerId));
    }

    public static function counts(int $userId): array
    {
        return app(SocialGraphRepository::class)->counts($userId);
    }

    /** @return array{following:bool,followed_by:bool,blocked_by_me:bool,counts:array{followers:int,following:int}} */
    public static function relation(int $viewerId, int $targetId): array
    {
        $repository = app(SocialGraphRepository::class);
        if (!$repository->userExists($targetId)) throw ApiException::notFound('用户不存在');
        $safety = app(SafetyRepository::class);
        return [
            'following' => $repository->isFollowing($viewerId, $targetId),
            'followed_by' => $repository->isFollowing($targetId, $viewerId),
            'blocked_by_me' => $safety->hasRelation($viewerId, $targetId, 'block'),
            'counts' => $repository->counts($targetId),
        ];
    }

    private static function fieldVisible(string $visibility, bool $isFollower): bool
    {
        return $visibility === 'public' || ($visibility === 'followers' && $isFollower);
    }

    private static function assertRelationsVisible(int $targetId, int $viewerId): void
    {
        $repository = app(SocialGraphRepository::class);
        if (!$repository->userExists($targetId)) throw ApiException::notFound('用户不存在');
        if ($viewerId === $targetId) return;
        $safety = app(SafetyRepository::class);
        if ($safety->isBlockedEitherDirection($viewerId, $targetId)) {
            throw ApiException::forbidden('当前无法访问该用户关系', ApiException::CODE_PROFILE_RESTRICTED, ['reason_code' => 'blocked_relationship']);
        }
        $preferences = $safety->preferences($targetId)->toArray();
        if (!(bool) $preferences['show_relations']) throw ApiException::forbidden('该用户未公开关注关系', ApiException::CODE_PROFILE_RESTRICTED);
        $visible = $preferences['profile_visibility'] === 'public'
            || ($preferences['profile_visibility'] === 'followers' && $repository->isFollowing($viewerId, $targetId));
        if (!$visible) throw ApiException::forbidden('该用户主页不可见', ApiException::CODE_PROFILE_RESTRICTED);
    }
}
