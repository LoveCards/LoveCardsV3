<?php
declare(strict_types=1);

namespace think\facade {
    final class NotificationRows
    {
        /** @param array<int, array<string, mixed>> $rows */
        public function __construct(private array $rows) {}
        /** @return array<int, array<string, mixed>> */
        public function toArray(): array { return $this->rows; }
    }

    final class NotificationQuery
    {
        private ?int $recipientId = null;
        private ?int $beforeId = null;
        private ?int $exactId = null;
        private ?string $beforeCreatedAt = null;
        private ?string $sameCreatedAt = null;
        private ?int $sameBeforeId = null;
        private ?string $type = null;
        /** @var array<int, string>|null */
        private ?array $allowedTypes = null;
        private bool $unreadOnly = false;
        private ?int $limit = null;

        public function where(mixed $field, mixed $operator = null, mixed $value = null): self
        {
            if (is_array($field)) return $this;
            if (is_callable($field)) {
                $nested = new self();
                $field($nested);
                $this->beforeCreatedAt = $nested->beforeCreatedAt;
                $this->sameCreatedAt = $nested->sameCreatedAt;
                $this->sameBeforeId = $nested->sameBeforeId;
                return $this;
            }
            if ($field === 'recipient_id') $this->recipientId = (int) $operator;
            if ($field === 'type') $this->type = (string) $operator;
            if ($field === 'created_at' && $operator === '<') $this->beforeCreatedAt = (string) $value;
            if ($field === 'created_at' && $operator === '=') $this->sameCreatedAt = (string) $value;
            if ($field === 'id' && $operator === '<') {
                if ($this->sameCreatedAt !== null) $this->sameBeforeId = (int) $value;
                else $this->beforeId = (int) $value;
            }
            if ($field === 'id' && $operator !== '<') $this->exactId = (int) $operator;
            return $this;
        }

        public function whereOr(callable $callback): self
        {
            $nested = new self();
            $callback($nested);
            $this->sameCreatedAt = $nested->sameCreatedAt;
            $this->sameBeforeId = $nested->sameBeforeId;
            return $this;
        }

        /** @param array<int, string> $values */
        public function whereIn(string $field, array $values): self
        {
            if ($field === 'type') $this->allowedTypes = $values;
            return $this;
        }

        public function whereNull(string $field): self
        {
            if ($field === 'read_at') $this->unreadOnly = true;
            return $this;
        }

        public function order(string $field, string $direction): self { return $this; }
        public function limit(int $limit): self { $this->limit = $limit; return $this; }
        public function field(string $fields): self { return $this; }

        public function select(): NotificationRows
        {
            $rows = $this->filteredRows();
            if ($this->limit !== null) $rows = array_slice($rows, 0, $this->limit);
            return new NotificationRows($rows);
        }

        public function count(): int { return count($this->filteredRows()); }
        public function find(): ?array { return $this->filteredRows()[0] ?? null; }

        /** @return array<int, array<string, mixed>> */
        private function filteredRows(): array
        {
            $rows = array_values(array_filter(Db::$notifications, function (array $row): bool {
                if ($this->recipientId !== null && (int) $row['recipient_id'] !== $this->recipientId) return false;
                if ($this->exactId !== null && (int) $row['id'] !== $this->exactId) return false;
                if ($this->beforeId !== null && (int) $row['id'] >= $this->beforeId) return false;
                if ($this->beforeCreatedAt !== null) {
                    $createdAt = (string) ($row['created_at'] ?? '');
                    $before = $createdAt < $this->beforeCreatedAt;
                    $tie = $this->sameCreatedAt !== null
                        && $createdAt === $this->sameCreatedAt
                        && (int) $row['id'] < (int) $this->sameBeforeId;
                    if (!$before && !$tie) return false;
                }
                if ($this->type !== null && (string) $row['type'] !== $this->type) return false;
                if ($this->allowedTypes !== null && !in_array((string) $row['type'], $this->allowedTypes, true)) return false;
                if ($this->unreadOnly && ($row['read_at'] ?? null) !== null) return false;
                return true;
            }));
            usort($rows, static function (array $left, array $right): int {
                return strcmp((string) $right['created_at'], (string) $left['created_at'])
                    ?: ((int) $right['id'] <=> (int) $left['id']);
            });
            return $rows;
        }
    }

    final class Db
    {
        /** @var array<int, array<string, mixed>> */
        public static array $notifications = [];
        public static function table(string $table): NotificationQuery
        {
            if ($table !== 'notifications') throw new \RuntimeException('Unexpected table: ' . $table);
            return new NotificationQuery();
        }
    }
}

namespace {
    $root = dirname(__DIR__, 2);
    require $root . '/app/shared/Resource/ResourceRef.php';
    require $root . '/app/modules/Platform/Notifications/Contract/NotificationTypeCatalog.php';
    require $root . '/app/modules/Platform/Notifications/Domain/NotificationIntent.php';
    require $root . '/app/modules/Platform/Notifications/Contract/NotificationRepository.php';
    require $root . '/app/modules/Platform/Notifications/Infrastructure/ThinkOrmNotificationRepository.php';

    use app\modules\Platform\Notifications\Infrastructure\ThinkOrmNotificationRepository;
    use think\facade\Db;

    $row = static fn(int $id, string $type, int $recipientId = 7, string $createdAt = '2026-08-21 00:00:00'): array => [
        'id' => $id,
        'recipient_id' => $recipientId,
        'dedupe_key' => 'row:' . $id,
        'type' => $type,
        'actor_id' => null,
        'resource_type' => null,
        'resource_id' => null,
        'payload' => '{}',
        'read_at' => null,
        'created_at' => $createdAt,
    ];
    Db::$notifications = [
        $row(9, 'custom.foo'),
        $row(8, 'content.replied', 7, '2026-08-21 00:00:01'),
        $row(7, 'custom.foo'),
        $row(6, 'library.saved', 7, '2026-08-21 00:00:01'),
        $row(5, 'social.followed'),
        $row(2, 'system.notice'),
        $row(4, 'custom.foo'),
        $row(3, 'message', 99),
    ];

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new \RuntimeException($message);
    };
    $repository = new ThinkOrmNotificationRepository();

    $first = $repository->listForUser(7, null, 2, false, null, null);
    $assert(array_column($first['items'], 'id') === [8, 6], 'unknown historical types must be filtered before the page limit');
    $assert($first['has_more'] === true && is_string($first['next_cursor']) && !ctype_digit($first['next_cursor']), 'known rows should retain an opaque composite cursor');
    $assert(array_diff(array_column($first['items'], 'type'), ['content.replied', 'library.saved']) === [], 'public rows must contain only catalog types');

    $second = $repository->listForUser(7, $first['next_cursor'], 2, false, null, null);
    $assert(array_column($second['items'], 'id') === [5, 2], 'the next page should continue across unknown historical rows');
    $assert($second['has_more'] === false && $second['next_cursor'] === null, 'the final known page should terminate cleanly');
    $assert($repository->unreadCount(7) === 4, 'unknown historical types must not create an unread badge with no visible row');

    $engagement = $repository->listForUser(7, null, 20, false, null, 'engagement');
    $assert(array_column($engagement['items'], 'id') === [8, 6, 5], 'engagement should include message/social/interaction rows and exclude system rows');
    $assert($repository->unreadCount(7, 'engagement') === 3, 'engagement unread count should exclude system rows');
    $legacy = $repository->listForUser(7, '6', 2, false, null, null);
    $assert(array_column($legacy['items'], 'id') === [5, 2], 'legacy numeric cursors should remain readable during rollout');

    fwrite(STDOUT, "PASS notification type boundary\n");
}
