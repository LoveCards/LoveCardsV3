<?php
declare(strict_types=1);

namespace app\api
{
    class ApiException extends \RuntimeException
    {
    }
}

namespace app\modules\Content\Cards\Public
{
    use app\api\ApiException;

    final class Cards
    {
        /** @var array<int, array<string, mixed>> */
        public static array $rows = [];

        public static function get(int $id, array $caps = []): array
        {
            if (!isset(self::$rows[$id])) throw new ApiException('卡片不存在');
            return self::$rows[$id];
        }
    }
}

namespace app\modules\Platform\Eventing\Public
{
    use LibraryEventFakeTransaction as Transaction;

    final class DomainEventBus
    {
        /** @var array<int, array<string, mixed>> */
        public array $events = [];

        public function publish(
            string $type,
            string $aggregateType,
            string $aggregateKey,
            array $payload,
            ?int $actorId = null
        ): object {
            if (!Transaction::$active) throw new \RuntimeException('event must be recorded inside the save transaction');
            $this->events[] = compact('type', 'aggregateType', 'aggregateKey', 'payload', 'actorId');
            return new \stdClass();
        }
    }
}

namespace
{
    $root = dirname(__DIR__, 2);
    require $root . '/app/shared/Resource/ResourceRef.php';
    require $root . '/app/shared/Transport/Http/CursorPage.php';
    require $root . '/app/shared/Transaction/TransactionManager.php';
    require $root . '/app/modules/Platform/Library/Contract/LibraryRepository.php';
    require $root . '/app/modules/Platform/Library/Application/ManageLibrary.php';
    require $root . '/app/modules/Platform/Library/Public/Library.php';

    use app\modules\Content\Cards\Public\Cards;
    use app\modules\Platform\Eventing\Public\DomainEventBus;
    use app\modules\Platform\Library\Contract\LibraryRepository;
    use app\modules\Platform\Library\Public\Library;
    use app\shared\Resource\ResourceRef;
    use app\shared\Transaction\TransactionManager;

    final class LibraryEventFakeTransaction implements TransactionManager
    {
        public static bool $active = false;
        public int $runs = 0;

        public function run(callable $callback): mixed
        {
            $this->runs++;
            self::$active = true;
            try {
                return $callback();
            } finally {
                self::$active = false;
            }
        }
    }

    final class LibraryEventFakeRepository implements LibraryRepository
    {
        /** @var array<string, bool> */
        public array $saved = [];

        public function save(int $userId, ResourceRef $resource): bool
        {
            $key = $userId . ':' . $resource->key();
            if (isset($this->saved[$key])) return false;
            $this->saved[$key] = true;
            return true;
        }

        public function remove(int $userId, ResourceRef $resource): bool { return false; }
        public function recordView(int $userId, ResourceRef $resource): void {}
        public function listSaved(int $userId, ?string $cursor, int $limit): array { return ['items' => [], 'next_cursor' => null, 'has_more' => false]; }
        public function listHistory(int $userId, ?string $cursor, int $limit): array { return ['items' => [], 'next_cursor' => null, 'has_more' => false]; }
        public function clearHistory(int $userId): int { return 0; }
    }

    final class LibraryEventContainer
    {
        public static LibraryEventFakeTransaction $transactions;
        public static LibraryEventFakeRepository $repository;
        public static DomainEventBus $events;
    }

    if (!class_exists('FakeCommunicationPorts')) {
            require_once __DIR__ . '/../../app/modules/Platform/Eventing/Public/RealtimeSignal.php';
            require_once __DIR__ . '/../../app/modules/Platform/Eventing/Public/RealtimeSignalPublisher.php';
            require_once __DIR__ . '/../../app/modules/Platform/Notifications/Contract/NotificationPolicyStore.php';
            final class FakeCommunicationPorts
            {
                private static $instances = [];
                public static function instance(string $class): object
                {
                    return self::$instances[$class] ??= new class implements
                        \app\modules\Platform\Eventing\Public\RealtimeSignalPublisher,
                        \app\modules\Platform\Notifications\Contract\NotificationPolicyStore {
                        public function publish(\app\modules\Platform\Eventing\Public\RealtimeSignal $signal): void {}
                        public function snapshot(int $policyRevision): ?array { return null; }
                        public function currentRevision(): ?int { return null; }
                        public function listCurrent(): array { return []; }
                        public function applyChanges(array $changes, int $expectedRevision, ?int $actorId, ?string $reason, callable $audit): array
                        { return ['revision' => $expectedRevision, 'policies' => [], 'before' => []]; }
                    };
                }
            }
        }

    function app(string $class): object
    {
            if (in_array($class, ['app\\modules\\Platform\\Eventing\\Public\\RealtimeSignalPublisher', 'app\\modules\\Platform\\Notifications\\Contract\\NotificationPolicyStore'], true)) {
                return \FakeCommunicationPorts::instance($class);
            }
        return match ($class) {
            TransactionManager::class => LibraryEventContainer::$transactions,
            LibraryRepository::class => LibraryEventContainer::$repository,
            DomainEventBus::class => LibraryEventContainer::$events,
            default => throw new RuntimeException('Unexpected container lookup: ' . $class),
        };
}

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };

    LibraryEventContainer::$transactions = new LibraryEventFakeTransaction();
    LibraryEventContainer::$repository = new LibraryEventFakeRepository();
    LibraryEventContainer::$events = new DomainEventBus();
    Cards::$rows[42] = ['id' => 42, 'user_id' => 9, 'status' => 0];

    $resource = new ResourceRef('card', 42);
    $assert(Library::save(3, $resource), 'first save should create a library item');
    $assert(!Library::save(3, $resource), 'repeated save should remain idempotent');
    $assert(LibraryEventContainer::$transactions->runs === 2, 'each save should use TransactionManager');
    $assert(count(LibraryEventContainer::$events->events) === 1, 'repeated save must not publish another event');

    $event = LibraryEventContainer::$events->events[0];
    $assert($event['type'] === 'library.saved', 'save should reuse library.saved');
    $assert($event['aggregateType'] === 'card' && $event['aggregateKey'] === '42', 'saved aggregate mismatch');
    $assert($event['actorId'] === 3, 'saved actor mismatch');
    $assert($event['payload'] === [
        'user_id' => 3,
        'actor_id' => 3,
        'card_id' => 42,
        'owner_id' => 9,
    ], 'saved event must carry authoritative card metadata');

    fwrite(STDOUT, "PASS library event baseline\n");



}
