<?php

// ============================================================
// 最小替身 — 必须在 require 生产文件前定义
// ============================================================

namespace think\facade
{
    class Db {}
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

namespace app\api\model
{
    /**
     * 查询链代理 — 记录 whereIn / where / update / column 调用
     */
    class CardsQuery
    {
        public array $calls = [];

        public function whereIn(string $field, array $values): self
        {
            $this->calls[] = [__FUNCTION__, $field, $values];
            return $this;
        }

        public function where(string $field, $value): self
        {
            $this->calls[] = [__FUNCTION__, $field, $value];
            return $this;
        }

        public function update(array $data): int
        {
            $this->calls[] = [__FUNCTION__, $data];
            return 1;
        }

        public function column(string $field): array
        {
            $this->calls[] = [__FUNCTION__, $field];
            return [];
        }
    }

    class Cards
    {
        public static ?CardsQuery $lastQuery = null;

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

namespace app\common\support
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

    class FieldsToggle
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

    class ModelList {}
}

// ============================================================
// require 生产文件（此后所有替身必须已就绪）
// ============================================================
namespace {
    require __DIR__ . '/../../app/api/service/Content/Cards.php';
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
        \app\api\model\Cards::reset();
        \app\common\support\FieldsToggle::reset();
    };

    $test('unhide builds whereIn → where → update chain', static function () use ($reset, $assertSame): void {
        $reset();
        \app\api\service\Content\Cards::batchOperate('unhide', [1, 2, 3], 1, ['cards.update.all']);

        $query = \app\api\model\Cards::$lastQuery;
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
        \app\api\service\Content\Cards::batchOperate('hide', [10, 20], 1, ['cards.update.all']);

        $call = \app\common\support\FieldsToggle::$lastToggleCall;
        $assertSame(\app\api\model\Cards::class, $call[0], 'modelClass');
        $assertSame('status', $call[1], 'field');
        $assertSame([10, 20], $call[2], 'ids');
        $assertSame([0, 2], $call[3], 'from');
        $assertSame([1, 3], $call[4], 'to');
    });

    $test('unsupported method throws bad request', static function () use ($reset, $assertThrows): void {
        $reset();
        $assertThrows(
            static function (): void {
                \app\api\service\Content\Cards::batchOperate('nonexistent', [1], 1, []);
            },
            '不支持的操作'
        );
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
