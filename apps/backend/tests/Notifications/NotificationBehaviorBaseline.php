<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/app/shared/Resource/ResourceRef.php';
require $root . '/app/shared/Transport/Http/CursorPage.php';
require $root . '/app/modules/Platform/Notifications/Domain/NotificationIntent.php';
require $root . '/app/modules/Platform/Notifications/Contract/NotificationRepository.php';
require $root . '/app/modules/Platform/Notifications/Application/ManageNotifications.php';

use app\modules\Platform\Notifications\Application\ManageNotifications;
use app\modules\Platform\Notifications\Contract\NotificationRepository;
use app\modules\Platform\Notifications\Domain\NotificationIntent;
use app\shared\Resource\ResourceRef;

final class FakeNotificationRepository implements NotificationRepository
{
    public array $items = [];
    public function create(NotificationIntent $intent): int { $key = $intent->recipientId() . ':' . $intent->dedupeKey(); if (isset($this->items[$key])) return $this->items[$key]['id']; $id = count($this->items) + 1; $this->items[$key] = ['id' => $id, 'read' => false]; return $id; }
    public function listForUser(int $userId, ?string $cursor, int $limit, bool $unreadOnly): array { return ['items' => array_values($this->items), 'next_cursor' => null, 'has_more' => false]; }
    public function markRead(int $userId, array $ids): int { $changed = 0; foreach ($this->items as &$item) if (in_array($item['id'], $ids, true) && !$item['read']) { $item['read'] = true; $changed++; } return $changed; }
    public function markAllRead(int $userId): int { return $this->markRead($userId, array_column($this->items, 'id')); }
    public function unreadCount(int $userId): int { return count(array_filter($this->items, static fn(array $item): bool => !$item['read'])); }
}

$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$repository = new FakeNotificationRepository();
$notifications = new ManageNotifications($repository);
$intent = new NotificationIntent(2, 'follow:1:2', 'social.followed', 1, new ResourceRef('user', 1));

$assert($notifications->create($intent) === 1, 'first notification should be created');
$assert($notifications->create($intent) === 1, 'same dedupe key should return existing notification');
$assert($notifications->unreadCount(2) === 1, 'notification should start unread');
$assert($notifications->markRead(2, [1]) === 1, 'mark read should update owned notification');
$assert($notifications->unreadCount(2) === 0, 'read notification should leave unread count');
$assert($notifications->list(2)['has_more'] === false, 'list should use cursor contract');

fwrite(STDOUT, "PASS notification behavior baseline\n");
