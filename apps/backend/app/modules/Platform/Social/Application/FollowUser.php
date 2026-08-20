<?php

namespace app\modules\Platform\Social\Application;

use app\modules\Platform\Social\Contract\SocialGraphRepository;
use app\modules\Platform\Social\Domain\FollowPolicy;

final class FollowUser
{
    public function __construct(private SocialGraphRepository $repository)
    {
    }

    public function follow(int $actorId, int $targetId): bool
    {
        FollowPolicy::assertAllowed($actorId, $targetId);
        if (!$this->repository->userExists($targetId)) {
            throw new \DomainException('用户不存在');
        }
        return $this->repository->follow($actorId, $targetId);
    }

    public function unfollow(int $actorId, int $targetId): bool
    {
        FollowPolicy::assertAllowed($actorId, $targetId);
        return $this->repository->unfollow($actorId, $targetId);
    }
}
