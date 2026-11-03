<?php

namespace app\modules\Platform\Auth\Contract;

use app\modules\Platform\Auth\Domain\SessionRecord;
use DateTimeImmutable;

interface SessionRegistry
{
    /** @return array<int, array<string,mixed>> */
    public function listForUser(int $userId, bool $includeRevoked = true): array;

    public function revokeById(int $userId, int $sessionId, string $reason, DateTimeImmutable $at): bool;
    /** @return array<string,mixed>|null */
    public function findForUserByPublicId(int $userId, string $publicId): ?array;
    public function revokeByPublicId(int $userId, string $publicId, string $reason, DateTimeImmutable $at): bool;
    public function revokeOthers(int $userId, string $currentPublicId, string $reason, DateTimeImmutable $at): int;

    public function start(
        int $userId,
        string $jtiHash,
        DateTimeImmutable $tokenExpiresAt,
        DateTimeImmutable $renewableUntil
    ): SessionRecord;

    public function findActive(string $jtiHash, DateTimeImmutable $at): ?SessionRecord;

    public function rotate(
        string $oldJtiHash,
        string $newJtiHash,
        int $userId,
        int $expectedSecurityVersion,
        int $expectedTokenExpiresAt,
        DateTimeImmutable $tokenExpiresAt,
        DateTimeImmutable $renewableUntil,
        DateTimeImmutable $at
    ): ?SessionRecord;

    public function revoke(string $jtiHash, string $reason, DateTimeImmutable $at): bool;

    public function revokeAllForUser(int $userId, string $reason, DateTimeImmutable $at): int;
}
