<?php

declare(strict_types=1);

namespace app\api
{
    final class ApiException extends \RuntimeException
    {
        public const CODE_SYSTEM_ERROR = 500;
        public static function notFound(string $message): self { return new self($message, 404); }
        public static function badRequest(string $message): self { return new self($message, 400); }
        public static function error(string $message, int $code = 500, mixed $data = null, ?\Throwable $previous = null): self
        {
            return new self($message, $code, $previous);
        }
    }
}

namespace app\modules\Platform\Notifications\Domain
{
    final class SystemNotice { public const MODERATION = 'system.moderation'; }
}

namespace app\shared\Authorization
{
    final class GrantSet
    {
    }
}

namespace app\modules\Content\Comments\Service\Policy
{
    final class CommentPolicy
    {
        public static function assertCan(object $grants, string $operation, int $id, int $uid): void
        {
        }
    }
}

namespace think\facade
{
    final class Db
    {
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

        public static function startTrans(): void {}
        public static function commit(): void { self::$commits++; }
        public static function rollback(): void { self::$rollbacks++; }
    }
}

namespace app\modules\Content\Infrastructure\Persistence\Model
{
    final class FakeRecord
    {
        /** @param array<string,mixed>|null $row */
        public function __construct(private ?array $row)
        {
        }

        public function isEmpty(): bool { return $this->row === null; }
        /** @return array<string,mixed> */
        public function toArray(): array { return $this->row ?? []; }
        public function __isset(string $name): bool { return array_key_exists($name, $this->row ?? []); }
        public function __get(string $name): mixed { return $this->row[$name] ?? null; }
    }

    final class FakeCollection
    {
        /** @param array<int,array<string,mixed>> $rows */
        public function __construct(private array $rows)
        {
        }

        /** @return array<int,array<string,mixed>> */
        public function toArray(): array { return $this->rows; }
    }

    final class FakeQuery
    {
        /** @var array<int,array{kind:string,field:string,value:mixed}> */
        private array $filters = [];
        private ?string $orderField = null;

        public function __construct(private string $model)
        {
        }

        public function where(string $field, mixed $value): self
        {
            $this->filters[] = ['kind' => 'eq', 'field' => $field, 'value' => $value];
            return $this;
        }

        public function whereIn(string $field, array $values): self
        {
            $this->filters[] = ['kind' => 'in', 'field' => $field, 'value' => $values];
            return $this;
        }

        public function order(string $field, string $direction): self
        {
            $this->orderField = $field;
            return $this;
        }

        public function lock(bool $lock): self { return $this; }
        public function field(string $fields): self { return $this; }
        public function inc(string $field): self { return $this; }

        public function findOrEmpty(): FakeRecord
        {
            foreach ($this->matchingRows() as $row) return new FakeRecord($row);
            return new FakeRecord(null);
        }

        public function select(): FakeCollection
        {
            return new FakeCollection($this->matchingRows());
        }

        public function update(array $data = []): int
        {
            $changed = 0;
            if ($this->model === 'comments') {
                foreach (Comments::$rows as $id => $row) {
                    if (!$this->matches($row)) continue;
                    Comments::$rows[$id] = array_merge($row, $data);
                    $changed++;
                }
            } else {
                foreach (Cards::$rows as $row) {
                    if ($this->matches($row)) $changed++;
                }
            }
            return $changed;
        }

        /** @return array<int,array<string,mixed>> */
        private function matchingRows(): array
        {
            $source = $this->model === 'comments' ? Comments::$rows : Cards::$rows;
            $rows = array_values(array_filter($source, fn(array $row): bool => $this->matches($row)));
            if ($this->orderField !== null) {
                $field = $this->orderField;
                usort($rows, static fn(array $left, array $right): int => ($left[$field] ?? 0) <=> ($right[$field] ?? 0));
            }
            return $rows;
        }

        /** @param array<string,mixed> $row */
        private function matches(array $row): bool
        {
            foreach ($this->filters as $filter) {
                $actual = $row[$filter['field']] ?? null;
                if ($filter['kind'] === 'eq' && $actual !== $filter['value']) return false;
                if ($filter['kind'] === 'in' && !in_array($actual, $filter['value'], true)) return false;
            }
            return true;
        }
    }

    final class Comments
    {
        /** @var array<int,array<string,mixed>> */
        public static array $rows = [];

        public static function where(string $field, mixed $value): FakeQuery
        {
            return (new FakeQuery('comments'))->where($field, $value);
        }

        public static function whereIn(string $field, array $values): FakeQuery
        {
            return (new FakeQuery('comments'))->whereIn($field, $values);
        }

        public static function update(array $data, array $where): int
        {
            $query = new FakeQuery('comments');
            foreach ($where as $field => $value) $query->where((string) $field, $value);
            return $query->update($data);
        }

        public static function create(array $data): FakeRecord
        {
            $id = Comments::$rows === [] ? 1 : max(array_keys(Comments::$rows)) + 1;
            $data['id'] = $id;
            Comments::$rows[$id] = $data;
            return new FakeRecord($data);
        }
    }

    final class Cards
    {
        /** @var array<int,array<string,mixed>> */
        public static array $rows = [];

        public static function where(string $field, mixed $value): FakeQuery
        {
            return (new FakeQuery('cards'))->where($field, $value);
        }

        public static function whereIn(string $field, array $values): FakeQuery
        {
            return (new FakeQuery('cards'))->whereIn($field, $values);
        }
    }
}

namespace app\modules\Content\Mentions\Service
{
    final class Mentions
    {
        public static int $captures = 0;
        public static int $addedEvents = 0;

        public static function capture(
            string $resourceType,
            int $resourceId,
            int $cardId,
            ?int $commentId,
            int $actorId,
            array $texts
        ): array {
            self::$captures++;
            return [['id' => self::$captures, 'user_id' => 7, 'username' => 'mentioned_user']];
        }

        public static function publishAdded(
            string $resourceType,
            int $resourceId,
            int $cardId,
            ?int $commentId,
            int $actorId,
            array $mentions,
            array $suppressedRecipientIds = []
        ): void {
            self::$addedEvents++;
        }
    }
}

namespace app\modules\Platform\Eventing\Public
{
    final class DomainEventBus
    {
        /** @var array<int,array<string,mixed>> */
        public array $events = [];

        public function publish(
            string $type,
            string $aggregateType,
            string $aggregateKey,
            array $payload,
            ?int $actorId = null
        ): object {
            $this->events[] = compact('type', 'aggregateType', 'aggregateKey', 'payload', 'actorId');
            return new \stdClass();
        }
    }
}

namespace
{
    use app\modules\Content\Comments\Service\Comments as CommentsService;
    use app\modules\Content\Infrastructure\Persistence\Model\Cards;
    use app\modules\Content\Infrastructure\Persistence\Model\Comments;
    use app\modules\Content\Mentions\Service\Mentions;
    use app\modules\Platform\Eventing\Public\DomainEventBus;
    use app\modules\Platform\Notifications\Contract\SystemNoticeIssuer;
    use app\shared\Authorization\GrantSet;

    final class CommentNotificationTestState
    {
        public static DomainEventBus $bus;
        public static object $notices;
    }

    function app(string $class): object
    {
        if ($class === DomainEventBus::class) return CommentNotificationTestState::$bus;
        if ($class === SystemNoticeIssuer::class) return CommentNotificationTestState::$notices;
        throw new RuntimeException('Unexpected container lookup: ' . $class);
    }

    require dirname(__DIR__, 2) . '/app/modules/Content/Cards/Service/CardVisibilityPolicy.php';
    require dirname(__DIR__, 2) . '/app/modules/Content/Cards/Public/CardAccess.php';
    require dirname(__DIR__, 2) . '/app/modules/Content/Comments/Service/Comments.php';

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };
    $reset = static function (): void {
        Comments::$rows = [];
        Cards::$rows = [];
        Mentions::$captures = 0;
        Mentions::$addedEvents = 0;
        CommentNotificationTestState::$bus = new DomainEventBus();
        CommentNotificationTestState::$notices = new class {
            public array $records = [];
            public function issue(...$arguments): object { $this->records[] = $arguments; return new \stdClass(); }
        };
        \think\facade\Db::$commits = 0;
        \think\facade\Db::$rollbacks = 0;
    };
    $approve = new ReflectionMethod(CommentsService::class, 'approveComments');
    $approve->setAccessible(true);

    $reset();
    Cards::$rows[100] = ['id' => 100, 'status' => 0, 'user_id' => 2];
    $pending = CommentsService::createComment([
        'id' => 100, 'parent_id' => 0, 'status' => 3,
        'user_id' => 4, 'content' => '@mentioned_user waiting for review',
    ]);
    $assert((int) $pending['data']->status === 3, 'reviewed comment creation must remain pending');
    $assert(CommentNotificationTestState::$bus->events === [], 'pending comment creation must not publish a notification event');
    $assert(Mentions::$captures === 0, 'pending comment creation must not persist mention notifications before approval');

    $reset();
    Cards::$rows[100] = ['id' => 100, 'status' => 0, 'user_id' => 2];
    Comments::$rows[10] = [
        'id' => 10, 'aid' => 1, 'pid' => 100, 'parent_id' => 0,
        'status' => 3, 'user_id' => 4, 'content' => '@mentioned_user hello',
    ];
    CommentsService::moderateComment(['id' => 10, 'status' => 0], 99, new GrantSet());
    $assert(Comments::$rows[10]['status'] === 0, 'managed single approval must make the comment visible');
    $assert(count(CommentNotificationTestState::$bus->events) === 1, 'managed single approval must publish once');
    $assert(count(CommentNotificationTestState::$notices->records) === 1, 'managed single approval must issue one moderation notice');
    $assert(CommentNotificationTestState::$notices->records[0][1] === 'moderation.comment.10.approved', 'single approval notice must use the stable comment key');
    $assert(CommentNotificationTestState::$bus->events[0]['type'] === 'content.comment.published', 'managed single approval must use content.comment.published');
    $assert(Mentions::$addedEvents === 0, 'first publication must not use the edit-only mention event');

    $reset();
    Cards::$rows[100] = ['id' => 100, 'status' => 0, 'user_id' => 2];
    Comments::$rows[20] = [
        'id' => 20, 'aid' => 1, 'pid' => 100, 'parent_id' => 19,
        'status' => 3, 'user_id' => 4, 'content' => 'orphan reply',
    ];
    $approve->invoke(null, [20]);
    $assert(Comments::$rows[20]['status'] === 3, 'reply with an unavailable parent must remain pending');
    $assert(CommentNotificationTestState::$bus->events === [], 'unpublishable reply must not emit an event');
    $assert(Mentions::$captures === 0, 'unpublishable reply must not persist mentions');

    $reset();
    Cards::$rows[100] = ['id' => 100, 'status' => 0, 'user_id' => 2];
    Comments::$rows[30] = [
        'id' => 30, 'aid' => 1, 'pid' => 100, 'parent_id' => 0,
        'status' => 3, 'user_id' => 4, 'content' => 'publish once',
    ];
    $approve->invoke(null, [30]);
    $approve->invoke(null, [30]);
    $assert(Comments::$rows[30]['status'] === 0, 'valid pending comment must become visible');
    $assert(count(CommentNotificationTestState::$bus->events) === 1, 'repeated approval must not duplicate the publication event');
    $assert(count(CommentNotificationTestState::$notices->records) === 1, 'repeated approval must not duplicate the moderation notice');
    $assert(Mentions::$captures === 1, 'repeated approval must not persist mentions twice');

    fwrite(STDOUT, "PASS comment notification publication baseline\n");
}
