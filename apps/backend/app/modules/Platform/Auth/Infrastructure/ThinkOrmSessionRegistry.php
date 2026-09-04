<?php

namespace app\modules\Platform\Auth\Infrastructure;

use app\modules\Platform\Auth\Contract\SessionRegistry;
use app\modules\Platform\Auth\Domain\SessionRecord;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use think\facade\Db;

final class ThinkOrmSessionRegistry implements SessionRegistry
{
    public function listForUser(int $userId, bool $includeRevoked = true): array
    {
        $query = Db::table('auth_sessions')->where('user_id', $userId);
        if (!$includeRevoked) $query->whereNull('revoked_at');
        return $query->field('id,public_id,user_id,token_expires_at,renewable_until,security_version,revoked_at,revoked_reason,rotated_to_session_id,created_at')->order('id', 'desc')->select()->toArray();
    }

    public function revokeById(int $userId, int $sessionId, string $reason, DateTimeImmutable $at): bool
    {
        return Db::table('auth_sessions')->where('id', $sessionId)->where('user_id', $userId)->whereNull('revoked_at')->update([
            'revoked_at' => $this->utc($at), 'revoked_reason' => $this->reason($reason),
        ]) === 1;
    }

    public function findForUserByPublicId(int $userId, string $publicId): ?array
    {
        $row = Db::table('auth_sessions')->where(['user_id' => $userId, 'public_id' => $publicId])
            ->field('public_id,user_id,token_expires_at,renewable_until,security_version,revoked_at,revoked_reason,created_at')
            ->find();
        return $row ?: null;
    }

    public function revokeByPublicId(int $userId, string $publicId, string $reason, DateTimeImmutable $at): bool
    {
        return Db::table('auth_sessions')->where(['user_id' => $userId, 'public_id' => $publicId])->whereNull('revoked_at')->update([
            'revoked_at' => $this->utc($at), 'revoked_reason' => $this->reason($reason),
        ]) === 1;
    }

    public function revokeOthers(int $userId, string $currentPublicId, string $reason, DateTimeImmutable $at): int
    {
        return Db::table('auth_sessions')->where('user_id', $userId)->where('public_id', '<>', $currentPublicId)->whereNull('revoked_at')->update([
            'revoked_at' => $this->utc($at), 'revoked_reason' => $this->reason($reason),
        ]);
    }

    public function start(
        int $userId,
        string $jtiHash,
        DateTimeImmutable $tokenExpiresAt,
        DateTimeImmutable $renewableUntil
    ): SessionRecord {
        Db::startTrans();
        try {
            $securityVersion = $this->lockedSecurityVersion($userId);
            $createdAt = $this->utc(new DateTimeImmutable('now', new DateTimeZone('UTC')));
            $publicId = bin2hex(random_bytes(16));
            $id = (int) Db::table('auth_sessions')->insertGetId([
                'public_id' => $publicId,
                'jti_hash' => $jtiHash,
                'user_id' => $userId,
                'token_expires_at' => $this->utc($tokenExpiresAt),
                'renewable_until' => $this->utc($renewableUntil),
                'security_version' => $securityVersion,
                'revoked_at' => null,
                'revoked_reason' => null,
                'rotated_to_session_id' => null,
                'created_at' => $createdAt,
            ]);
            Db::commit();

            return new SessionRecord(
                $id,
                $jtiHash,
                $userId,
                $tokenExpiresAt,
                $renewableUntil,
                $securityVersion,
                null,
                null,
                null,
                $publicId
            );
        } catch (\Throwable $exception) {
            Db::rollback();
            throw $exception;
        }
    }

    public function findActive(string $jtiHash, DateTimeImmutable $at): ?SessionRecord
    {
        $row = Db::table('auth_sessions')
            ->alias('sessions')
            ->join('users users', 'users.id = sessions.user_id')
            ->where('sessions.jti_hash', $jtiHash)
            ->whereNull('sessions.revoked_at')
            ->where('sessions.token_expires_at', '>', $this->utc($at))
            ->whereColumn('sessions.security_version', '=', 'users.security_version')
            ->whereNull('users.deleted_at')
            ->field('sessions.*')
            ->find();

        return $row ? $this->hydrate($row) : null;
    }

    public function rotate(
        string $oldJtiHash,
        string $newJtiHash,
        int $userId,
        int $expectedSecurityVersion,
        int $expectedTokenExpiresAt,
        DateTimeImmutable $tokenExpiresAt,
        DateTimeImmutable $renewableUntil,
        DateTimeImmutable $at
    ): ?SessionRecord {
        Db::startTrans();
        try {
            $row = Db::table('auth_sessions')
                ->where('jti_hash', $oldJtiHash)
                ->lock(true)
                ->find();
            if (!$row) {
                Db::rollback();
                return null;
            }

            $old = $this->hydrate($row);
            $currentSecurityVersion = $this->lockedSecurityVersion($userId);
            if (
                !$old->isRenewableAt($at)
                || $old->userId() !== $userId
                || $old->securityVersion() !== $expectedSecurityVersion
                || $old->tokenExpiresAt()->getTimestamp() !== $expectedTokenExpiresAt
                || $currentSecurityVersion !== $expectedSecurityVersion
            ) {
                Db::rollback();
                return null;
            }

            $newId = (int) Db::table('auth_sessions')->insertGetId([
                'public_id' => $newPublicId = bin2hex(random_bytes(16)),
                'jti_hash' => $newJtiHash,
                'user_id' => $userId,
                'token_expires_at' => $this->utc($tokenExpiresAt),
                'renewable_until' => $this->utc($renewableUntil),
                'security_version' => $currentSecurityVersion,
                'revoked_at' => null,
                'revoked_reason' => null,
                'rotated_to_session_id' => null,
                'created_at' => $this->utc($at),
            ]);

            $updated = Db::table('auth_sessions')
                ->where('id', $old->id())
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => $this->utc($at),
                    'revoked_reason' => 'rotated',
                    'rotated_to_session_id' => $newId,
                ]);
            if ($updated !== 1) {
                Db::rollback();
                return null;
            }

            Db::commit();

            return new SessionRecord(
                $newId,
                $newJtiHash,
                $userId,
                $tokenExpiresAt,
                $renewableUntil,
                $currentSecurityVersion,
                null,
                null,
                null,
                $newPublicId
            );
        } catch (\Throwable $exception) {
            Db::rollback();
            throw $exception;
        }
    }

    public function revoke(string $jtiHash, string $reason, DateTimeImmutable $at): bool
    {
        return Db::table('auth_sessions')
            ->where('jti_hash', $jtiHash)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => $this->utc($at),
                'revoked_reason' => $this->reason($reason),
            ]) === 1;
    }

    public function revokeAllForUser(int $userId, string $reason, DateTimeImmutable $at): int
    {
        return Db::table('auth_sessions')
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => $this->utc($at),
                'revoked_reason' => $this->reason($reason),
            ]);
    }

    private function lockedSecurityVersion(int $userId): int
    {
        $user = Db::table('users')
            ->where('id', $userId)
            ->whereNull('deleted_at')
            ->field('security_version')
            ->lock(true)
            ->find();
        $securityVersion = (int) ($user['security_version'] ?? 0);
        if ($securityVersion < 1) {
            throw new RuntimeException('用户安全版本不存在，Auth Session Schema 尚未就绪');
        }

        return $securityVersion;
    }

    private function hydrate(array $row): SessionRecord
    {
        return new SessionRecord(
            (int) $row['id'],
            (string) $row['jti_hash'],
            (int) $row['user_id'],
            $this->date((string) $row['token_expires_at']),
            $this->date((string) $row['renewable_until']),
            (int) $row['security_version'],
            isset($row['revoked_at']) ? $this->date((string) $row['revoked_at']) : null,
            isset($row['revoked_reason']) ? (string) $row['revoked_reason'] : null,
            isset($row['rotated_to_session_id']) ? (int) $row['rotated_to_session_id'] : null,
            isset($row['public_id']) ? (string) $row['public_id'] : null
        );
    }

    private function date(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value, new DateTimeZone('UTC'));
    }

    private function utc(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function reason(string $reason): string
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \InvalidArgumentException('撤销原因不得为空');
        }

        return substr($reason, 0, 64);
    }
}
