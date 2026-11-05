<?php

namespace app\modules\Platform\Safety\Contract;

use app\modules\Platform\Safety\Domain\PrivacyPreferences;
use app\modules\Platform\Safety\Domain\ReportSubmission;

interface SafetyRepository
{
    public function savePreferences(int $userId, PrivacyPreferences $preferences): void;
    public function preferences(int $userId): PrivacyPreferences;
    public function addRelation(int $actorId, int $targetId, string $kind): bool;
    public function removeRelation(int $actorId, int $targetId, string $kind): bool;
    public function hasRelation(int $actorId, int $targetId, string $kind): bool;
    public function isBlockedEitherDirection(int $firstUserId, int $secondUserId): bool;
    /** @return array<int,int> */
    public function blockedUserIds(int $userId): array;
    public function userExists(int $userId): bool;
    /** @return array{items:array<int,array<string,mixed>>,next_cursor:?string,has_more:bool} */
    public function listBlocked(int $userId, ?string $cursor, int $limit, ?string $query): array;
    public function canMessage(int $senderId, int $recipientId): bool;
    /**
     * 同一举报人对同一目标是否已有待处理/处理中的案件（重复举报幂等依据）。
     * $targetId 必须是证据快照解析后的内部主键。
     */
    public function findOpenReport(int $reporterId, string $targetType, string $targetId): ?int;
    /** @param array<string,mixed> $evidenceSnapshot */
    public function createReport(ReportSubmission $submission, array $evidenceSnapshot, string $targetId): int;
}
