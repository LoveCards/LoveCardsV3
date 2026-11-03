<?php
declare(strict_types=1);

namespace app\modules\Platform\Auth\Contract;

interface AdminSecurityRepository
{
    /** @return array<string,mixed>|null */
    public function findUserOverview(int $userId, \DateTimeImmutable $now): ?array;

    /** @return int[] */
    public function findUserRoleIds(int $userId, bool $forUpdate = false): array;

    /**
     * @return array{items:array<int,array<string,mixed>>,total:int}
     */
    public function listRecoveryChallenges(?string $status, int $page, int $limit, \DateTimeImmutable $now): array;
}
