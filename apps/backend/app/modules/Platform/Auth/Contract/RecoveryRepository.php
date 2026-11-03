<?php

declare(strict_types=1);

namespace app\modules\Platform\Auth\Contract;

interface RecoveryRepository
{
    /** @return array{challenge_id:?int,expires_in:int,debug_code?:string} */
    public function issue(string $account, \DateTimeImmutable $at, int $ttlSeconds): array;

    /** @return array<string,mixed>|null */
    public function lockChallenge(int $challengeId): ?array;

    public function incrementAttempts(int $challengeId): void;

    public function updatePassword(int $userId, string $passwordHash, \DateTimeImmutable $at): bool;

    public function consume(int $challengeId, \DateTimeImmutable $at): void;
}
