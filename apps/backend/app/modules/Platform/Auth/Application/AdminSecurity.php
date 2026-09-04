<?php
declare(strict_types=1);

namespace app\modules\Platform\Auth\Application;

use app\api\ApiException;
use app\modules\Platform\Auth\Contract\AdminSecurityRepository;
use app\modules\Platform\Auth\Contract\SessionRegistry;
use app\modules\Platform\Notifications\Contract\SystemNoticeIssuer;
use app\modules\Platform\Notifications\Domain\SystemNotice;
use app\modules\Platform\Rbac\Public\AdministrationBoundary;
use app\shared\Authorization\GrantSet;
use app\shared\Authorization\Scope;
use app\shared\Observability\AuditLogger;
use app\shared\Transaction\TransactionManager;

final class AdminSecurity
{
    public function __construct(
        private SessionRegistry $sessions,
        private AdminSecurityRepository $repository,
        private AuditLogger $audit,
        private TransactionManager $transactions,
        private SystemNoticeIssuer $notices
    ) {}

    public function overview(
        int $actorId,
        array $actorRoleIds,
        GrantSet $actorGrants,
        int $userId
    ): array
    {
        $overview = $this->loadOverview($userId);
        $overview['session_mutation_allowed'] = $this->sessionMutationViolation(
            $actorId,
            $actorRoleIds,
            $actorGrants,
            $userId,
            false
        ) === null;
        return $overview;
    }

    public function sessions(int $userId): array
    {
        $this->loadOverview($userId);
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

    public function revoke(
        int $actorId,
        array $actorRoleIds,
        GrantSet $actorGrants,
        int $userId,
        int $sessionId,
        string $ipAddress
    ): void
    {
        $this->transactions->run(function () use ($actorId, $actorRoleIds, $actorGrants, $userId, $sessionId, $ipAddress): void {
            $this->assertMayMutateUser($actorId, $actorRoleIds, $actorGrants, $userId);
            if (!$this->sessions->revokeById($userId, $sessionId, 'admin_revoked', $this->now())) throw ApiException::notFound('Session 不存在或已撤销');
            $this->audit->record($actorId, 'auth.session.revoke', 'auth_session', (string) $sessionId, ['user_id' => $userId, '_ip' => $ipAddress]);
            $this->notices->issue(
                $userId,
                'auth.session.revoked:' . $sessionId,
                SystemNotice::SECURITY,
                '你的一个登录会话已由管理员撤销。如非预期，请检查账号安全。',
                '/settings',
                'warning'
            );
        });
    }

    public function revokeAll(
        int $actorId,
        array $actorRoleIds,
        GrantSet $actorGrants,
        int $userId,
        string $ipAddress
    ): void
    {
        $this->transactions->run(function () use ($actorId, $actorRoleIds, $actorGrants, $userId, $ipAddress): void {
            $this->assertMayMutateUser($actorId, $actorRoleIds, $actorGrants, $userId);
            $now = $this->now();
            $sessionIds = [];
            foreach ($this->sessions->listForUser($userId, false) as $session) {
                if (!empty($session['revoked_at'])) continue;
                if (new \DateTimeImmutable((string) $session['token_expires_at'], new \DateTimeZone('UTC')) <= $now) continue;
                $sessionIds[] = (int) $session['id'];
            }
            sort($sessionIds);
            $count = $this->sessions->revokeAllForUser($userId, 'admin_revoked_all', $now);
            $this->audit->record($actorId, 'auth.session.revoke_all', 'user', (string) $userId, ['count' => $count, '_ip' => $ipAddress]);
            if ($count > 0) {
                $this->notices->issue(
                    $userId,
                    'auth.sessions.revoked_all:' . hash('sha256', implode(',', $sessionIds)),
                    SystemNotice::SECURITY,
                    '你的全部登录会话已由管理员撤销。如非预期，请尽快检查账号安全。',
                    '/settings',
                    'critical'
                );
            }
        });
    }

    private function assertMayMutateUser(
        int $actorId,
        array $actorRoleIds,
        GrantSet $actorGrants,
        int $userId
    ): void {
        $this->loadOverview($userId);
        $violation = $this->sessionMutationViolation(
            $actorId,
            $actorRoleIds,
            $actorGrants,
            $userId,
            true
        );
        if ($violation !== null) throw ApiException::forbidden($violation);
    }

    private function sessionMutationViolation(
        int $actorId,
        array $actorRoleIds,
        GrantSet $actorGrants,
        int $userId,
        bool $forUpdate
    ): ?string {
        if (!$actorGrants->allows('users.sessions.revoke', Scope::GLOBAL)) return '权限不足';
        return AdministrationBoundary::userMutationViolation(
            $actorId,
            $actorRoleIds,
            $actorGrants,
            $userId,
            $this->repository->findUserRoleIds($userId, $forUpdate),
            proposedRoleIds: null,
            proposedGrants: null,
            enforceTargetGrantCeiling: false
        );
    }

    private function loadOverview(int $userId): array
    {
        $overview = $this->repository->findUserOverview($userId, $this->now());
        if ($overview === null) throw ApiException::notFound('用户不存在');
        return $overview;
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
