<?php
declare(strict_types=1);

// ============================================================
// LoveCardsV3 -- Comments 模块「管理列表」行为基线测试
//
// 范围：锁定 listAll() capability 过滤语义 + 分页参数直传
// 运行：@php tests/Comments/BehaviorBaseline.php
// 架构：standalone PHP 脚本（无 PHPUnit / 无框架引导）
// 模式：namespace 覆盖 + require 生产代码 + 查询链记录
// ============================================================

// ════════════════════════════════════════════════════════════
// 1. 全局函数替身
// ════════════════════════════════════════════════════════════
namespace
{
    function app(string $class): object
    {
        return new $class;
    }
}

// ════════════════════════════════════════════════════════════
// 2. Model & Support 替身（命名空间覆盖）
// ════════════════════════════════════════════════════════════

namespace app\modules\Content\Infrastructure\Persistence\Model
{
    class Cards
    {
        public static array $rows = [];
        public static function where(string $field, mixed $value): CardQuery
        {
            return new CardQuery([[$field, $value]]);
        }
    }

    final class CardQuery
    {
        public function __construct(private array $where) {}
        public function where(string $field, mixed $value): self
        {
            $this->where[] = [$field, $value];
            return $this;
        }
        public function findOrEmpty(): object
        {
            $matched = null;
            foreach (Cards::$rows as $row) {
                $ok = true;
                foreach ($this->where as [$field, $value]) {
                    if (($row[$field] ?? null) !== $value) { $ok = false; break; }
                }
                if ($ok) { $matched = $row; break; }
            }
            return new class($matched) {
                public function __construct(private ?array $row) {}
                public function __get(string $name): mixed { return $this->row[$name] ?? null; }
                public function isEmpty(): bool { return $this->row === null; }
                public function toArray(): array { return $this->row ?? []; }
            };
        }
    }

    class Comments
    {
        const STATUS_NORMAL   = 0;
        const STATUS_HIDE     = 1;
        const STATUS_BAN      = 2;
        const STATUS_APPROVAL = 3;
        const DELETE_NORMAL = 0;
        public static array $rows = [];
        public static array $capturedWhere = [];

        public static function where(string $field, mixed $value): CommentQuery
        {
            self::$capturedWhere = [[$field, $value]];
            return new CommentQuery(self::$capturedWhere);
        }
    }

    final class CommentQuery
    {
        public function __construct(private array $where) {}
        public function where(string $field, mixed $value): self
        {
            $this->where[] = [$field, $value];
            Comments::$capturedWhere = $this->where;
            return $this;
        }
        public function findOrEmpty(): object
        {
            $matched = null;
            foreach (Comments::$rows as $row) {
                $ok = true;
                foreach ($this->where as [$field, $value]) {
                    if (($row[$field] ?? null) !== $value) { $ok = false; break; }
                }
                if ($ok) { $matched = $row; break; }
            }
            return new class($matched) {
                public function __construct(private ?array $row) {}
                public function __get(string $name): mixed { return $this->row[$name] ?? null; }
                public function isEmpty(): bool { return $this->row === null; }
                public function toArray(): array { return $this->row ?? []; }
            };
        }
    }
}

namespace app\api
{
    class ApiException extends \RuntimeException
    {
        public static function notFound(string $message): self { return new self($message, 404); }
    }
}

namespace think\facade
{
    class Db
    {
        public static function table(string $table): object
        {
            return new class {
                public function whereIn(string $field, array $values): self { return $this; }
                public function field(string $fields): self { return $this; }
                public function select(): object
                {
                    return new class {
                        public function toArray(): array { return []; }
                    };
                }
            };
        }
    }
}

namespace app\shared\Infrastructure\Persistence
{
    class ModelQuery
    {
        public static array $capturedParams = [];
        public static int $callCount = 0;

        public static function make($model = null): self
        {
            return new self;
        }

        public function trashScope(int $showDeleted = 0, bool $canReadAll = false): self
        {
            return $this;
        }

        public function getPaginate(array $params): object
        {
            self::$capturedParams = $params;
            self::$callCount++;
            return new class {
                public function toArray(): array
                {
                    return ['data' => [], 'total' => 0, 'per_page' => 15, 'current_page' => 1];
                }
            };
        }
    }

    // OwnershipGuard 存根 — CommentsService 使用该 trait 但 listAll() 不调用其方法
    trait OwnershipGuard
    {
    }
}

// ════════════════════════════════════════════════════════════
// 3. 加载生产代码
// ════════════════════════════════════════════════════════════
namespace
{
require __DIR__ . '/../../app/modules/Content/Comments/Service/Comments.php';
}

// ════════════════════════════════════════════════════════════
// 4. 测试工具函数
// ════════════════════════════════════════════════════════════
namespace
{
    $tests = [];

    $test = static function (string $name, Closure $callback) use (&$tests): void {
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

    $assertTrue = static function (bool $value, string $message = ''): void {
        if ($value !== true) {
            throw new \RuntimeException($message !== '' ? $message : 'expected true, got false');
        }
    };

    $assertFalse = static function (bool $value, string $message = ''): void {
        if ($value !== false) {
            throw new \RuntimeException($message !== '' ? $message : 'expected false, got true');
        }
    };

    $reset = static function (): void {
        \app\shared\Infrastructure\Persistence\ModelQuery::$capturedParams = [];
        \app\shared\Infrastructure\Persistence\ModelQuery::$callCount = 0;
        \app\modules\Content\Infrastructure\Persistence\Model\Comments::$capturedWhere = [];
        \app\modules\Content\Infrastructure\Persistence\Model\Comments::$rows = [];
    };
}

// ════════════════════════════════════════════════════════════
// 5. 测试用例
// ════════════════════════════════════════════════════════════
namespace
{
    // --- 组 A：capability 过滤语义 ---

    $test('A1: comments.read 能力注入 status=0 条件', static function () use ($reset, $assertSame): void {
        $reset();
        \app\modules\Content\Comments\Service\Comments::listAll([], ['comments.read']);
        $params = \app\shared\Infrastructure\Persistence\ModelQuery::$capturedParams;
        $assertSame(0, $params['where']['status'] ?? null, 'comments.read 应在 where 中注入 status=0');
    });

    $test('A2: comments.read.all 能力不注入 status 限制', static function () use ($reset, $assertSame): void {
        $reset();
        \app\modules\Content\Comments\Service\Comments::listAll([], ['comments.read.all']);
        $params = \app\shared\Infrastructure\Persistence\ModelQuery::$capturedParams;
        $hasStatus = array_key_exists('status', $params['where'] ?? []);
        $assertSame(false, $hasStatus, 'comments.read.all 不应在 where 中注入 status 限制');
    });

    $test('A3: 同时拥有 read 和 read.all 按 read.all 处理', static function () use ($reset, $assertFalse): void {
        $reset();
        \app\modules\Content\Comments\Service\Comments::listAll([], ['comments.read', 'comments.read.all']);
        $params = \app\shared\Infrastructure\Persistence\ModelQuery::$capturedParams;
        $hasStatus = array_key_exists('status', $params['where'] ?? []);
        $assertFalse($hasStatus, '同时拥有 read.all 不应注入 status 限制');
    });

    $test('A4: 无能力时（空 caps）注入 status=0', static function () use ($reset, $assertSame): void {
        $reset();
        \app\modules\Content\Comments\Service\Comments::listAll([], []);
        $params = \app\shared\Infrastructure\Persistence\ModelQuery::$capturedParams;
        $assertSame(0, $params['where']['status'] ?? null, '空 caps 应在 where 中注入 status=0');
    });

    $test('A5: 默认 $caps 参数（不传）注入 status=0', static function () use ($reset, $assertSame): void {
        $reset();
        \app\modules\Content\Comments\Service\Comments::listAll([]);
        $params = \app\shared\Infrastructure\Persistence\ModelQuery::$capturedParams;
        $assertSame(0, $params['where']['status'] ?? null, '不传 caps 应在 where 中注入 status=0');
    });

    // --- 组 B：分页参数直传 ---

    $test('B1: page 和 list_rows 参数传递到 ModelList', static function () use ($reset, $assertSame): void {
        $reset();
        \app\modules\Content\Comments\Service\Comments::listAll(['page' => 3, 'list_rows' => 20], ['comments.read.all']);
        $params = \app\shared\Infrastructure\Persistence\ModelQuery::$capturedParams;
        $assertSame(3, $params['page'] ?? null, 'page 参数应直传');
        $assertSame(20, $params['list_rows'] ?? null, 'list_rows 参数应直传');
    });

    $test('B2: search_default_key 始终被注入', static function () use ($reset, $assertSame): void {
        $reset();
        \app\modules\Content\Comments\Service\Comments::listAll([], ['comments.read']);
        $params = \app\shared\Infrastructure\Persistence\ModelQuery::$capturedParams;
        $assertSame('content', $params['search_default_key'] ?? null, 'search_default_key 应为 content');
    });

    $test('C1: public comment detail requires published status', static function () use ($reset, $assertSame): void {
        $reset();
        \app\modules\Content\Infrastructure\Persistence\Model\Cards::$rows = [
            ['id' => 42, 'status' => 0],
        ];
        \app\modules\Content\Infrastructure\Persistence\Model\Comments::$rows = [
            ['id' => 9, 'pid' => 42, 'status' => 0, 'content' => 'visible'],
        ];
        $result = \app\modules\Content\Comments\Service\Comments::get(9);
        $assertSame('visible', $result['content'] ?? null, 'published comment should be readable');
        $assertSame([['id', 9], ['status', 0]], \app\modules\Content\Infrastructure\Persistence\Model\Comments::$capturedWhere);
    });

    $test('C2: hidden comment detail is not exposed publicly', static function () use ($reset, $assertSame): void {
        $reset();
        \app\modules\Content\Infrastructure\Persistence\Model\Comments::$rows = [
            ['id' => 10, 'status' => 3, 'content' => 'pending'],
        ];
        try {
            \app\modules\Content\Comments\Service\Comments::get(10);
        } catch (\app\api\ApiException $exception) {
            $assertSame(404, $exception->getCode(), 'hidden public detail should be a 404');
            return;
        }
        throw new \RuntimeException('hidden comment was exposed publicly');
    });

    $test('C3: managed comment detail may include hidden status', static function () use ($reset, $assertSame): void {
        $reset();
        \app\modules\Content\Infrastructure\Persistence\Model\Comments::$rows = [
            ['id' => 11, 'status' => 3, 'content' => 'pending'],
        ];
        $result = \app\modules\Content\Comments\Service\Comments::get(11, true);
        $assertSame('pending', $result['content'] ?? null, 'managed detail should retain moderation visibility');
        $assertSame([['id', 11]], \app\modules\Content\Infrastructure\Persistence\Model\Comments::$capturedWhere);
    });

    $test('C4: reply creation validates visible parent scope before insert', static function () use ($assertTrue): void {
        $source = (string) file_get_contents(__DIR__ . '/../../app/modules/Content/Comments/Service/Comments.php');
        $parentLookup = strpos($source, "CommentsModel::where('id', \$parentId)");
        $insert = strpos($source, 'CommentsModel::create($params)');
        $assertTrue($parentLookup !== false && $insert !== false && $parentLookup < $insert, 'parent validation must precede insert');
        $scope = substr($source, $parentLookup, $insert - $parentLookup);
        foreach (["->where('aid', 1)", "->where('pid', \$id)", "->where('status', 0)", "->lock(true)"] as $guard) {
            $assertTrue(strpos($scope, $guard) !== false, 'missing parent guard: ' . $guard);
        }
    });
}

// ════════════════════════════════════════════════════════════
// 6. 运行器
// ════════════════════════════════════════════════════════════
namespace
{
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
        fwrite(STDERR, "\n{$failures} Comments behavior baseline test(s) failed.\n");
        exit(1);
    }

    fwrite(STDOUT, "\n" . count($tests) . " Comments behavior baseline tests passed.\n");
}
