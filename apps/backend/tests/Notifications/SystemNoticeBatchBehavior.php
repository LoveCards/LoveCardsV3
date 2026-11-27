<?php
declare(strict_types=1);

use app\modules\Platform\Notifications\Domain\SystemNoticeBatch;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$reject = static function (array $input) use ($assert): void {
    try { SystemNoticeBatch::normalize($input); }
    catch (InvalidArgumentException) { return; }
    $assert(false, 'invalid publication input must be rejected');
};
$base = ['request_key' => 'request:stable-1', 'audience' => 'selected', 'recipient_ids' => [19, 7, 19], 'message' => ' 系统维护通知 ', 'action_url' => '/settings', 'severity' => 'info'];
$canonical = SystemNoticeBatch::normalize($base);
$assert($canonical['recipient_ids'] === [7, 19] && $canonical['message'] === '系统维护通知', 'recipient order and duplicate selections must canonicalize');
$other = SystemNoticeBatch::normalize(array_replace($base, ['recipient_ids' => [7, 19], 'request_key' => 'second:key']));
$assert(SystemNoticeBatch::requestHash($canonical) === SystemNoticeBatch::requestHash($other), 'request hash must represent content and audience independently of request token');
$assert(SystemNoticeBatch::requestHash($canonical) !== SystemNoticeBatch::requestHash(array_replace($other, ['message' => '不同正文'])), 'different body must not reuse the same request hash');
foreach ([
    ['recipient_ids' => []], ['recipient_ids' => ['7']], ['recipient_ids' => range(1, 201)],
    ['audience' => 'all'], ['audience' => 'role'], ['severity' => 'critical'],
    ['message' => ' '], ['message' => str_repeat('字', 2000)], ['message' => []],
    ['request_key' => ''], ['request_key' => str_repeat('x', 65)],
    ['action_url' => 'https://outside.example'], ['action_url' => '/%2Foutside.example'],
] as $change) $reject(array_replace($base, $change));
$all = SystemNoticeBatch::normalize(array_replace($base, ['audience' => 'all', 'recipient_ids' => [], 'severity' => 'warning']));
$assert($all['recipient_ids'] === [], 'all-user request must not enumerate recipients from the browser');
$assert(SystemNoticeBatch::noticeKey(19) === 'notice.batch:19', 'notification idempotency must use the persistent publication identity');
$prepared = ['confirmed_at' => null, 'dispatch_cursor' => 0];
$confirmed = ['confirmed_at' => '2026-11-27 08:00:00', 'dispatch_cursor' => 0];
$assert(SystemNoticeBatch::status($prepared, 0, 0, 0, 2, false, false) === 'prepared', 'preview is not an accepted send');
$assert(SystemNoticeBatch::status($confirmed, 0, 0, 0, 2, false, false) === 'queued', 'queued work is not generated or read');
$assert(SystemNoticeBatch::status($confirmed, 1, 0, 0, 1, false, true) === 'processing', 'in-flight projections must remain processing');
$assert(SystemNoticeBatch::status($confirmed, 1, 1, 0, 0, false, false) === 'completed', 'generated and deliberately skipped recipients settle the frozen batch');
$assert(SystemNoticeBatch::status($confirmed, 1, 0, 1, 0, false, false) === 'partial_failure', 'mixed terminal outcomes must show partial failure');
$assert(SystemNoticeBatch::status($confirmed, 0, 0, 2, 0, false, false) === 'failed', 'entire failed batch must not report success');
$assert(SystemNoticeBatch::status($confirmed, 0, 0, 0, 2, true, false) === 'failed', 'fanout failure is visible even before any per-recipient task exists');
echo "PASS system notice batch behavior\n";
