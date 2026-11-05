<?php

declare(strict_types=1);

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return $GLOBALS['realtime_env'][$key] ?? $default;
    }
}

require dirname(__DIR__, 2) . '/app/modules/Platform/Eventing/Contract/RealtimeTicketIssuer.php';
require dirname(__DIR__, 2) . '/app/modules/Platform/Eventing/Infrastructure/HmacRealtimeTicketIssuer.php';

use app\modules\Platform\Eventing\Infrastructure\HmacRealtimeTicketIssuer;

$GLOBALS['realtime_env'] = [
    'LC_REALTIME_TICKET_SECRET' => 'ticket-secret-abcdefghijklmnopqrstuvwxyz-1234',
    'LC_REALTIME_PUBLIC_URL' => 'http://127.0.0.1:7070',
    'LC_REALTIME_TICKET_TTL_SECONDS' => 60,
];

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$issuer = new HmacRealtimeTicketIssuer();
$issued = $issuer->issue(123, ['notifications']);
$assert(str_contains($issued['ticket'], '.'), 'ticket must contain a signature separator');
$assert($issued['endpoint'] === 'http://127.0.0.1:7070/stream', 'ticket endpoint must be the configured Gateway stream');
$assert($issued['protocol'] === 'lc-realtime-1', 'ticket protocol must be stable');
$assert($issued['topics'] === ['notifications'], 'ticket topics must be normalized');
$assert($issued['cursor_strategy'] === 'opaque', 'ticket must declare opaque cursor strategy');

$failed = false;
try { $issuer->issue(0); } catch (InvalidArgumentException) { $failed = true; }
$assert($failed, 'invalid recipient must be rejected');
$failed = false;
try { $issuer->issue(123, ['unknown']); } catch (InvalidArgumentException) { $failed = true; }
$assert($failed, 'unknown topic must be rejected');

fwrite(STDOUT, "Realtime ticket issuer baseline passed.\n");
