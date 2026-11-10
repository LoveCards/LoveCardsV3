<?php
declare(strict_types=1);

/**
 * Stream ticket 一次性语义基线。
 * 纯内存替身，不触碰 DB/Redis。
 */

$root = dirname(__DIR__, 2);
require $root . '/app/modules/Platform/Notifications/Contract/StreamTicketStore.php';
require $root . '/app/modules/Platform/Notifications/Application/ManageStreamTickets.php';

use app\modules\Platform\Notifications\Application\ManageStreamTickets;
use app\modules\Platform\Notifications\Contract\StreamTicketStore;

if (!function_exists('env')) {
    function env(string $key, $default = null) { return $GLOBALS['__test_env'][$key] ?? $default; }
}

final class FakeStreamTicketStore implements StreamTicketStore
{
    /** @var array<string, array{uid: int, expires_ok: bool, used: bool}> */
    public array $rows = [];
    public function issue(int $uid, string $ticketPlain): bool
    {
        if ($uid < 1 || strlen($ticketPlain) < 16) return false;
        $this->rows[hash('sha256', $ticketPlain)] = ['uid' => $uid, 'expires_ok' => true, 'used' => false];
        return true;
    }
    public function consume(string $ticketPlain): ?array
    {
        $hash = hash('sha256', $ticketPlain);
        $row = $this->rows[$hash] ?? null;
        if ($row === null || $row['used'] || !$row['expires_ok']) return null;
        $this->rows[$hash]['used'] = true;
        return ['uid' => $row['uid'], 'cursor_seed' => 'seed-cursor'];
    }
}

$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };

$store = new FakeStreamTicketStore();
$tickets = new ManageStreamTickets($store);

$GLOBALS['__test_env']['LC_REALTIME_GATEWAY_PUBLIC_URL'] = 'https://rt.example.test/';
$issued = $tickets->issue(42);
$assert(preg_match('/^[0-9a-f]{26}$/', $issued['ticket']) === 1, 'ticket must be 26 hex chars');
$assert($issued['expires_in'] === 60, 'ticket ttl must be 60 seconds');
$assert($issued['connect_url'] === 'https://rt.example.test/stream', 'connect_url must join /stream on the public gateway base');
$assert($issued['heartbeat_seconds'] === 15, 'heartbeat must be 15 seconds');
$assert(count($store->rows) === 1 && !array_key_exists($issued['ticket'], $store->rows), 'store must only keep the sha256 of the ticket');

$verified = $tickets->verify($issued['ticket']);
$assert($verified === ['uid' => 42, 'cursor_seed' => 'seed-cursor'], 'verify must consume the ticket and return uid plus cursor seed');
$assert($tickets->verify($issued['ticket']) === null, 'a ticket must be single use');
$assert($tickets->verify(str_repeat('0', 26)) === null, 'unknown tickets must be rejected');

$GLOBALS['__test_env']['LC_REALTIME_GATEWAY_PUBLIC_URL'] = '';
$issued2 = $tickets->issue(43);
$assert($issued2['connect_url'] === '', 'missing gateway public url must yield an empty connect_url, never a guessed host');

fwrite(STDOUT, "PASS stream ticket baseline\n");
