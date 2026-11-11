<?php
declare(strict_types=1);

/**
 * Stream ticket 一次性语义基线。
 * 纯内存替身，不触碰 DB/Redis。
 */

$root = dirname(__DIR__, 2);
require $root . '/app/modules/Operations/Runtime/Contract/StreamTicketStore.php';
require $root . '/app/modules/Operations/Runtime/Application/ManageStreamTickets.php';

use app\modules\Operations\Runtime\Application\ManageStreamTickets;
use app\modules\Operations\Runtime\Contract\StreamTicketStore;

require $root . '/app/modules/Platform/Auth/Public/AuthUser.php';
require $root . '/app/modules/Platform/Auth/Domain/SessionRecord.php';
require $root . '/app/modules/Platform/Auth/Contract/SessionRegistry.php';
require $root . '/app/modules/Platform/Auth/Contract/UserRepository.php';
require $root . '/app/modules/Platform/User/Public/UserStatus.php';
require $root . '/app/modules/Platform/Auth/Public/SessionAccess.php';

use app\modules\Platform\Auth\Contract\SessionRegistry;
use app\modules\Platform\Auth\Contract\UserRepository;
use app\modules\Platform\Auth\Domain\SessionRecord;
use app\modules\Platform\Auth\Public\AuthUser;
use app\modules\Platform\Auth\Public\SessionAccess;

final class TicketSessions implements SessionRegistry
{
    public ?SessionRecord $active;
    public bool $fail = false;
    public function __construct() { $this->active = new SessionRecord(1, str_repeat('a',64), 42, new DateTimeImmutable('+1 hour'), new DateTimeImmutable('+2 hours'), 1); }
    public function findForUserByPublicId(int $userId, string $publicId): ?array { if ($this->fail) throw new RuntimeException('database unavailable'); return ['jti_hash'=>str_repeat('a',64)]; }
    public function findActive(string $jtiHash, DateTimeImmutable $at): ?SessionRecord { return $this->active; }
    public function listForUser(int $userId, bool $includeRevoked=true): array { return []; }
    public function revokeById(int $userId, int $sessionId, string $reason, DateTimeImmutable $at): bool { return false; }
    public function revokeByPublicId(int $userId, string $publicId, string $reason, DateTimeImmutable $at): bool { return false; }
    public function revokeOthers(int $userId, string $currentPublicId, string $reason, DateTimeImmutable $at): int { return 0; }
    public function start(int $userId, string $jtiHash, DateTimeImmutable $tokenExpiresAt, DateTimeImmutable $renewableUntil): SessionRecord { throw new LogicException(); }
    public function rotate(string $oldJtiHash, string $newJtiHash, int $userId, int $expectedSecurityVersion, int $expectedTokenExpiresAt, DateTimeImmutable $tokenExpiresAt, DateTimeImmutable $renewableUntil, DateTimeImmutable $at): ?SessionRecord { return null; }
    public function revoke(string $jtiHash, string $reason, DateTimeImmutable $at): bool { return false; }
    public function revokeAllForUser(int $userId, string $reason, DateTimeImmutable $at): int { return 0; }
}
final class TicketUsers implements UserRepository
{
    public ?AuthUser $user;
    public function __construct() { $this->user = new AuthUser(42,0,'',[]); }
    public function findById(int $id): ?AuthUser { return $this->user; }
    public function findByAccount(string $account): ?AuthUser { return null; }
    public function contactExists(string $email, string $phone): bool { return false; }
    public function create(string $number, string $username, string $email, string $phone, string $passwordHash, array $roleIds, int $status): AuthUser { throw new LogicException(); }
}

if (!function_exists('env')) {
    function env(string $key, $default = null) { return $GLOBALS['__test_env'][$key] ?? $default; }
}

final class FakeStreamTicketStore implements StreamTicketStore
{
    /** @var array<string, array{uid: int, expires_ok: bool, used: bool}> */
    public array $rows = [];
    public function issue(int $uid, string $sessionPublicId, string $ticketPlain): bool
    {
        if ($uid < 1 || strlen($ticketPlain) < 16) return false;
        $this->rows[hash('sha256', $ticketPlain)] = ['uid' => $uid, 'session_public_id' => $sessionPublicId, 'expires_ok' => true, 'used' => false];
        return true;
    }
    public function consume(string $ticketPlain): ?array
    {
        $hash = hash('sha256', $ticketPlain);
        $row = $this->rows[$hash] ?? null;
        if ($row === null || $row['used'] || !$row['expires_ok']) return null;
        $this->rows[$hash]['used'] = true;
        return ['uid' => $row['uid'], 'session_public_id' => $row['session_public_id']];
    }
}

$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };

$store = new FakeStreamTicketStore();
$sessions = new TicketSessions();
$users = new TicketUsers();
$mode = 'realtime';
$publicId = str_repeat('a',32);
$tickets = new ManageStreamTickets($store, new SessionAccess($sessions, $users), static function () use (&$mode): string { return $mode; });

$GLOBALS['__test_env']['LC_REALTIME_GATEWAY_PUBLIC_URL'] = 'https://rt.example.test/';
$issued = $tickets->issue(42, $publicId);
$assert(preg_match('/^[0-9a-f]{26}$/', $issued['ticket']) === 1, 'ticket must be 26 hex chars');
$assert($issued['expires_in'] === 60, 'ticket ttl must be 60 seconds');
$assert($issued['connect_url'] === 'https://rt.example.test/stream', 'connect_url must join /stream on the public gateway base');
$assert($issued['heartbeat_seconds'] === 15, 'heartbeat must be 15 seconds');
$assert(count($store->rows) === 1 && !array_key_exists($issued['ticket'], $store->rows), 'store must only keep the sha256 of the ticket');

$verified = $tickets->verify($issued['ticket']);
$assert($verified === ['uid' => 42, 'cursor_seed' => null, 'connection_ttl_seconds' => 60], 'verify must bind the session and return a bounded connection lifetime');
$assert($tickets->verify($issued['ticket']) === null, 'a ticket must be single use');
$assert($tickets->verify(str_repeat('0', 26)) === null, 'unknown tickets must be rejected');

$GLOBALS['__test_env']['LC_REALTIME_GATEWAY_PUBLIC_URL'] = '';
$issued2 = $tickets->issue(42, $publicId);
$assert($issued2['connect_url'] === '', 'missing gateway public url must yield an empty connect_url, never a guessed host');

$original = $sessions->active;
foreach (['banned','deleted','revoked','expired','rotated','wrong-user','polling','legacy'] as $case) {
    $issued = $tickets->issue(42, $publicId);
    if ($case === 'banned') $users->user = new AuthUser(42,1,'',[]);
    if ($case === 'deleted') $users->user = null;
    if ($case === 'revoked') $sessions->active = $original->revoke(new DateTimeImmutable(), 'logout');
    if ($case === 'expired') $sessions->active = new SessionRecord(1,str_repeat('a',64),42,new DateTimeImmutable('-1 second'),new DateTimeImmutable('+1 hour'),1);
    if ($case === 'rotated') $sessions->active = new SessionRecord(2,str_repeat('b',64),42,new DateTimeImmutable('+1 hour'),new DateTimeImmutable('+2 hours'),1);
    if ($case === 'wrong-user') $sessions->active = new SessionRecord(1,str_repeat('a',64),43,new DateTimeImmutable('+1 hour'),new DateTimeImmutable('+2 hours'),1);
    if ($case === 'polling') $mode = 'polling';
    if ($case === 'legacy') $store->rows[hash('sha256',$issued['ticket'])]['session_public_id'] = null;
    $assert($tickets->verify($issued['ticket']) === null, $case . ' must reject a previously issued ticket');
    $sessions->active = $original; $users->user = new AuthUser(42,0,'',[]); $mode = 'realtime';
}
$sessions->active = new SessionRecord(1,str_repeat('a',64),42,new DateTimeImmutable('+5 seconds'),new DateTimeImmutable('+1 hour'),1);
$short = $tickets->verify($tickets->issue(42, $publicId)['ticket']);
$assert($short['connection_ttl_seconds'] > 0 && $short['connection_ttl_seconds'] <= 5, 'connection cannot outlive session');
$issued = $tickets->issue(42, $publicId);
$sessions->fail = true;
$failedClosed = false;
try { $tickets->verify($issued['ticket']); } catch (RuntimeException $e) { $failedClosed = true; }
$assert($failedClosed, 'database errors must propagate and never authorize');
fwrite(STDOUT, "PASS stream ticket session baseline\n");
