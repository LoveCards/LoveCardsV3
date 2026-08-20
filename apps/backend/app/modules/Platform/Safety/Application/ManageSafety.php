<?php

namespace app\modules\Platform\Safety\Application;

use app\api\ApiException;
use app\modules\Platform\Safety\Contract\SafetyRepository;
use app\modules\Platform\Safety\Contract\ReportTargetResolver;
use app\modules\Platform\Safety\Domain\PrivacyPreferences;
use app\modules\Platform\Safety\Domain\ReportSubmission;
use app\modules\Platform\Safety\Domain\SafetyRelation;

final class ManageSafety
{
    public function __construct(private SafetyRepository $repository, private ReportTargetResolver $targets)
    {
    }

    public function savePreferences(int $userId, PrivacyPreferences $preferences): void
    {
        if ($userId <= 0) throw new \InvalidArgumentException('用户标识无效');
        $this->repository->savePreferences($userId, $preferences);
    }

    public function preferences(int $userId): PrivacyPreferences
    {
        if ($userId <= 0) throw new \InvalidArgumentException('用户标识无效');
        return $this->repository->preferences($userId);
    }

    public function addRelation(int $actorId, int $targetId, string $kind): bool
    {
        SafetyRelation::assertAllowed($actorId, $targetId, $kind);
        return $this->repository->addRelation($actorId, $targetId, $kind);
    }

    public function removeRelation(int $actorId, int $targetId, string $kind): bool
    {
        SafetyRelation::assertAllowed($actorId, $targetId, $kind);
        return $this->repository->removeRelation($actorId, $targetId, $kind);
    }

    public function report(ReportSubmission $submission): int
    {
        $snapshot = $this->targets->snapshotForReporter($submission->reporterId(), $submission->resource());
        if ($snapshot === null) throw ApiException::notFound('举报目标不存在或无权访问');
        return $this->repository->createReport($submission, $snapshot);
    }

    public function canMessage(int $senderId, int $recipientId): bool
    {
        SafetyRelation::assertAllowed($senderId, $recipientId, SafetyRelation::BLOCK);
        return $this->repository->canMessage($senderId, $recipientId);
    }
}
