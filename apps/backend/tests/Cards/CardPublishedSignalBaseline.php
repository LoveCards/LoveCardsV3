<?php

declare(strict_types=1);

namespace think\facade {
    final class Db
    {
        public static array $card = ['id' => 10, 'user_id' => 7, 'status' => 0, 'visibility' => 'public', 'deleted_at' => null];
        public static array $followers = [];
        public static array $continuations = [];
        public static string $lastFollowerSql = '';
        public static function table(string $table): object
        {
            return new class($table) {
                private array $filters = [];
                public function __construct(private string $table) {}
                public function where(string $field, mixed $value): self { $this->filters[$field] = $value; return $this; }
                public function whereNull(string $field): self { $this->filters[$field] = null; return $this; }
                public function field(string $field): self { return $this; }
                public function lock(bool $lock): self { return $this; }
                public function find(): ?array
                {
                    if ($this->table === 'cards') return Db::$card;
                    if ($this->table === 'users') return ['id' => 7, 'status' => 0];
                    if ($this->table === 'privacy_preferences') return ['profile_visibility' => 'public'];
                    if ($this->table === 'event_deliveries') return ['id' => $this->filters['id'] ?? 0];
                    if ($this->table === 'outbox_events') return Db::$continuations[$this->filters['aggregate_key'] ?? ''] ?? null;
                    return null;
                }
            };
        }
        public static function query(string $sql, array $params): array
        {
            self::$lastFollowerSql = $sql;
            $after = (int) ($params[1] ?? 0);
            return array_map(static fn(int $id): array => ['follower_id' => $id],
                array_slice(array_values(array_filter(self::$followers, static fn(int $id): bool => $id > $after)), 0, 201));
        }
        public static function transaction(callable $callback): mixed { return $callback(); }
    }
}

namespace app\modules\Platform\Eventing\Public {
    final class SignalPolicies { public static bool $enabled = true; public static function enabled(string $key): bool { return self::$enabled; } }
    final class DomainEventBus
    {
        public array $published = [];
        public function publish(string $type, string $aggregateType, string $aggregateKey, array $payload, ?int $actorId = null): object
        {
            $this->published[] = [$type, $aggregateKey, $payload];
            \think\facade\Db::$continuations[$aggregateKey] = ['aggregate_key' => $aggregateKey];
            return new \stdClass();
        }
    }
}

namespace app\modules\Operations\Runtime\Public {
    final class RuntimeCommunication { public string $state = 'deliver'; public function signalDeliveryState(): string { return $this->state; } }
}

namespace {
    $root = dirname(__DIR__, 2);
    require $root . '/vendor/autoload.php';
    $runtime = new \app\modules\Operations\Runtime\Public\RuntimeCommunication();
    $bus = new \app\modules\Platform\Eventing\Public\DomainEventBus();
    $publisher = new class implements \app\modules\Platform\Eventing\Public\RealtimeSignalPublisher {
        public array $signals = [];
        public function publish(\app\modules\Platform\Eventing\Public\RealtimeSignal $signal): void { $this->signals[] = $signal; }
    };
    function app(string $class): object
    {
        global $runtime, $bus, $publisher;
        return match ($class) {
            \app\modules\Operations\Runtime\Public\RuntimeCommunication::class => $runtime,
            \app\modules\Platform\Eventing\Public\DomainEventBus::class => $bus,
            \app\modules\Platform\Eventing\Public\RealtimeSignalPublisher::class => $publisher,
            default => throw new \RuntimeException('unexpected container lookup: ' . $class),
        };
    }
    $assert = static function (bool $ok, string $message): void { if (!$ok) throw new \RuntimeException($message); };
    $handler = new \app\modules\Content\Cards\Infrastructure\CardPublishedSignalHandler();
    $event = static fn(string $type, int $after = 0): \app\modules\Platform\Eventing\Public\EventEnvelope =>
        new \app\modules\Platform\Eventing\Public\EventEnvelope($type, 1, 'card', '10', [
            'card_id' => 10, 'owner_id' => 7, 'visibility' => \think\facade\Db::$card['visibility'],
            'root_event_id' => 20, 'after_follower_id' => $after,
        ], new \DateTimeImmutable('now', new \DateTimeZone('UTC')), 7, 'feed-test');
    $context = static fn(int $id = 30): \app\modules\Platform\Eventing\Public\DeliveryContext =>
        new \app\modules\Platform\Eventing\Public\DeliveryContext($id, 20, 'realtime.card-published', 1);

    $handler->handle($event('content.card.published'), $context());
    $assert(count($publisher->signals) === 1 && $publisher->signals[0]->recipientKey() === 'all', 'public card should broadcast only public hint');
    $publisher->signals = [];
    \think\facade\Db::$card['visibility'] = 'followers';
    \think\facade\Db::$followers = range(1, 201);
    $handler->handle($event('content.card.published'), $context());
    $assert(count($publisher->signals) === 1 && count($publisher->signals[0]->recipientKeys()) === 200, 'first follower batch should be capped at 200');
    $assert(str_contains(\think\facade\Db::$lastFollowerSql, 'u.status = 0 AND u.deleted_at IS NULL')
        && str_contains(\think\facade\Db::$lastFollowerSql, "b.relation = 'block'")
        && str_contains(\think\facade\Db::$lastFollowerSql, 'f.follower_id > ?')
        && str_contains(\think\facade\Db::$lastFollowerSql, 'LIMIT 201'), 'batch query must filter disabled, blocked, and prior followers');
    $assert(count($bus->published) === 1 && $bus->published[0][2]['after_follower_id'] === 200, 'next batch should persist cursor');
    $publisher->signals = [];
    $handler->handle($event('content.card.feed.batch.requested', 200), $context(31));
    $assert(count($publisher->signals) === 1 && $publisher->signals[0]->recipientKeys() === ['user:201'],
        'continuation should deliver only the next follower');
    $handler->handle($event('content.card.published'), $context());
    $assert(count($bus->published) === 1, 'retry should not duplicate continuation');
    $publisher->signals = [];
    \think\facade\Db::$card['visibility'] = 'private';
    $privateEvent = new \app\modules\Platform\Eventing\Public\EventEnvelope('content.card.published', 1, 'card', '10', [
        'card_id' => 10, 'owner_id' => 7, 'visibility' => 'followers',
    ], new \DateTimeImmutable('now', new \DateTimeZone('UTC')), 7, 'feed-test');
    $handler->handle($privateEvent, $context());
    $assert($publisher->signals === [], 'private transition must not signal');
    fwrite(STDOUT, "PASS card published signal routing and bounded continuation\n");
}
