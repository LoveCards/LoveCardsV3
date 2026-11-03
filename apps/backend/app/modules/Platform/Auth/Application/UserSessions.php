<?php
declare(strict_types=1);

namespace app\modules\Platform\Auth\Application;

use app\api\ApiException;
use app\modules\Platform\Auth\Contract\SessionRegistry;
use app\shared\Observability\AuditLogger;
use app\shared\Transaction\TransactionManager;
use DateTimeImmutable;
use DateTimeZone;

final class UserSessions
{
    public function __construct(
        private SessionRegistry $sessions,
        private TransactionManager $transactions,
        private AuditLogger $audit
    ) {}

    /** @return array<int,array<string,mixed>> */
    public function list(int $userId, string $currentPublicId): array
    {
        $this->assertValidCurrentSessionId($currentPublicId);
        $now = $this->now();
        return array_map(static function (array $row) use ($currentPublicId, $now): array {
            $revoked = !empty($row['revoked_at']);
            $expiresAt = (string) ($row['token_expires_at'] ?? '');
            $expired = !$revoked && $expiresAt !== '' && new DateTimeImmutable($expiresAt, new DateTimeZone('UTC')) <= $now;
            return [
                'id' => (string) $row['public_id'],
                'current' => hash_equals($currentPublicId, (string) $row['public_id']),
                'status' => $revoked ? 'revoked' : ($expired ? 'expired' : 'active'),
                'created_at' => (string) $row['created_at'],
                'expires_at' => $expiresAt,
                'last_seen_at' => null,
                'device_name' => null,
                'ip_address' => null,
                'location' => null,
            ];
        }, $this->sessions->listForUser($userId, true));
    }

    public function revoke(int $userId, string $currentPublicId, string $sessionPublicId, ?string $ipAddress = null): void
    {
        $this->assertValidCurrentSessionId($currentPublicId);
        if (preg_match('/^[a-f0-9]{32}$/D', $sessionPublicId) !== 1) throw ApiException::notFound('会话不存在');
        $this->transactions->run(function () use ($userId, $sessionPublicId, $ipAddress): void {
            $row = $this->sessions->findForUserByPublicId($userId, $sessionPublicId);
            if ($row === null) throw ApiException::notFound('会话不存在');
            if (!empty($row['revoked_at'])) return;
            $this->sessions->revokeByPublicId($userId, $sessionPublicId, 'user_revoked', $this->now());
            $this->audit->record($userId, 'user.session.revoked', 'auth_session', $sessionPublicId, ['_ip' => $ipAddress]);
        });
    }

    public function revokeOthers(int $userId, string $currentPublicId, ?string $ipAddress = null): void
    {
        $this->assertValidCurrentSessionId($currentPublicId);
        $this->transactions->run(function () use ($userId, $currentPublicId, $ipAddress): void {
            $count = $this->sessions->revokeOthers($userId, $currentPublicId, 'user_revoked_others', $this->now());
            if ($count > 0) $this->audit->record($userId, 'user.sessions.revoked_others', 'user', (string) $userId, [
                'count' => $count, '_ip' => $ipAddress,
            ]);
        });
    }

    private function assertValidCurrentSessionId(string $currentPublicId): void
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $currentPublicId) !== 1) {
            throw ApiException::unauthorized('当前会话无效');
        }
    }

    private function now(): DateTimeImmutable { return new DateTimeImmutable('now', new DateTimeZone('UTC')); }
}
