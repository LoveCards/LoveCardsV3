<?php
declare(strict_types=1);

namespace think\facade
{
    final class Db
    {
        public static bool $active = false;
        public static int $commits = 0;
        public static int $rollbacks = 0;

        public static function table(string $table): object {
            if ($table !== 'cards') throw new \RuntimeException('unexpected table');
            return new class {
                private int $id = 0;
                public function where(string $field, mixed $value): self { if ($field === 'cards.id') $this->id = (int) $value; return $this; }
                public function whereNull(string $field): self { return $this; }
                public function whereRaw(string $sql): self { return $this; }
                public function find(): ?array {
                    foreach (\app\modules\Content\Infrastructure\Persistence\Model\Cards::$rows as $row) {
                        if ((int) $row['id'] === $this->id && (int) $row['status'] === 0) return $row;
                    }
                    return null;
                }
            };
        }

        public static function startTrans(): void { self::$active = true; }
        public static function commit(): void { self::$commits++; self::$active = false; }
        public static function rollback(): void { self::$rollbacks++; self::$active = false; }
    }
}

namespace app\api
{
    class ApiException extends \RuntimeException
    {
        public const CODE_RESOURCE_NOT_FOUND = 'RESOURCE_NOT_FOUND';
        public const CODE_PARAM_INVALID = 'PARAM_INVALID';

        public static function badRequest(string $message, string $businessCode = ''): self { return new self($message); }
        public static function notFound(string $message, string $businessCode = ''): self { return new self($message); }
    }
}

namespace app\modules\Platform\Eventing\Public
{
    use think\facade\Db;

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
            if (!Db::$active) throw new \RuntimeException('like event must be published inside the business transaction');
            $this->events[] = compact('type', 'aggregateType', 'aggregateKey', 'payload', 'actorId');
            return new \stdClass();
        }
    }
}

namespace app\modules\Content\Infrastructure\Persistence\Model
{
    final class ContentRecord
    {
        /** @param array<string, mixed>|null $row */
        public function __construct(private ?array $row) {}
        public function isEmpty(): bool { return $this->row === null; }
        public function __get(string $field): mixed { return $this->row[$field] ?? null; }
    }

    final class ContentTargetQuery
    {
        /** @var array<int, array{0:string,1:string,2:mixed}> */
        private array $conditions = [];
        private ?string $counter = null;

        public function __construct(private string $model, string $field, mixed $value)
        {
            $this->conditions[] = [$field, '=', $value];
        }

        public function where(string $field, mixed $operator, mixed $value = null): self
        {
            if (func_num_args() === 2) {
                $value = $operator;
                $operator = '=';
            }
            $this->conditions[] = [$field, (string) $operator, $value];
            return $this;
        }

        public function lock(bool $lock): self { return $this; }
        public function inc(string $field): self { $this->counter = '+' . $field; return $this; }
        public function dec(string $field): self { $this->counter = '-' . $field; return $this; }

        public function findOrEmpty(): ContentRecord
        {
            foreach ($this->rows() as $row) {
                if ($this->matches($row)) return new ContentRecord($row);
            }
            return new ContentRecord(null);
        }

        public function update(array $data = []): int
        {
            $rows =& $this->rows();
            foreach ($rows as &$row) {
                if (!$this->matches($row)) continue;
                if ($this->counter !== null) {
                    $field = substr($this->counter, 1);
                    $delta = $this->counter[0] === '+' ? 1 : -1;
                    $row[$field] = (int) ($row[$field] ?? 0) + $delta;
                }
                foreach ($data as $field => $value) $row[$field] = $value;
                return 1;
            }
            return 0;
        }

        /** @return array<int, array<string, mixed>> */
        private function &rows(): array
        {
            if ($this->model === Cards::class) return Cards::$rows;
            return Comments::$rows;
        }

        /** @param array<string, mixed> $row */
        private function matches(array $row): bool
        {
            foreach ($this->conditions as [$field, $operator, $value]) {
                $actual = $row[$field] ?? null;
                if ($operator === '=' && $actual !== $value) return false;
                if ($operator === '>' && !($actual > $value)) return false;
            }
            return true;
        }
    }

    class Cards
    {
        /** @var array<int, array<string, mixed>> */
        public static array $rows = [];
        public static function where(string $field, mixed $value): ContentTargetQuery { return new ContentTargetQuery(self::class, $field, $value); }
    }

    class Comments
    {
        /** @var array<int, array<string, mixed>> */
        public static array $rows = [];
        public static function where(string $field, mixed $value): ContentTargetQuery { return new ContentTargetQuery(self::class, $field, $value); }
    }

    final class LikeRecord
    {
        public function __construct(private string $key) {}
        public function delete(): void { unset(Likes::$rows[$this->key]); }
    }

    final class LikeCollection
    {
        /** @param array<int, array<string, mixed>> $rows */
        public function __construct(private array $rows) {}
        public function toArray(): array { return $this->rows; }
    }

    final class LikeQuery
    {
        /** @var array<string, mixed> */
        private array $conditions = [];

        public function __construct(string $field, mixed $value) { $this->conditions[$field] = $value; }
        public function where(string $field, mixed $value): self { $this->conditions[$field] = $value; return $this; }

        public function find(): ?LikeRecord
        {
            foreach (Likes::$rows as $key => $row) {
                if ($this->matches($row)) return new LikeRecord($key);
            }
            return null;
        }

        public function count(): int
        {
            return count(array_filter(Likes::$rows, fn(array $row): bool => $this->matches($row)));
        }

        public function select(): LikeCollection
        {
            return new LikeCollection(array_values(array_filter(Likes::$rows, fn(array $row): bool => $this->matches($row))));
        }

        /** @param array<string, mixed> $row */
        private function matches(array $row): bool
        {
            foreach ($this->conditions as $field => $value) {
                if (($row[$field] ?? null) !== $value) return false;
            }
            return true;
        }
    }

    class Likes
    {
        /** @var array<string, array<string, mixed>> */
        public static array $rows = [];

        public static function where(string $field, mixed $value): LikeQuery { return new LikeQuery($field, $value); }
        public static function create(array $row): void
        {
            $key = $row['ref_type'] . ':' . $row['ref_id'] . ':' . $row['uid'];
            self::$rows[$key] = $row;
        }
    }
}

namespace
{
    $root = dirname(__DIR__, 2);
    require $root . '/app/modules/Content/Cards/Service/CardVisibilityPolicy.php';
    require $root . '/app/modules/Content/Cards/Public/CardAccess.php';
    require $root . '/app/modules/Content/Likes/Service/Likes.php';

    use app\api\ApiException;
    use app\modules\Content\Infrastructure\Persistence\Model\Cards;
    use app\modules\Content\Infrastructure\Persistence\Model\Comments;
    use app\modules\Content\Likes\Service\Likes;
    use app\modules\Platform\Eventing\Public\DomainEventBus;

    final class LikeEventContainer
    {
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
        if ($class === DomainEventBus::class) return LikeEventContainer::$events;
        if (in_array($class, ['app\\modules\\Platform\\Eventing\\Public\\RealtimeSignalPublisher', 'app\\modules\\Platform\\Notifications\\Contract\\NotificationPolicyStore'], true)) {
            return \FakeCommunicationPorts::instance($class);
        }
        throw new RuntimeException('Unexpected container lookup: ' . $class);
}

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };

    LikeEventContainer::$events = new DomainEventBus();
    Cards::$rows[10] = ['id' => 10, 'status' => 0, 'user_id' => 2, 'goods' => 0];
    Comments::$rows[20] = ['id' => 20, 'pid' => 10, 'status' => 0, 'user_id' => 4, 'goods' => 0];

    $assert(Likes::like('card', 10, 1, '127.0.0.1') === 1, 'first card like should be created');
    $assert(count(LikeEventContainer::$events->events) === 1, 'first card like should publish one event');
    $cardEvent = LikeEventContainer::$events->events[0];
    $assert($cardEvent['type'] === 'content.liked', 'like event type mismatch');
    $assert($cardEvent['payload'] === [
        'actor_id' => 1,
        'target_type' => 'card',
        'target_id' => 10,
        'owner_id' => 2,
        'card_id' => 10,
    ], 'card event metadata mismatch');

    try {
        Likes::like('card', 10, 1, '127.0.0.1');
        throw new RuntimeException('duplicate like should be rejected');
    } catch (ApiException) {
    }
    $assert(count(LikeEventContainer::$events->events) === 1, 'duplicate like must not publish an event');

    Likes::unlike('card', 10, 1);
    $assert(count(LikeEventContainer::$events->events) === 1, 'unlike must not publish an event');
    $assert(Likes::like('card', 10, 1, '127.0.0.1') === 1, 'like should be possible after unlike');
    $assert(count(LikeEventContainer::$events->events) === 2, 're-like should publish a new fact for business-key dedupe');

    $assert(Likes::like('comment', 20, 3, '127.0.0.1') === 1, 'first comment like should be created');
    $commentEvent = LikeEventContainer::$events->events[2];
    $assert($commentEvent['aggregateType'] === 'comment' && $commentEvent['aggregateKey'] === '20', 'comment aggregate mismatch');
    $assert($commentEvent['payload'] === [
        'actor_id' => 3,
        'target_type' => 'comment',
        'target_id' => 20,
        'owner_id' => 4,
        'card_id' => 10,
        'comment_id' => 20,
    ], 'comment event metadata mismatch');

    fwrite(STDOUT, "PASS like notification event baseline\n");



}
