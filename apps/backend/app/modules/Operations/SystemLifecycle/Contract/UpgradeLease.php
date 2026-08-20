<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Contract;

interface UpgradeLease
{
    /** 返回不可伪造的 holder token；已有有效 Lease 时返回 null。 */
    public function acquire(string $leaseName, \DateTimeImmutable $now, int $ttlSeconds): ?string;

    /** 仅当前 holder 可以续租；Lease 已丢失或过期时返回 false。 */
    public function renew(
        string $leaseName,
        string $holderToken,
        \DateTimeImmutable $now,
        int $ttlSeconds
    ): bool;

    public function release(string $leaseName, string $holderToken): void;
}
