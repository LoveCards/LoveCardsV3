<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/vendor/topthink/framework/src/helper.php';
$app = new \think\App($root);
$app->initialize();

use app\modules\Platform\Auth\Application\AdminSecurity;
use app\modules\Platform\Auth\Contract\AdminSecurityRepository;
use app\modules\Platform\Auth\Contract\SessionRegistry;
use app\modules\Platform\Auth\Domain\SessionRecord;
use app\modules\Platform\Auth\Transport\Api\AdminSecurityResource;
use app\modules\Platform\Eventing\Public\RecordedEvent;
use app\modules\Platform\Notifications\Contract\SystemNoticeIssuer;
use app\shared\Authorization\Grant;
use app\shared\Authorization\GrantSet;
use app\shared\Authorization\Scope;
use app\shared\Observability\AuditLogger;
use app\shared\Transaction\TransactionManager;

final class AdminSecuritySessionsFake implements SessionRegistry
{
    public bool $active = true;
    public bool $allActive = true;
    public function listForUser(int $userId, bool $includeRevoked = true): array
    {
        return $this->allActive ? [[
            'id' => 8,
            'revoked_at' => null,
            'token_expires_at' => '2099-01-01 00:00:00',
        ]] : [];
    }
    public function revokeById(int $userId, int $sessionId, string $reason, DateTimeImmutable $at): bool
    {
        if (!$this->active || $userId !== 42 || $sessionId !== 7) return false;
        $this->active = false;
        return true;
    }
    public function findForUserByPublicId(int $userId, string $publicId): ?array { return null; }
    public function revokeByPublicId(int $userId, string $publicId, string $reason, DateTimeImmutable $at): bool { return false; }
    public function revokeOthers(int $userId, string $currentPublicId, string $reason, DateTimeImmutable $at): int { return 0; }
    public function start(int $userId, string $jtiHash, DateTimeImmutable $tokenExpiresAt, DateTimeImmutable $renewableUntil): SessionRecord { throw new LogicException('not used'); }
    public function findActive(string $jtiHash, DateTimeImmutable $at): ?SessionRecord { return null; }
    public function rotate(string $oldJtiHash, string $newJtiHash, int $userId, int $expectedSecurityVersion, int $expectedTokenExpiresAt, DateTimeImmutable $tokenExpiresAt, DateTimeImmutable $renewableUntil, DateTimeImmutable $at): ?SessionRecord { return null; }
    public function revoke(string $jtiHash, string $reason, DateTimeImmutable $at): bool { return false; }
    public function revokeAllForUser(int $userId, string $reason, DateTimeImmutable $at): int
    {
        if (!$this->allActive) return 0;
        $this->allActive = false;
        return 1;
    }
}

final class AdminSecurityRepositoryFake implements AdminSecurityRepository
{
    public function findUserOverview(int $userId, DateTimeImmutable $now): ?array { return in_array($userId, [42, 43], true) ? ['id' => $userId] : null; }
    public function findUserRoleIds(int $userId, bool $forUpdate = false): array { return $userId === 42 ? [1] : []; }
    public function listRecoveryChallenges(?string $status, int $page, int $limit, DateTimeImmutable $now): array { return ['items' => [], 'total' => 0]; }
}

final class AdminSecurityAuditFake implements AuditLogger
{
    public array $records = [];
    public function record(?int $actorId, string $action, string $resourceType, ?string $resourceId, array $metadata = []): void
    {
        $this->records[] = compact('actorId', 'action', 'resourceType', 'resourceId', 'metadata');
    }
}

final class AdminSecurityTransactionsFake implements TransactionManager
{
    public function run(callable $callback): mixed { return $callback(); }
}

final class AdminSecurityNoticesFake implements SystemNoticeIssuer
{
    public array $records = [];
    public function issue(int $recipientId, string $noticeKey, string $type, string $message, ?string $actionUrl = null, string $severity = 'info', ?int $actorId = null): RecordedEvent
    {
        $this->records[] = compact('recipientId', 'noticeKey', 'type', 'message', 'actionUrl', 'severity', 'actorId');
        return new RecordedEvent(count($this->records), []);
    }
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$sessions = new AdminSecuritySessionsFake();
$audit = new AdminSecurityAuditFake();
$notices = new AdminSecurityNoticesFake();
$security = new AdminSecurity($sessions, new AdminSecurityRepositoryFake(), $audit, new AdminSecurityTransactionsFake(), $notices);
$rootGrants = new GrantSet([new Grant('users.sessions.revoke', Scope::GLOBAL)]);
$adminGrants = new GrantSet([new Grant('users.sessions.revoke', Scope::GLOBAL)]);
$readOnlyGrants = new GrantSet();

$rootOverview = $security->overview(9, [2], $adminGrants, 42);
$assert($rootOverview['session_mutation_allowed'] === false, 'non-root session operator must receive false for a root target');
$ordinaryOverview = $security->overview(9, [8], $adminGrants, 43);
$assert($ordinaryOverview['session_mutation_allowed'] === true, 'dedicated security role may revoke an ordinary target without users.read or roles.read');
$readOnlyOverview = $security->overview(9, [8], $readOnlyGrants, 43);
$assert($readOnlyOverview['session_mutation_allowed'] === false, 'security overview reader without revoke grant must receive false');
$superOverview = $security->overview(1, [1], $rootGrants, 42);
$assert($superOverview['session_mutation_allowed'] === true, 'root session operator may revoke a root target');
$assert(!array_key_exists('roles', $ordinaryOverview) && !array_key_exists('grants', $ordinaryOverview), 'overview must not expose role or grant internals');
$resource = AdminSecurityResource::make($ordinaryOverview);
$assert(is_bool($resource['session_mutation_allowed']), 'security resource must return a boolean mutation flag');
$assert((AdminSecurityResource::schema()['properties']['session_mutation_allowed']['type'] ?? null) === 'boolean', 'security resource schema must publish the mutation flag');
$assert(in_array('session_mutation_allowed', AdminSecurityResource::schema()['required'] ?? [], true), 'security mutation flag must remain required in the contract');

$nonRootRejected = false;
try { $security->revoke(9, [2], $adminGrants, 42, 7, 'forbidden root action', '127.0.0.1'); }
catch (\app\api\ApiException $exception) { $nonRootRejected = $exception->getHttpStatus() === 403; }
$assert($nonRootRejected, 'non-root administrator must not revoke a root session');
$assert(count($audit->records) === 0 && count($notices->records) === 0, 'rejected root mutation must have no side effects');

$security->revoke(9, [1], $rootGrants, 42, 7, 'risk response', '127.0.0.1');
$assert(count($audit->records) === 1, 'session revocation must be audited');
$assert(count($notices->records) === 1, 'session revocation must issue one security notice');
$assert($notices->records[0]['noticeKey'] === 'auth.session.revoked:7', 'session notice must use the stable session key');
$assert($notices->records[0]['recipientId'] === 42 && $notices->records[0]['actorId'] === null, 'session notice must use system identity for the affected user');

$duplicateRejected = false;
try { $security->revoke(9, [1], $rootGrants, 42, 7, 'repeat', '127.0.0.1'); }
catch (\app\api\ApiException) { $duplicateRejected = true; }
$assert($duplicateRejected, 'repeated session revocation must be rejected');
$assert(count($notices->records) === 1, 'repeated session revocation must not duplicate the notice');

$security->revokeAll(9, [1], $rootGrants, 42, 'incident response', '127.0.0.1');
$assert(count($notices->records) === 2, 'revoking all active sessions must issue one security notice');
$assert(str_starts_with($notices->records[1]['noticeKey'], 'auth.sessions.revoked_all:'), 'revoke-all notice must use the affected session set as its stable key');
$assert($notices->records[1]['severity'] === 'critical', 'revoke-all notice must be critical');
$security->revokeAll(9, [1], $rootGrants, 42, 'repeat', '127.0.0.1');
$assert(count($notices->records) === 2, 'revoke-all without active sessions must not issue another notice');

fwrite(STDOUT, "PASS admin security notification baseline\n");
