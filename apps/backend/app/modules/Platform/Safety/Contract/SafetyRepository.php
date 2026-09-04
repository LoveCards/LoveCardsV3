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
    /** @param array<string,mixed> $evidenceSnapshot */
    public function createReport(ReportSubmission $submission, array $evidenceSnapshot): int;
}
