<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/app/api/ApiException.php';
require $root . '/app/modules/Platform/Auth/Domain/SessionRecord.php';
require $root . '/app/modules/Platform/Auth/Contract/SessionRegistry.php';
require $root . '/app/shared/Transaction/TransactionManager.php';
require $root . '/app/shared/Observability/AuditLogger.php';
require $root . '/app/modules/Platform/Auth/Application/UserSessions.php';

use app\modules\Platform\Auth\Application\UserSessions;
use app\modules\Platform\Auth\Contract\SessionRegistry;
use app\modules\Platform\Auth\Domain\SessionRecord;
use app\shared\Observability\AuditLogger;
use app\shared\Transaction\TransactionManager;

final class UserSessionsRepo implements SessionRegistry
{
    public array $rows = [
        ['public_id'=>'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','user_id'=>7,'token_expires_at'=>'2099-01-01 00:00:00','renewable_until'=>'2099-02-01 00:00:00','security_version'=>1,'revoked_at'=>null,'revoked_reason'=>null,'created_at'=>'2026-08-24 00:00:00'],
        ['public_id'=>'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb','user_id'=>7,'token_expires_at'=>'2099-01-01 00:00:00','renewable_until'=>'2099-02-01 00:00:00','security_version'=>1,'revoked_at'=>null,'revoked_reason'=>null,'created_at'=>'2026-08-23 00:00:00'],
    ];
    public function listForUser(int $userId, bool $includeRevoked = true): array { return array_values(array_filter($this->rows, fn(array $row): bool => $row['user_id']===$userId && ($includeRevoked || $row['revoked_at']===null))); }
    public function revokeById(int $userId, int $sessionId, string $reason, DateTimeImmutable $at): bool { return false; }
    public function findForUserByPublicId(int $userId, string $publicId): ?array { foreach ($this->rows as $row) if ($row['user_id']===$userId && $row['public_id']===$publicId) return $row; return null; }
    public function revokeByPublicId(int $userId, string $publicId, string $reason, DateTimeImmutable $at): bool { foreach ($this->rows as &$row) if ($row['user_id']===$userId && $row['public_id']===$publicId && $row['revoked_at']===null) { $row['revoked_at']=$at->format('Y-m-d H:i:s'); return true; } return false; }
    public function revokeOthers(int $userId, string $currentPublicId, string $reason, DateTimeImmutable $at): int { $count=0; foreach ($this->rows as &$row) if ($row['user_id']===$userId && $row['public_id']!==$currentPublicId && $row['revoked_at']===null) { $row['revoked_at']=$at->format('Y-m-d H:i:s'); $count++; } return $count; }
    public function start(int $userId, string $jtiHash, DateTimeImmutable $tokenExpiresAt, DateTimeImmutable $renewableUntil): SessionRecord { throw new LogicException('not used'); }
    public function findActive(string $jtiHash, DateTimeImmutable $at): ?SessionRecord { return null; }
    public function rotate(string $oldJtiHash, string $newJtiHash, int $userId, int $expectedSecurityVersion, int $expectedTokenExpiresAt, DateTimeImmutable $tokenExpiresAt, DateTimeImmutable $renewableUntil, DateTimeImmutable $at): ?SessionRecord { return null; }
    public function revoke(string $jtiHash, string $reason, DateTimeImmutable $at): bool { return false; }
    public function revokeAllForUser(int $userId, string $reason, DateTimeImmutable $at): int { return 0; }
}
final class UserSessionsTx implements TransactionManager { public function run(callable $callback): mixed { return $callback(); } }
final class UserSessionsAudit implements AuditLogger { public array $records=[]; public function record(?int $actorId, string $action, string $resourceType, ?string $resourceId, array $metadata=[]): void { $this->records[]=$action; } }

$assert = static fn(bool $condition, string $message) => $condition ?: throw new RuntimeException($message);
$repo=new UserSessionsRepo(); $audit=new UserSessionsAudit(); $service=new UserSessions($repo,new UserSessionsTx(),$audit);
$items=$service->list(7,'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
$assert($items[0]['current']===true && $items[0]['id']==='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'session list must identify current opaque public id');
$assert($items[0]['device_name']===null && $items[0]['ip_address']===null && $items[0]['location']===null, 'unknown session metadata must remain null');
$service->revoke(7,'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb');
$service->revoke(7,'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa','bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb');
$assert(count($audit->records)===1, 'repeated own-session revocation must be idempotent and audited once');
$repo->rows[1]['revoked_at']=null;
$service->revokeOthers(7,'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa');
$assert($repo->rows[0]['revoked_at']===null && $repo->rows[1]['revoked_at']!==null, 'revokeOthers must preserve the current session');
$notFound=false;
try { $service->revoke(8,'cccccccccccccccccccccccccccccccc','aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'); } catch (\app\api\ApiException $exception) { $notFound=$exception->getHttpStatus()===404; }
$assert($notFound, 'cross-user session revocation must be indistinguishable from a missing session');
fwrite(STDOUT, "PASS current-user sessions baseline\n");
