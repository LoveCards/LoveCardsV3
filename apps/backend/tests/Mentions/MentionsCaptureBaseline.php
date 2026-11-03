<?php

declare(strict_types=1);

namespace think\facade
{
    final class MentionCollection
    {
        /** @param array<int, array<string, mixed>> $rows */
        public function __construct(private array $rows)
        {
        }

        /** @return array<int, array<string, mixed>> */
        public function toArray(): array
        {
            return $this->rows;
        }
    }

    final class MentionQuery
    {
        /** @var array<int, array{kind:string,field:string,value:mixed}> */
        private array $filters = [];
        private ?string $orderField = null;

        public function __construct(private string $table)
        {
        }

        public function where(string|array $field, mixed $value = null): self
        {
            if (is_array($field)) {
                foreach ($field as $name => $expected) $this->where((string) $name, $expected);
                return $this;
            }
            $this->filters[] = ['kind' => 'eq', 'field' => $field, 'value' => $value];
            return $this;
        }

        public function whereIn(string $field, array $values): self
        {
            $this->filters[] = ['kind' => 'in', 'field' => $field, 'value' => array_values($values)];
            return $this;
        }

        public function whereNull(string $field): self
        {
            $this->filters[] = ['kind' => 'null', 'field' => $field, 'value' => null];
            return $this;
        }

        public function field(string $fields): self
        {
            return $this;
        }

        public function order(string $field, string $direction): self
        {
            $this->orderField = $field;
            return $this;
        }

        public function select(): MentionCollection
        {
            if ($this->table === 'users') {
                Db::$userSelectCalls++;
                foreach ($this->filters as $filter) {
                    if ($filter['kind'] === 'in' && $filter['field'] === 'username') {
                        Db::$lastUserHandleBatch = $filter['value'];
                    }
                }
            }
            return new MentionCollection($this->matchingRows());
        }

        /** @return array<int, mixed> */
        public function column(string $field): array
        {
            if ($this->table === 'user_safety_relations') Db::$safetyBatchCalls++;
            if ($this->table === 'content_mentions') Db::$existingMentionBatchCalls++;
            return array_values(array_map(
                static fn(array $row): mixed => $row[$field] ?? null,
                $this->matchingRows()
            ));
        }

        public function insertGetId(array $row): int
        {
            if ($this->table !== 'content_mentions') {
                throw new \RuntimeException('Unexpected insert table: ' . $this->table);
            }
            Db::$mentionInsertAttempts++;
            if (Db::$nextInsertException !== null) {
                $exception = Db::$nextInsertException;
                Db::$nextInsertException = null;
                if (Db::$persistRowBeforeException) {
                    $id = Db::$nextMentionId++;
                    Db::$contentMentions[$id] = ['id' => $id] + $row;
                    Db::$persistRowBeforeException = false;
                }
                throw $exception;
            }
            $id = Db::$nextMentionId++;
            Db::$contentMentions[$id] = ['id' => $id] + $row;
            Db::$mentionInsertCalls++;
            return $id;
        }

        public function lock(bool $lock): self
        {
            if ($lock) Db::$mentionLockReads++;
            return $this;
        }

        public function value(string $field): mixed
        {
            $row = $this->matchingRows()[0] ?? null;
            return $row[$field] ?? null;
        }

        /** @return array<int, array<string, mixed>> */
        private function matchingRows(): array
        {
            $rows = match ($this->table) {
                'users' => Db::$users,
                'user_safety_relations' => Db::$safetyRelations,
                'content_mentions' => Db::$contentMentions,
                default => throw new \RuntimeException('Unexpected table: ' . $this->table),
            };
            $rows = array_values(array_filter($rows, fn(array $row): bool => $this->matches($row)));
            if ($this->orderField !== null) {
                $field = $this->orderField;
                usort($rows, static fn(array $left, array $right): int => ($left[$field] ?? 0) <=> ($right[$field] ?? 0));
            }
            return $rows;
        }

        /** @param array<string, mixed> $row */
        private function matches(array $row): bool
        {
            foreach ($this->filters as $filter) {
                $actual = $row[$filter['field']] ?? null;
                if ($filter['kind'] === 'null' && $actual !== null) return false;
                if ($filter['kind'] === 'eq' && $actual !== $filter['value']) return false;
                if ($filter['kind'] !== 'in') continue;

                $values = $filter['value'];
                if ($filter['field'] === 'username') {
                    $actual = mb_strtolower((string) $actual, 'UTF-8');
                    $values = array_map(static fn(mixed $item): string => mb_strtolower((string) $item, 'UTF-8'), $values);
                }
                if (!in_array($actual, $values, true)) return false;
            }
            return true;
        }
    }

    final class Db
    {
        /** @var array<int, array<string, mixed>> */
        public static array $users = [];
        /** @var array<int, array<string, mixed>> */
        public static array $safetyRelations = [];
        /** @var array<int, array<string, mixed>> */
        public static array $contentMentions = [];
        /** @var string[] */
        public static array $lastUserHandleBatch = [];
        public static int $userSelectCalls = 0;
        public static int $safetyBatchCalls = 0;
        public static int $existingMentionBatchCalls = 0;
        public static int $mentionInsertCalls = 0;
        public static int $mentionInsertAttempts = 0;
        public static int $mentionLockReads = 0;
        public static int $nextMentionId = 1;
        public static ?\Throwable $nextInsertException = null;
        public static bool $persistRowBeforeException = false;

        public static function table(string $table): MentionQuery
        {
            return new MentionQuery($table);
        }
    }
}

namespace
{
    final class MentionDatabaseException extends RuntimeException
    {
        /** @param array<string, mixed> $data */
        public function __construct(private array $data, ?Throwable $previous = null)
        {
            parent::__construct('database operation failed', 0, $previous);
        }

        /** @return array<string, mixed> */
        public function getData(): array
        {
            return $this->data;
        }
    }

    $root = dirname(__DIR__, 2);
    require $root . '/app/modules/Content/Mentions/Domain/MentionParser.php';
    require $root . '/app/modules/Content/Mentions/Service/Mentions.php';

    use app\modules\Content\Mentions\Service\Mentions;
    use think\facade\Db;

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };

    Db::$users = [
        1 => ['id' => 1, 'username' => 'ActorOne', 'status' => 0, 'deleted_at' => null],
        2 => ['id' => 2, 'username' => 'AliceOne', 'status' => 0, 'deleted_at' => null],
        3 => ['id' => 3, 'username' => 'Bob_User', 'status' => 0, 'deleted_at' => null],
        4 => ['id' => 4, 'username' => 'InactiveOne', 'status' => 1, 'deleted_at' => null],
        5 => ['id' => 5, 'username' => 'DeletedOne', 'status' => 0, 'deleted_at' => '2026-08-20 12:00:00'],
        6 => ['id' => 6, 'username' => 'MutedOut', 'status' => 0, 'deleted_at' => null],
        7 => ['id' => 7, 'username' => 'MutedIn', 'status' => 0, 'deleted_at' => null],
        8 => ['id' => 8, 'username' => 'NewFriend', 'status' => 0, 'deleted_at' => null],
    ];
    Db::$safetyRelations = [
        ['actor_id' => 1, 'subject_id' => 6, 'relation' => 'block'],
        ['actor_id' => 7, 'subject_id' => 1, 'relation' => 'block'],
    ];

    $body = '@ActorOne @AliceOne @ALICEONE @Bob_User @InactiveOne @DeletedOne @MutedOut @MutedIn';
    $created = Mentions::capture('comment', 88, 44, 88, 1, [$body]);

    $assert(Db::$userSelectCalls === 1, 'all parsed handles should be resolved with one users query');
    $assert(count(Db::$lastUserHandleBatch) === 7, 'case-insensitive duplicate handles should be removed before user resolution');
    $assert(Db::$safetyBatchCalls === 2, 'both block directions should be resolved with two batch queries');
    $assert($created === [
        ['id' => 1, 'user_id' => 2, 'username' => 'AliceOne'],
        ['id' => 2, 'user_id' => 3, 'username' => 'Bob_User'],
    ], 'only active, non-self and mutually unblocked users should become notification candidates');
    $assert(Db::$mentionInsertCalls === 2, 'duplicate text mentions should persist one relation per eligible user');
    $assert(array_column(Db::$contentMentions, 'mentioned_user_id') === [2, 3], 'ineligible users must never be persisted');

    $retried = Mentions::capture('comment', 88, 44, 88, 1, [$body]);
    $assert($retried === [], 'retrying the same content should produce no new notification candidates');
    $assert(Db::$mentionInsertCalls === 2, 'retrying the same content should not insert duplicate mention relations');

    $edited = Mentions::capture('comment', 88, 44, 88, 1, ['保留 @AliceOne，新增 @NewFriend，再重复 @newfriend。']);
    $assert($edited === [['id' => 3, 'user_id' => 8, 'username' => 'NewFriend']], 'editing should return only newly persisted mention relations');
    $assert(Db::$mentionInsertCalls === 3, 'editing should not reinsert an existing relation');
    $assert(Db::$userSelectCalls === 3, 'each capture should keep user resolution constant at one batch query');
    $assert(Db::$existingMentionBatchCalls === 3, 'each capture should check existing relations in one batch query');

    $duplicate = new RuntimeException('wrapped database exception', 0, new MentionDatabaseException([
        'PDO Error Info' => [
            'SQLSTATE' => '23000',
            'Driver Error Code' => 1062,
            'Driver Error Message' => 'Duplicate entry',
        ],
    ]));
    Db::$nextInsertException = $duplicate;
    Db::$persistRowBeforeException = true;
    $concurrent = Mentions::capture('comment', 99, 44, 99, 1, ['@AliceOne']);
    $assert($concurrent === [], 'a confirmed concurrent duplicate relation must remain idempotent');
    $assert(Db::$mentionLockReads === 1, 'duplicate confirmation must use a current locking read');

    $unconfirmedDuplicate = new MentionDatabaseException([
        'PDO Error Info' => [
            'SQLSTATE' => '23000',
            'Driver Error Code' => 1062,
            'Driver Error Message' => 'Duplicate entry',
        ],
    ]);
    Db::$nextInsertException = $unconfirmedDuplicate;
    try {
        Mentions::capture('comment', 102, 44, 102, 1, ['@AliceOne']);
        throw new RuntimeException('unconfirmed duplicate must be rethrown');
    } catch (Throwable $exception) {
        $assert($exception === $unconfirmedDuplicate, '1062 without the expected persisted relation must be rethrown unchanged');
    }
    $assert(Db::$mentionLockReads === 2, 'each 1062 must confirm the exact mention relation');

    $deadlockCause = new PDOException('Deadlock found when trying to get lock', 1213);
    $deadlockCause->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock'];
    $deadlock = new RuntimeException('wrapped database exception', 0, $deadlockCause);
    Db::$nextInsertException = $deadlock;
    try {
        Mentions::capture('comment', 100, 44, 100, 1, ['@AliceOne']);
        throw new RuntimeException('deadlock must be rethrown');
    } catch (Throwable $exception) {
        $assert($exception === $deadlock, 'deadlock 1213 must be rethrown unchanged');
    }
    $assert(Db::$mentionLockReads === 2, 'deadlock must not run duplicate confirmation queries');

    $messageOnly = new MentionDatabaseException([
        'PDO Error Info' => [
            'SQLSTATE' => 'HY000',
            'Driver Error Code' => 1205,
            'Driver Error Message' => 'Duplicate entry text is not enough',
        ],
    ]);
    Db::$nextInsertException = $messageOnly;
    try {
        Mentions::capture('comment', 101, 44, 101, 1, ['@AliceOne']);
        throw new RuntimeException('message-only duplicate must be rethrown');
    } catch (Throwable $exception) {
        $assert($exception === $messageOnly, 'duplicate-looking text without 23000/1062 must be rethrown unchanged');
    }

    fwrite(STDOUT, "PASS mention capture behavior baseline\n");
}
