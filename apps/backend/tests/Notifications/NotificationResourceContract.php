<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/app/shared/Resource/ResourceRef.php';
require $root . '/app/shared/Transport/Http/Resource.php';
require $root . '/app/modules/Platform/Notifications/Domain/NotificationIntent.php';
require $root . '/app/modules/Platform/Notifications/Contract/NotificationRepository.php';
require $root . '/app/modules/Platform/Notifications/Contract/NotificationTypeCatalog.php';
require $root . '/app/modules/Platform/Notifications/Infrastructure/ThinkOrmNotificationRepository.php';
require $root . '/app/modules/Platform/Notifications/Transport/Api/NotificationItemResource.php';
require $root . '/app/modules/Platform/Notifications/Transport/Api/NotificationCursorPageResource.php';
require $root . '/app/modules/Platform/Notifications/Transport/Api/NotificationUnreadResource.php';

use app\modules\Platform\Notifications\Contract\NotificationTypeCatalog;
use app\modules\Platform\Notifications\Infrastructure\ThinkOrmNotificationRepository;
use app\modules\Platform\Notifications\Transport\Api\NotificationCursorPageResource;
use app\modules\Platform\Notifications\Transport\Api\NotificationUnreadResource;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$page = NotificationCursorPageResource::make([
    'data' => [[
        'id' => '41',
        'recipient_id' => '7',
        'type' => 'content.replied',
        'actor_id' => '12',
        'actor' => ['uid' => '123456789012', 'username' => 'reply_user', 'avatar' => 'https://example.test/a.png', 'private' => 'hidden'],
        'actor_state' => 'active',
        'resource_type' => 'comment',
        'resource_id' => '91',
        'resource_state' => 'available',
        'action_url' => '/post/4/thread/91',
        'payload' => ['card_id' => 4, 'comment_id' => 91],
        'dedupe_key' => 'reply:91:12',
        'read_at' => null,
        'created_at' => '2026-08-21 00:00:00',
        'internal_only' => 'must not leak',
    ]],
    'next_cursor' => null,
    'has_more' => false,
]);

$item = $page['data'][0];
$assert($item['category'] === 'interaction', 'notification resource should derive a stable category');
$assert($item['actor'] === ['uid' => '123456789012', 'username' => 'reply_user', 'avatar' => 'https://example.test/a.png'], 'notification resource should expose only public actor fields');
$assert($item['action_url'] === '/post/4/thread/91', 'notification resource should retain canonical internal action URL');
$assert(!array_key_exists('internal_only', $item), 'notification resource should whitelist fields');
$assert(!array_key_exists('recipient_id', $item) && !array_key_exists('dedupe_key', $item), 'notification resource should hide recipient and idempotency internals');
$assert($page['next_cursor'] === null && $page['has_more'] === false, 'notification cursor envelope should remain stable');
$unread = NotificationUnreadResource::make(['unread' => '4', 'internal_only' => true]);
$assert($unread === ['unread' => 4], 'notification unread resource should expose a numeric unread count only');
$assert(NotificationUnreadResource::schema()['required'] === ['unread'], 'notification unread schema should require unread');

$schema = NotificationCursorPageResource::schema();
$itemSchema = $schema['properties']['data']['items'] ?? [];
$assert(isset($itemSchema['properties']['actor'], $itemSchema['properties']['resource_state'], $itemSchema['properties']['action_url']), 'notification item schema should expose actor and resource presentation fields');
$assert(($itemSchema['properties']['type']['enum'] ?? []) === NotificationTypeCatalog::types(), 'notification type schema should be generated from the catalog');
$assert(($itemSchema['properties']['category']['enum'] ?? []) === NotificationTypeCatalog::CATEGORIES, 'notification category schema should be generated from the catalog');

$invalidPageRejected = false;
try { NotificationCursorPageResource::make(['data' => [], 'next_cursor' => 5, 'has_more' => false]); } catch (UnexpectedValueException) { $invalidPageRejected = true; }
$assert($invalidPageRejected, 'notification resource should reject malformed cursor envelopes');
$unknownTypeRejected = false;
try {
    NotificationCursorPageResource::make([
        'data' => [['id' => 42, 'type' => 'custom.foo', 'created_at' => '2026-08-21 00:00:00']],
        'next_cursor' => null,
        'has_more' => false,
    ]);
} catch (UnexpectedValueException) {
    $unknownTypeRejected = true;
}
$assert($unknownTypeRejected, 'notification resource should never expose a type outside the public catalog');

$safePath = new ReflectionMethod(ThinkOrmNotificationRepository::class, 'safeInternalPath');
$safePath->setAccessible(true);
$repository = new ThinkOrmNotificationRepository();
$assert($safePath->invoke($repository, '/settings/security?tab=sessions#active') === '/settings/security?tab=sessions#active', 'read model should retain a valid internal action path');
foreach (['https://evil.example', '//evil.example', '/safe\\evil', "/safe\nbad", '/https://evil.example', '/%2Fevil.example'] as $unsafePath) {
    $assert($safePath->invoke($repository, $unsafePath) === null, 'read model should suppress unsafe action paths');
}

fwrite(STDOUT, "PASS notification resource contract\n");
