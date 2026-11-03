<?php

declare(strict_types=1);

namespace app\modules\Platform\Auth\Application;

use app\api\ApiException;
use app\modules\Platform\Auth\Contract\RecoveryRepository;
use app\modules\Platform\Auth\Contract\SessionRegistry;
use app\modules\Platform\Notifications\Contract\SystemNoticeIssuer;
use app\modules\Platform\Notifications\Domain\SystemNotice;
use app\shared\Observability\AuditLogger;
use app\shared\Transaction\TransactionManager;

final class AccountRecovery
{
    private const CHALLENGE_TTL_SECONDS = 600;
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private RecoveryRepository $recovery,
        private SessionRegistry $sessions,
        private AuditLogger $audit,
        private TransactionManager $transactions,
        private SystemNoticeIssuer $notices
    ) {
    }

    public function issue(string $account, string $ipAddress = ''): array
    {
        $account = trim($account);
        if ($account === '') {
            throw ApiException::badRequest('恢复账号不能为空');
        }

        return $this->transactions->run(function () use ($account, $ipAddress): array {
            $result = $this->recovery->issue($account, $this->now(), self::CHALLENGE_TTL_SECONDS);
            $this->audit->record(null, 'auth.recovery.request', 'user', null, [
                'account_hash' => hash('sha256', strtolower($account)),
                '_ip' => $ipAddress,
            ]);
            return $result;
        });
    }

    public function reset(int $challengeId, string $code, string $password, string $ipAddress = ''): void
    {
        $length = strlen($password);
        if ($length < 8 || $length > 36) {
            throw ApiException::badRequest('密码长度必须为 8-36 位');
        }
        $code = trim($code);

        $error = $this->transactions->run(function () use ($challengeId, $code, $password, $ipAddress): ?string {
            $now = $this->now();
            $challenge = $this->recovery->lockChallenge($challengeId);
            if (!$this->isUsable($challenge, $now)) {
                $this->audit->record(null, 'auth.recovery.failed', 'security_challenge', (string) $challengeId, [
                    'reason' => 'invalid_or_expired',
                    '_ip' => $ipAddress,
                ]);
                return '恢复挑战无效或已过期';
            }

            // 错误验证码也必须正常提交 attempts，不能因抛出异常而回滚限次状态。
            $this->recovery->incrementAttempts($challengeId);
            if (!hash_equals((string) $challenge['secret_hash'], hash('sha256', $code))) {
                $this->audit->record(null, 'auth.recovery.failed', 'security_challenge', (string) $challengeId, [
                    'reason' => 'code_mismatch',
                    'attempts' => (int) $challenge['attempts'] + 1,
                    '_ip' => $ipAddress,
                ]);
                return '恢复验证码错误';
            }

            $userId = (int) $challenge['user_id'];
            if (!$this->recovery->updatePassword($userId, password_hash($password, PASSWORD_DEFAULT), $now)) {
                throw new \RuntimeException('恢复账号不存在');
            }
            $this->recovery->consume($challengeId, $now);
            $this->sessions->revokeAllForUser($userId, 'password_recovery', $now);
            $this->audit->record(null, 'auth.recovery.reset', 'user', (string) $userId, [
                'challenge_id' => $challengeId,
                '_ip' => $ipAddress,
            ]);
            $this->notices->issue(
                $userId,
                'auth.recovery.reset:' . $challengeId,
                SystemNotice::SECURITY,
                '你的账号密码已通过找回流程重置，所有登录会话已退出。若非本人操作，请尽快联系站点管理员。',
                '/settings',
                'critical'
            );
            return null;
        });

        if ($error !== null) {
            throw ApiException::badRequest($error);
        }
    }

    /** @param array<string,mixed>|null $challenge */
    private function isUsable(?array $challenge, \DateTimeImmutable $now): bool
    {
        if (
            $challenge === null
            || (string) ($challenge['purpose'] ?? '') !== 'password_recovery'
            || ($challenge['consumed_at'] ?? null) !== null
            || (int) ($challenge['user_id'] ?? 0) < 1
            || (int) ($challenge['attempts'] ?? 0) >= self::MAX_ATTEMPTS
        ) {
            return false;
        }

        try {
            $expiresAt = new \DateTimeImmutable((string) ($challenge['expires_at'] ?? ''), new \DateTimeZone('UTC'));
        } catch (\Throwable) {
            return false;
        }
        return $expiresAt > $now;
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
