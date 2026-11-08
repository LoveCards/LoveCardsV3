<?php

// ============================================================
// 最小替身 — 必须在 require 生产文件前定义
// ============================================================

namespace think\facade
{
    class Db
    {
        public static function startTrans(): void {}
        public static function commit(): void {}
        public static function rollback(): void {}
        public static function table(string $table): object
        {
            return new class {
                public function where(...$args): self { return $this; }
                public function whereIn(...$args): self { return $this; }
                public function field(...$args): self { return $this; }
                public function find(): ?array { return ['status' => 0, 'deleted_at' => null]; }
                public function delete(): int { return 1; }
            };
        }
    }
}

namespace app\api
{
    class ApiException extends \RuntimeException
    {
        const CODE_SYSTEM_ERROR = 500;

        public static function notFound(string $msg = ''): self
        {
            return new self($msg, 404);
        }

        public static function badRequest(string $msg = ''): self
        {
            return new self($msg, 400);
        }

        public static function forbidden(string $msg = ''): self
        {
            return new self($msg, 403);
        }

        public static function error(string $msg, int $code, $data = null, \Throwable $prev = null): self
        {
            return new self($msg, $code, $prev);
        }
    }
}

namespace app\modules\Content\Infrastructure\Persistence\Model
{
    /**
     * 查询链代理 — 记录 whereIn / where / update / column 调用
     */
    class CardsQuery
    {
        public array $calls = [];
        private array $filters = [];

        public function whereIn(string $field, array $values): self
        {
            $this->calls[] = [__FUNCTION__, $field, $values];
            $this->filters[] = ['in', $field, $values];
            return $this;
        }

        public function where(string $field, $value): self
        {
            $this->calls[] = [__FUNCTION__, $field, $value];
            $this->filters[] = ['eq', $field, $value];
            return $this;
        }

        public function order(string $field, string $direction): self { return $this; }
        public function lock(bool $lock): self { return $this; }
        public function select(): object
        {
            $rows = array_values(array_filter(Cards::$rows, fn(array $row): bool => $this->matches($row)));
            return new class($rows) {
                public function __construct(private array $rows) {}
                public function toArray(): array { return $this->rows; }
            };
        }

        public function update(array $data): int
        {
            $this->calls[] = [__FUNCTION__, $data];
            $changed = 0;
            foreach (Cards::$rows as $id => $row) {
                if (!$this->matches($row)) continue;
                Cards::$rows[$id] = array_merge($row, $data);
                $changed++;
            }
            return $changed ?: 1;
        }

        public function column(string $field): array
        {
            $this->calls[] = [__FUNCTION__, $field];
            return [];
        }

        private function matches(array $row): bool
        {
            foreach ($this->filters as [$kind, $field, $value]) {
                $actual = $row[$field] ?? null;
                if ($kind === 'eq' && $actual !== $value) return false;
                if ($kind === 'in' && !in_array($actual, $value, true)) return false;
            }
            return true;
        }
    }

    class Cards
    {
        public static ?CardsQuery $lastQuery = null;
        public static array $rows = [];

        public static function whereIn(string $field, array $values): CardsQuery
        {
            $q = new CardsQuery;
            $q->calls[] = ['whereIn', $field, $values];
            self::$lastQuery = $q;
            return $q;
        }

        public static function where(string $field, $value): CardsQuery
        {
            $q = new CardsQuery;
            $q->calls[] = ['where', $field, $value];
            self::$lastQuery = $q;
            return $q;
        }

        public static function reset(): void
        {
            self::$lastQuery = null;
            self::$rows = [];
        }
    }

    class TagsMap
    {
        public static function where(...$args): self { return new self; }
        public function delete(): int { return 0; }
        public static function create(array $data): self { return new self; }
    }

    class Comments
    {
        public static function where(...$args): self { return new self; }
        public function delete(): int { return 0; }
    }
}

namespace app\shared\Infrastructure\Persistence
{
    trait OwnershipGuard
    {
        protected static function guardBatch(array $ids, int $uid, array $caps, string $baseCap): void
        {
            $allCap = $baseCap . '.all';
            if (in_array($allCap, $caps)) {
                return;
            }
            if (in_array($baseCap, $caps)) {
                return;
            }
            throw \app\api\ApiException::forbidden('权限不足');
        }

        protected static function guard(int $id, int $uid, array $caps, string $baseCap): void {}
    }

    class BulkFieldToggle
    {
        public static $lastToggleCall;

        public static function toggle(string $modelClass, string $field, array $ids, array $from, array $to = []): void
        {
            self::$lastToggleCall = func_get_args();
        }

        public static function reset(): void
        {
            self::$lastToggleCall = null;
        }
    }

    class ModelQuery {}
}

// ============================================================
// require 生产文件（此后所有替身必须已就绪）
// ============================================================
namespace app\shared\Authorization
{
    class GrantSet {}
}

namespace app\modules\Content\Mentions\Service
{
    final class Mentions
    {
        public static int $captures = 0;
        public static function capture(...$arguments): array { self::$captures++; return []; }
        public static function publishAdded(...$arguments): void {}
    }
}

namespace app\modules\Platform\Notifications\Domain
{
    final class SystemNotice { public const MODERATION = 'system.moderation'; }
}

namespace app\modules\Platform\Files\Public
{
    final class Files
    {
        public static function referenceFileIds(string $type, string $id): array { return []; }
        public static function replaceReferences(string $type, string $id, string $purpose, array $fileIds, int $ownerId, bool $isPublic, bool $allowAll): void {}
    }
}

namespace app\modules\Content\Cards\Service\Policy
{
    class CardPolicy
    {
        public static function assertCanBatch(...$args): void {}
        public static function assertCan(...$args): void {}
    }
}

namespace {
    final class CardNoticeState
    {
        public static object $issuer;
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
                        public function applyChanges(array $changes, int $expectedRevision, ?int $actorId, ?string $reason): array
                        { return ['revision' => $expectedRevision, 'policies' => [], 'before' => []]; }
                    };
                }
            }
        }

    function app(string $class): object
    {
        if ($class === \app\modules\Platform\Notifications\Contract\SystemNoticeIssuer::class) return CardNoticeState::$issuer;
        if (in_array($class, ['app\\modules\\Platform\\Eventing\\Public\\RealtimeSignalPublisher', 'app\\modules\\Platform\\Notifications\\Contract\\NotificationPolicyStore'], true)) {
            return \FakeCommunicationPorts::instance($class);
        }
        throw new \RuntimeException('Unexpected container lookup: ' . $class);
}

require __DIR__ . '/../../app/modules/Content/Cards/Domain/PublicationStatus.php';
    require __DIR__ . '/../../app/modules/Content/Cards/Service/Cards.php';
}

// ============================================================
// 测试
// ============================================================
namespace {
    $tests = [];

    $test = static function (string $name, \Closure $callback) use (&$tests): void {
        $tests[$name] = $callback;
    };

    $assertSame = static function ($expected, $actual, string $message = ''): void {
        if ($expected !== $actual) {
            throw new \RuntimeException(
                ($message !== '' ? $message . ': ' : '')
                . 'expected ' . var_export($expected, true)
                . ', got ' . var_export($actual, true)
            );
        }
    };

    $assertThrows = static function (\Closure $callback, string $message) use ($assertSame): void {
        try {
            $callback();
        } catch (\Throwable $e) {
            $assertSame($message, $e->getMessage(), 'exception message');
            return;
        }
        throw new \RuntimeException('Expected exception with message "' . $message . '" was not thrown');
    };

    $reset = static function (): void {
        \app\modules\Content\Infrastructure\Persistence\Model\Cards::reset();
        \app\shared\Infrastructure\Persistence\BulkFieldToggle::reset();
        \app\modules\Content\Mentions\Service\Mentions::$captures = 0;
        CardNoticeState::$issuer = new class {
            public array $records = [];
            public function issue(...$arguments): object { $this->records[] = $arguments; return new \stdClass(); }
        };
    };

    $test('unhide builds whereIn → where → update chain', static function () use ($reset, $assertSame): void {
        $reset();
        \app\modules\Content\Cards\Service\Cards::batchOperate('unhide', [1, 2, 3], 1, new \app\shared\Authorization\GrantSet());

        $query = \app\modules\Content\Infrastructure\Persistence\Model\Cards::$lastQuery;
        $calls = $query->calls;
        $assertSame(3, count($calls), 'query chain call count');

        $assertSame('whereIn', $calls[0][0]);
        $assertSame('id', $calls[0][1]);
        $assertSame([1, 2, 3], $calls[0][2]);

        $assertSame('where', $calls[1][0]);
        $assertSame('status', $calls[1][1]);
        $assertSame(2, $calls[1][2]);

        $assertSame('update', $calls[2][0]);
        $assertSame(['status' => 0], $calls[2][1]);
    });

    $test('hide delegates to FieldsToggle with correct params', static function () use ($reset, $assertSame): void {
        $reset();
        \app\modules\Content\Cards\Service\Cards::batchOperate('hide', [10, 20], 1, new \app\shared\Authorization\GrantSet());

        $call = \app\shared\Infrastructure\Persistence\BulkFieldToggle::$lastToggleCall;
        $assertSame(\app\modules\Content\Infrastructure\Persistence\Model\Cards::class, $call[0], 'modelClass');
        $assertSame('status', $call[1], 'field');
        $assertSame([10, 20], $call[2], 'ids');
        $assertSame([0, 2], $call[3], 'from');
        $assertSame([1, 3], $call[4], 'to');
    });

    $test('unsupported method throws bad request', static function () use ($reset, $assertThrows): void {
        $reset();
        $assertThrows(
            static function (): void {
                \app\modules\Content\Cards\Service\Cards::batchOperate('nonexistent', [1], 1, new \app\shared\Authorization\GrantSet());
            },
            '不支持的操作'
        );
    });

    $test('pending card approval issues one moderation notice', static function () use ($reset, $assertSame): void {
        $reset();
        \app\modules\Content\Infrastructure\Persistence\Model\Cards::$rows[12] = [
            'id' => 12, 'user_id' => 42, 'status' => 3, 'data' => [], 'content' => '',
        ];
        $approve = new \ReflectionMethod(\app\modules\Content\Cards\Service\Cards::class, 'approveCards');
        $approve->setAccessible(true);
        $approve->invoke(null, [12]);
        $approve->invoke(null, [12]);
        $assertSame(0, \app\modules\Content\Infrastructure\Persistence\Model\Cards::$rows[12]['status']);
        $assertSame(1, count(CardNoticeState::$issuer->records), 'approval notice count');
        $assertSame('moderation.card.12.approved', CardNoticeState::$issuer->records[0][1], 'approval notice key');
    });

    $failures = 0;
    foreach ($tests as $name => $callback) {
        try {
            $callback();
            fwrite(STDOUT, "PASS {$name}\n");
        } catch (\Throwable $exception) {
            $failures++;
            fwrite(STDERR, "FAIL {$name}: {$exception->getMessage()}\n");
        }
    }

    if ($failures > 0) {
        fwrite(STDERR, "{$failures} Cards batch test(s) failed.\n");
        exit(1);
    }

    fwrite(STDOUT, count($tests) . " Cards batch tests passed.\n");



}
