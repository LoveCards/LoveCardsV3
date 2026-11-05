<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/modules/Platform/Notifications/Contract/NotificationRepository.php';
require dirname(__DIR__, 2) . '/app/modules/Platform/Notifications/Contract/NotificationReceiptRepository.php';
require dirname(__DIR__, 2) . '/app/modules/Platform/Notifications/Contract/NotificationTypeCatalog.php';
require dirname(__DIR__, 2) . '/app/modules/Platform/Notifications/Domain/NotificationIntent.php';
require dirname(__DIR__, 2) . '/app/modules/Platform/Notifications/Domain/NotificationWriteReceipt.php';
require dirname(__DIR__, 2) . '/app/modules/Platform/Eventing/Contract/RealtimePublisher.php';
require dirname(__DIR__, 2) . '/app/modules/Platform/Eventing/Domain/RealtimeSignal.php';
require dirname(__DIR__, 2) . '/app/modules/Platform/Notifications/Application/ManageNotifications.php';

use app\modules\Platform\Eventing\Contract\RealtimePublisher;
use app\modules\Platform\Eventing\Domain\RealtimeSignal;
use app\modules\Platform\Notifications\Application\ManageNotifications;
use app\modules\Platform\Notifications\Contract\NotificationReceiptRepository;
use app\modules\Platform\Notifications\Domain\NotificationIntent;
use app\modules\Platform\Notifications\Domain\NotificationWriteReceipt;

final class RealtimeNotificationFakeRepository implements NotificationReceiptRepository
{
    public int $nextId = 41;
    private bool $created = false;
    public function recipientCanReceive(int $userId, string $type): bool { return true; }
    public function create(NotificationIntent $intent): int { return $this->createWithReceipt($intent)->id(); }
    public function createWithReceipt(NotificationIntent $intent): NotificationWriteReceipt
    {
        if ($this->created) return new NotificationWriteReceipt($this->nextId, false);
        $this->created = true;
        return new NotificationWriteReceipt($this->nextId, true);
    }
    public function listForUser(int $userId, ?string $cursor, int $limit, bool $unreadOnly, ?string $type, ?string $category): array { return ['items' => [], 'next_cursor' => null, 'has_more' => false]; }
    public function markRead(int $userId, array $ids): int { return 0; }
    public function markAllRead(int $userId, ?string $category = null): int { return 0; }
    public function unreadCount(int $userId, ?string $category = null): int { return 0; }
    public function preferences(int $userId): array { return []; }
    public function savePreferences(int $userId, array $preferences): void {}
}

final class RealtimeNotificationFakePublisher implements RealtimePublisher
{
    /** @var RealtimeSignal[] */
    public array $signals = [];
    public function publish(RealtimeSignal $signal): void { $this->signals[] = $signal; }
}

$repo = new RealtimeNotificationFakeRepository();
$publisher = new RealtimeNotificationFakePublisher();
$manager = new ManageNotifications($repo, null, $publisher);
$intent = new NotificationIntent(2, 'message:41', 'message', 1);
$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };

$assert($manager->create($intent) === 41, 'first notification should return its id');
$assert(count($publisher->signals) === 1, 'first notification should publish one realtime signal');
$assert($publisher->signals[0]->topic() === 'notifications', 'signal topic must be notifications');
$assert($publisher->signals[0]->recipientKey() === 'user:2', 'signal recipient must be scoped to the recipient');
$assert($manager->create($intent) === 41, 'deduplicated notification should return its existing id');
$assert(count($publisher->signals) === 1, 'deduplicated notification must not publish a second signal');

$failingPublisher = new class implements RealtimePublisher {
    public function publish(RealtimeSignal $signal): void { throw new RuntimeException('gateway unavailable'); }
};
$freshRepository = new RealtimeNotificationFakeRepository();
$assert((new ManageNotifications($freshRepository, null, $failingPublisher))->create(new NotificationIntent(3, 'message:42', 'message', 1)) === 41, 'failed realtime transport must not fail notification creation');

fwrite(STDOUT, "Realtime notification signal baseline passed.\n");
