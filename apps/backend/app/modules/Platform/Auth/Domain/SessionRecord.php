<?php

namespace app\modules\Platform\Auth\Domain;

use DateTimeImmutable;

final class SessionRecord
{
    public function __construct(
        private int $id,
        private string $jtiHash,
        private int $userId,
        private DateTimeImmutable $tokenExpiresAt,
        private DateTimeImmutable $renewableUntil,
        private int $securityVersion,
        private ?DateTimeImmutable $revokedAt = null,
        private ?string $revokedReason = null,
        private ?int $rotatedToSessionId = null,
        private ?string $publicId = null
    ) {
        if ($id < 1 || $userId < 1 || $securityVersion < 1) {
            throw new \InvalidArgumentException('Session 标识或安全版本无效');
        }
        if (preg_match('/^[a-f0-9]{64}$/D', $jtiHash) !== 1) {
            throw new \InvalidArgumentException('JTI Hash 格式无效');
        }
        if ($renewableUntil < $tokenExpiresAt) {
            throw new \InvalidArgumentException('续期截止时间不得早于 Token 到期时间');
        }
        $this->publicId ??= substr($jtiHash, 0, 32);
        if (preg_match('/^[a-f0-9]{32}$/D', $this->publicId) !== 1) throw new \InvalidArgumentException('Session 公开标识无效');
    }

    public function id(): int
    {
        return $this->id;
    }

    public function jtiHash(): string
    {
        return $this->jtiHash;
    }

    public function publicId(): string { return (string) $this->publicId; }

    public function userId(): int
    {
        return $this->userId;
    }

    public function tokenExpiresAt(): DateTimeImmutable
    {
        return $this->tokenExpiresAt;
    }

    public function renewableUntil(): DateTimeImmutable
    {
        return $this->renewableUntil;
    }

    public function securityVersion(): int
    {
        return $this->securityVersion;
    }

    public function revokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revokedReason(): ?string
    {
        return $this->revokedReason;
    }

    public function rotatedToSessionId(): ?int
    {
        return $this->rotatedToSessionId;
    }

    public function isActiveAt(DateTimeImmutable $at): bool
    {
        return $this->revokedAt === null && $at < $this->tokenExpiresAt;
    }

    public function isRenewableAt(DateTimeImmutable $at): bool
    {
        return $this->revokedAt === null
            && $at >= $this->tokenExpiresAt
            && $at < $this->renewableUntil;
    }

    public function matchesToken(int $userId, int $securityVersion, int $expiresAt): bool
    {
        return $this->userId === $userId
            && $this->securityVersion === $securityVersion
            && $this->tokenExpiresAt->getTimestamp() === $expiresAt;
    }

    public function revoke(DateTimeImmutable $at, string $reason, ?int $rotatedToSessionId = null): self
    {
        return new self(
            $this->id,
            $this->jtiHash,
            $this->userId,
            $this->tokenExpiresAt,
            $this->renewableUntil,
            $this->securityVersion,
            $at,
            $reason,
            $rotatedToSessionId,
            $this->publicId
        );
    }
}
