<?php

namespace app\modules\Platform\Social\Application;

use app\modules\Platform\Social\Contract\SocialGraphRepository;
use app\modules\Platform\Social\Domain\FollowPolicy;
use app\modules\Platform\Safety\Contract\SafetyRepository;
use app\api\ApiException;

final class FollowUser
{
    public function __construct(private SocialGraphRepository $repository, private ?SafetyRepository $safety = null)
    {
    }

    public function follow(int $actorId, int $targetId): bool
    {
        FollowPolicy::assertAllowed($actorId, $targetId);
        if (!$this->repository->userExists($targetId)) {
            throw new \DomainException('用户不存在');
        }
        if ($this->safety?->isBlockedEitherDirection($actorId, $targetId)) {
            throw ApiException::forbidden('屏蔽关系下无法关注用户', ApiException::CODE_PERMISSION_DENIED, ['reason_code' => 'blocked_relationship']);
        }
        return $this->repository->follow($actorId, $targetId);
    }

    public function unfollow(int $actorId, int $targetId): bool
    {
        FollowPolicy::assertAllowed($actorId, $targetId);
        return $this->repository->unfollow($actorId, $targetId);
    }
}
