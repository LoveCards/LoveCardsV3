<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/app/shared/Resource/ResourceRef.php';
require $root . '/app/shared/Transport/Http/CursorPage.php';
require $root . '/app/modules/Platform/Notifications/Contract/NotificationTypeCatalog.php';
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
    public array $activeUsers = [2 => true];
    public array $systemOnlyUsers = [3 => true];
    public function recipientCanReceive(int $userId, string $type): bool
    {
        return isset($this->activeUsers[$userId]) || (str_starts_with($type, 'system.') && isset($this->systemOnlyUsers[$userId]));
    }
    public function create(NotificationIntent $intent): int { $key = $intent->recipientId() . ':' . $intent->dedupeKey(); if (isset($this->items[$key])) return $this->items[$key]['id']; $id = count($this->items) + 1; $this->items[$key] = ['id' => $id, 'read' => false]; return $id; }
    public ?string $lastType = null;
    public ?string $lastCategory = null;
    public function listForUser(int $userId, ?string $cursor, int $limit, bool $unreadOnly, ?string $type, ?string $category): array { $this->lastType = $type; $this->lastCategory = $category; return ['items' => array_values($this->items), 'next_cursor' => null, 'has_more' => false]; }
    public function markRead(int $userId, array $ids): int { $changed = 0; foreach ($this->items as &$item) if (in_array($item['id'], $ids, true) && !$item['read']) { $item['read'] = true; $changed++; } return $changed; }
    public function markAllRead(int $userId, ?string $category = null): int { return $this->markRead($userId, array_column($this->items, 'id')); }
    public function unreadCount(int $userId, ?string $category = null): int { return count(array_filter($this->items, static fn(array $item): bool => !$item['read'])); }
    public function preferences(int $userId): array { return []; }
    public function savePreferences(int $userId, array $preferences): void {}
}

$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$repository = new FakeNotificationRepository();
$notifications = new ManageNotifications($repository);
$intent = new NotificationIntent(2, 'follow:1:2', 'social.followed', 1, new ResourceRef('user', 1));

$assert($notifications->create($intent) === 1, 'first notification should be created');
$assert($notifications->create($intent) === 1, 'same dedupe key should return existing notification');
$assert($notifications->create(new NotificationIntent(2, 'self:2', 'content.card_liked', 2)) === 0, 'self notification should be suppressed');
$assert($notifications->create(new NotificationIntent(3, 'inactive:3', 'content.card_liked', 1)) === 0, 'restricted recipient engagement should be suppressed');
$assert($notifications->unreadCount(2) === 1, 'notification should start unread');
$assert($notifications->markRead(2, [1]) === 1, 'mark read should update owned notification');
$assert($notifications->unreadCount(2) === 0, 'read notification should leave unread count');
$assert($notifications->list(2)['has_more'] === false, 'list should use cursor contract');
$notifications->list(2, null, 20, false, 'message');
$assert($repository->lastType === 'message', 'notification list should pass the requested type to persistence');
$notifications->list(2, null, 20, false, null, 'interaction');
$assert($repository->lastCategory === 'interaction', 'notification list should pass the requested category to persistence');
$notifications->list(2, null, 20, false, null, 'engagement');
$assert($repository->lastCategory === 'engagement', 'notification list should pass the engagement category to persistence');
$invalidCategoryRejected = false;
try { $notifications->list(2, null, 20, false, null, 'unknown'); } catch (InvalidArgumentException) { $invalidCategoryRejected = true; }
$assert($invalidCategoryRejected, 'notification list should reject unknown category');
$invalidTypeRejected = false;
try { new NotificationIntent(2, 'custom:2', 'custom.foo', 1); } catch (InvalidArgumentException) { $invalidTypeRejected = true; }
$assert($invalidTypeRejected, 'new notifications should reject a type outside the public catalog');
$invalidTypeFilterRejected = false;
try { $notifications->list(2, null, 20, false, 'custom.foo'); } catch (InvalidArgumentException) { $invalidTypeFilterRejected = true; }
$assert($invalidTypeFilterRejected, 'notification list should reject a type outside the public catalog');
$assert($notifications->create(new NotificationIntent(3, 'system:restricted:3', 'system.moderation', null)) === 2, 'restricted recipient system notice should be persisted for later access');

fwrite(STDOUT, "PASS notification behavior baseline\n");
