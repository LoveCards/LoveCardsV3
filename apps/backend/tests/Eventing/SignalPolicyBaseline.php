<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

use app\modules\Platform\Eventing\Infrastructure\ThinkDbSignalPolicyStore;
use app\modules\Platform\Eventing\Public\HandlerRegistry;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$rejects = static function (callable $operation, string $message): void {
    try { $operation(); }
    catch (InvalidArgumentException) { return; }
    throw new RuntimeException($message);
};

$catalog = (new HandlerRegistry())->signalCatalog();
$keys = array_column($catalog, 'key');
$assert($keys === ['feed.published', 'messages.new', 'messages.read', 'notifications.new'], 'signal catalog must match registered handlers');
foreach ($catalog as $entry) {
    $assert($entry['handler_key'] !== '' && $entry['topic'] !== '' && is_bool($entry['default_enabled']), 'every signal needs a handler, topic, and default');
}

$store = new ThinkDbSignalPolicyStore();
$audit = static function (): void { throw new RuntimeException('invalid changes reached audit'); };
$rejects(static fn() => $store->applyChanges($catalog, [['key' => 'unregistered', 'enabled' => true]], 1, 7, $audit), 'unknown signal must be rejected before storage');
$rejects(static fn() => $store->applyChanges($catalog, [['key' => 'messages.new', 'enabled' => true], ['key' => 'messages.new', 'enabled' => false]], 1, 7, $audit), 'duplicate signal must be rejected before storage');
$rejects(static fn() => $store->applyChanges($catalog, [['key' => 'messages.new', 'enabled' => 1]], 1, 7, $audit), 'non-boolean switch must be rejected before storage');
$rejects(static fn() => $store->applyChanges($catalog, [], 1, 7, $audit), 'empty change set must be rejected');
$rejects(static fn() => $store->enabled('unregistered', $catalog), 'unknown lookup must be rejected before storage');

fwrite(STDOUT, "PASS signal policy catalog and validation baseline\n");
