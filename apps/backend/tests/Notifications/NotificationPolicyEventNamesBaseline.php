<?php
declare(strict_types=1);

/**
 * Locks the notification policy event vocabulary to the event names emitted by
 * HandlerRegistry. The migration must advance the current snapshot instead of
 * editing the historical Phase 3 seed in place.
 */

$root = dirname(__DIR__, 2);
$migrationPath = $root . '/database/migrations/20261107000001.sql';
$migration = file_get_contents($migrationPath);
if ($migration === false) throw new RuntimeException('notification policy normalization migration is unreadable');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$registry = file_get_contents($root . '/app/modules/Platform/Eventing/Public/HandlerRegistry.php');
if ($registry === false) throw new RuntimeException('handler registry is unreadable');

foreach (['library.saved', 'content.comment.published', 'content.mentions.added'] as $eventType) {
    $assert(str_contains($registry, "'{$eventType}'"), "handler registry must emit {$eventType}");
    $assert(str_contains($migration, "'{$eventType}'"), "normalization migration must produce {$eventType}");
}

foreach (['content.saved', 'comment.published', 'mentions.added'] as $legacyEventType) {
    $assert(str_contains($migration, "WHEN '{$legacyEventType}'"), "normalization migration must map {$legacyEventType}");
}

$assert(str_contains($migration, "`source` = 'system'"), 'normalization must be identifiable for safe replay');
$assert(str_contains($migration, 'START TRANSACTION'), 'policy normalization must be transactional');
$assert(str_contains($migration, 'ORDER BY `policy_revision` DESC'), 'normalization must lock the latest policy revision');
$assert(str_contains($migration, 'FOR UPDATE'), 'normalization must serialize with policy CAS writes');
$assert(str_contains($migration, 'SIGNAL SQLSTATE'), 'conflicting policy rows must fail closed');

fwrite(STDOUT, "PASS notification policy event names baseline\n");
