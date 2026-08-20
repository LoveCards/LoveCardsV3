<?php
declare(strict_types=1);

namespace app\modules\Platform\Auth\Application;

use app\api\ApiException;
use app\modules\Platform\Auth\Contract\AdminSecurityRepository;
use app\modules\Platform\Auth\Contract\SessionRegistry;
use app\shared\Observability\AuditLogger;
use app\shared\Transaction\TransactionManager;

final class AdminSecurity
{
    public function __construct(
        private SessionRegistry $sessions,
        private AdminSecurityRepository $repository,
        private AuditLogger $audit,
        private TransactionManager $transactions
    ) {}

    public function overview(int $userId): array
    {
        $overview = $this->repository->findUserOverview($userId, $this->now());
        if ($overview === null) throw ApiException::notFound('用户不存在');
        return $overview;
    }

    public function sessions(int $userId): array
    {
        $this->overview($userId);
        $now = $this->now();
        $rows = $this->sessions->listForUser($userId, true);
        foreach ($rows as &$row) {
            $row['active'] = empty($row['revoked_at']) && new \DateTimeImmutable((string) $row['token_expires_at'], new \DateTimeZone('UTC')) > $now;
        }
        unset($row);
        return $rows;
    }

    public function recovery(array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['list_rows'] ?? 20)));
        $now = $this->now();
        $result = $this->repository->listRecoveryChallenges(
            !empty($params['status']) ? (string) $params['status'] : null,
            $page,
            $limit,
            $now
        );
        $rows = $result['items'];
        $total = $result['total'];
        foreach ($rows as &$row) {
            $row['state'] = $row['consumed_at'] !== null ? 'consumed' : (new \DateTimeImmutable((string) $row['expires_at'], new \DateTimeZone('UTC')) <= $now ? 'expired' : 'active');
        }
        unset($row);
        return ['data' => $rows, 'total' => $total, 'per_page' => $limit, 'current_page' => $page, 'last_page' => max(1, (int) ceil($total / $limit))];
    }

    public function revoke(int $actorId, int $userId, int $sessionId, string $reason, string $ipAddress): void
    {
        $this->transactions->run(function () use ($actorId, $userId, $sessionId, $reason, $ipAddress): void {
            if (!$this->sessions->revokeById($userId, $sessionId, $reason, $this->now())) throw ApiException::notFound('Session 不存在或已撤销');
            $this->audit->record($actorId, 'auth.session.revoke', 'auth_session', (string) $sessionId, ['user_id' => $userId, 'reason' => $reason, '_ip' => $ipAddress]);
        });
    }

    public function revokeAll(int $actorId, int $userId, string $reason, string $ipAddress): void
    {
        $this->transactions->run(function () use ($actorId, $userId, $reason, $ipAddress): void {
            $this->overview($userId);
            $count = $this->sessions->revokeAllForUser($userId, $reason, $this->now());
            $this->audit->record($actorId, 'auth.session.revoke_all', 'user', (string) $userId, ['count' => $count, 'reason' => $reason, '_ip' => $ipAddress]);
        });
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
