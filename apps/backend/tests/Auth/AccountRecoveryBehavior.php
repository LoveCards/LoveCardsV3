<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/app/api/ApiException.php';
require $root . '/app/shared/Observability/AuditLogger.php';
require $root . '/app/shared/Transaction/TransactionManager.php';
require $root . '/app/modules/Platform/Auth/Domain/SessionRecord.php';
require $root . '/app/modules/Platform/Auth/Contract/RecoveryRepository.php';
require $root . '/app/modules/Platform/Auth/Contract/SessionRegistry.php';
require $root . '/app/modules/Platform/Auth/Application/AccountRecovery.php';

use app\modules\Platform\Auth\Application\AccountRecovery;
use app\modules\Platform\Auth\Contract\RecoveryRepository;
use app\modules\Platform\Auth\Contract\SessionRegistry;
use app\modules\Platform\Auth\Domain\SessionRecord;
use app\shared\Observability\AuditLogger;
use app\shared\Transaction\TransactionManager;

final class FakeRecoveryRepository implements RecoveryRepository
{
    public array $challenge;
    public int $attemptWrites = 0;
    public ?string $passwordHash = null;
    public bool $consumed = false;

    public function __construct()
    {
        $this->challenge = [
            'id' => 7,
            'user_id' => 42,
            'purpose' => 'password_recovery',
            'secret_hash' => hash('sha256', '123456'),
            'attempts' => 0,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 600),
            'consumed_at' => null,
        ];
    }

    public function issue(string $account, DateTimeImmutable $at, int $ttlSeconds): array
    {
        return ['challenge_id' => 7, 'expires_in' => $ttlSeconds];
    }

    public function lockChallenge(int $challengeId): ?array
    {
        return $challengeId === 7 ? $this->challenge : null;
    }

    public function incrementAttempts(int $challengeId): void
    {
        $this->attemptWrites++;
        $this->challenge['attempts']++;
    }

    public function updatePassword(int $userId, string $passwordHash, DateTimeImmutable $at): bool
    {
        if ($userId !== 42) return false;
        $this->passwordHash = $passwordHash;
        return true;
    }

    public function consume(int $challengeId, DateTimeImmutable $at): void
    {
        $this->consumed = true;
        $this->challenge['consumed_at'] = $at->format('Y-m-d H:i:s');
    }
}

final class FakeRecoverySessions implements SessionRegistry
{
    public array $revoked = [];
    public function listForUser(int $userId, bool $includeRevoked = true): array { return []; }
    public function revokeById(int $userId, int $sessionId, string $reason, DateTimeImmutable $at): bool { return false; }
    public function start(int $userId, string $jtiHash, DateTimeImmutable $tokenExpiresAt, DateTimeImmutable $renewableUntil): SessionRecord { throw new LogicException('not used'); }
    public function findActive(string $jtiHash, DateTimeImmutable $at): ?SessionRecord { return null; }
    public function rotate(string $oldJtiHash, string $newJtiHash, int $userId, int $expectedSecurityVersion, int $expectedTokenExpiresAt, DateTimeImmutable $tokenExpiresAt, DateTimeImmutable $renewableUntil, DateTimeImmutable $at): ?SessionRecord { return null; }
    public function revoke(string $jtiHash, string $reason, DateTimeImmutable $at): bool { return false; }
    public function revokeAllForUser(int $userId, string $reason, DateTimeImmutable $at): int { $this->revoked[] = [$userId, $reason]; return 2; }
}

final class FakeRecoveryTransactions implements TransactionManager
{
    public bool $active = false;
    public int $calls = 0;
    public function run(callable $callback): mixed
    {
        $this->calls++;
        $this->active = true;
        try { return $callback(); }
        finally { $this->active = false; }
    }
}

final class FakeRecoveryAudit implements AuditLogger
{
    public array $records = [];
    public function __construct(private FakeRecoveryTransactions $transactions) {}
    public function record(?int $actorId, string $action, string $resourceType, ?string $resourceId, array $metadata = []): void
    {
        $this->records[] = [$actorId, $action, $resourceType, $resourceId, $metadata, $this->transactions->active];
    }
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$repository = new FakeRecoveryRepository();
$sessions = new FakeRecoverySessions();
$transactions = new FakeRecoveryTransactions();
$audit = new FakeRecoveryAudit($transactions);
$recovery = new AccountRecovery($repository, $sessions, $audit, $transactions);

$issued = $recovery->issue(' User@Example.com ', '127.0.0.1');
$assert($issued['challenge_id'] === 7 && $issued['expires_in'] === 600, 'recovery issue must use the repository contract');
$assert($audit->records[0][1] === 'auth.recovery.request' && $audit->records[0][5] === true, 'challenge issue and audit must share a transaction');
$assert($audit->records[0][4]['account_hash'] === hash('sha256', 'user@example.com'), 'audit must store only normalized account hash');

$wrongCodeRejected = false;
try { $recovery->reset(7, '000000', 'new-password', '127.0.0.1'); }
catch (\app\api\ApiException $exception) { $wrongCodeRejected = $exception->getHttpStatus() === 400; }
$assert($wrongCodeRejected, 'wrong recovery code must return a controlled client error');
$assert($repository->attemptWrites === 1 && $repository->challenge['attempts'] === 1, 'wrong code must persist one failed attempt');
$assert($sessions->revoked === [] && $repository->passwordHash === null, 'wrong code must not change credentials or sessions');
$assert(end($audit->records)[1] === 'auth.recovery.failed' && end($audit->records)[5] === true, 'failed attempt must be audited inside its commit transaction');

$recovery->reset(7, '123456', 'new-password', '127.0.0.1');
$assert($repository->consumed && password_verify('new-password', (string) $repository->passwordHash), 'valid code must consume challenge and hash the password');
$assert($sessions->revoked === [[42, 'password_recovery']], 'valid recovery must revoke every prior user session');
$lastAudit = end($audit->records);
$assert($lastAudit[0] === null && $lastAudit[1] === 'auth.recovery.reset' && $lastAudit[3] === '42' && $lastAudit[5] === true, 'successful anonymous recovery must be transactionally audited against the user');

$shortPasswordRejected = false;
try { $recovery->reset(7, '123456', 'short'); }
catch (\app\api\ApiException $exception) { $shortPasswordRejected = $exception->getHttpStatus() === 400; }
$assert($shortPasswordRejected, 'recovery password policy must fail before persistence');

fwrite(STDOUT, "PASS account recovery behavior baseline\n");
