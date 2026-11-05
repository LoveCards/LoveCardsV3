<?php

namespace app\modules\Platform\Safety\Application;

use app\api\ApiException;
use app\modules\Platform\Safety\Contract\SafetyRepository;
use app\modules\Platform\Safety\Contract\ReportTargetResolver;
use app\modules\Platform\Safety\Domain\PrivacyPreferences;
use app\modules\Platform\Safety\Domain\ReportSubmission;
use app\modules\Platform\Safety\Domain\SafetyRelation;
use app\modules\Platform\Social\Contract\SocialGraphRepository;
use app\shared\Observability\AuditLogger;
use app\shared\Transaction\TransactionManager;
use app\shared\Transport\Http\CursorPage;

final class ManageSafety
{
    public function __construct(
        private SafetyRepository $repository,
        private ReportTargetResolver $targets,
        private ?SocialGraphRepository $social = null,
        private ?TransactionManager $transactions = null,
        private ?AuditLogger $audit = null
    ) {}

    public function savePreferences(int $userId, PrivacyPreferences|array $patch, ?string $ipAddress = null): PrivacyPreferences
    {
        if ($userId <= 0) throw new \InvalidArgumentException('用户标识无效');
        $before = $this->repository->preferences($userId)->toArray();
        $changes = $patch instanceof PrivacyPreferences ? $patch->toArray() : $patch;
        $merged = array_replace($before, array_intersect_key($changes, $before));
        $preferences = new PrivacyPreferences(
            (string) $merged['profile_visibility'],
            (string) $merged['messages_from'],
            (bool) $merged['show_relations'],
            (string) ($merged['gender_visibility'] ?? 'private'),
            (string) ($merged['birth_date_visibility'] ?? 'private')
        );
        $this->run(function () use ($userId, $preferences, $before, $ipAddress): void {
            $this->repository->savePreferences($userId, $preferences);
            $this->audit?->record($userId, 'user.privacy.updated', 'user', (string) $userId, [
                'before' => $before, 'after' => $preferences->toArray(), '_ip' => $ipAddress,
            ]);
        });
        return $preferences;
    }

    public function preferences(int $userId): PrivacyPreferences
    {
        if ($userId <= 0) throw new \InvalidArgumentException('用户标识无效');
        return $this->repository->preferences($userId);
    }

    public function addRelation(int $actorId, int $targetId, string $kind): bool
    {
        try { SafetyRelation::assertAllowed($actorId, $targetId, $kind); }
        catch (\InvalidArgumentException $exception) {
            throw ApiException::validation(['user_id' => [$exception->getMessage()]]);
        }
        if (!$this->repository->userExists($targetId)) throw ApiException::notFound('用户不存在');
        return $this->run(function () use ($actorId, $targetId, $kind): bool {
            $created = $this->repository->addRelation($actorId, $targetId, $kind);
            if ($created && $kind === SafetyRelation::BLOCK && $this->social !== null) {
                $this->social->unfollow($actorId, $targetId);
                $this->social->unfollow($targetId, $actorId);
            }
            if ($created) $this->audit?->record($actorId, 'user.safety_relation.created', 'user', (string) $targetId, ['relation' => $kind]);
            return $created;
        });
    }

    public function removeRelation(int $actorId, int $targetId, string $kind): bool
    {
        try { SafetyRelation::assertAllowed($actorId, $targetId, $kind); }
        catch (\InvalidArgumentException $exception) {
            throw ApiException::validation(['user_id' => [$exception->getMessage()]]);
        }
        return $this->run(function () use ($actorId, $targetId, $kind): bool {
            $removed = $this->repository->removeRelation($actorId, $targetId, $kind);
            if ($removed) $this->audit?->record($actorId, 'user.safety_relation.removed', 'user', (string) $targetId, ['relation' => $kind]);
            return $removed;
        });
    }

    /** @return array{data:array<int,mixed>,next_cursor:?string,has_more:bool} */
    public function listBlocked(int $userId, ?string $cursor, int $limit, ?string $query): array
    {
        if ($userId <= 0) throw ApiException::unauthorized('请先登入');
        $result = $this->repository->listBlocked($userId, $cursor, max(1, min(100, $limit)), $query);
        return CursorPage::make($result['items'], $result['next_cursor'], $result['has_more']);
    }

    public function isBlockedEitherDirection(int $firstUserId, int $secondUserId): bool
    {
        return $this->repository->isBlockedEitherDirection($firstUserId, $secondUserId);
    }

    /** @return array<int,int> */
    public function blockedUserIds(int $userId): array { return $this->repository->blockedUserIds($userId); }

    public function report(ReportSubmission $submission): int
    {
        $snapshot = $this->targets->snapshotForReporter($submission->reporterId(), $submission->resource());
        if ($snapshot === null) throw ApiException::notFound('举报目标不存在或无权访问');
        // 目标统一按快照解析出的内部主键落库：用户举报走公开 uid，管理端导航需要 users.id。
        $targetId = isset($snapshot['captured']['id']) ? (string) $snapshot['captured']['id'] : $submission->resource()->id();
        // 重复举报幂等：同一举报人对同一目标的 open/reviewing 案件不重复立案、不重复通知。
        $existing = $this->repository->findOpenReport($submission->reporterId(), $submission->resource()->type(), $targetId);
        if ($existing !== null) {
            return $existing;
        }
        return $this->repository->createReport($submission, $snapshot, $targetId);
    }

    public function canMessage(int $senderId, int $recipientId): bool
    {
        SafetyRelation::assertAllowed($senderId, $recipientId, SafetyRelation::BLOCK);
        return $this->repository->canMessage($senderId, $recipientId);
    }

    private function run(callable $callback): mixed
    {
        return $this->transactions !== null ? $this->transactions->run($callback) : $callback();
    }
}
