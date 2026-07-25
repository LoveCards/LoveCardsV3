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

namespace app\api\model
{
    class Comments
    {
        const STATUS_NORMAL   = 0;
        const STATUS_HIDE     = 1;
        const STATUS_BAN      = 2;
        const STATUS_APPROVAL = 3;
        const DELETE_NORMAL = 0;
    }
}

namespace app\common\support
{
    class ModelList
    {
        public static array $capturedParams = [];
        public static int $callCount = 0;

        public static function make($model = null): self
        {
            return new self;
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
    require __DIR__ . '/../../app/api/service/Content/Comments.php';
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
        \app\common\support\ModelList::$capturedParams = [];
        \app\common\support\ModelList::$callCount = 0;
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
        \app\api\service\Content\Comments::listAll([], ['comments.read']);
        $params = \app\common\support\ModelList::$capturedParams;
        $assertSame(0, $params['where']['status'] ?? null, 'comments.read 应在 where 中注入 status=0');
    });

    $test('A2: comments.read.all 能力不注入 status 限制', static function () use ($reset, $assertSame): void {
        $reset();
        \app\api\service\Content\Comments::listAll([], ['comments.read.all']);
        $params = \app\common\support\ModelList::$capturedParams;
        $hasStatus = array_key_exists('status', $params['where'] ?? []);
        $assertSame(false, $hasStatus, 'comments.read.all 不应在 where 中注入 status 限制');
    });

    $test('A3: 同时拥有 read 和 read.all 按 read.all 处理', static function () use ($reset, $assertFalse): void {
        $reset();
        \app\api\service\Content\Comments::listAll([], ['comments.read', 'comments.read.all']);
        $params = \app\common\support\ModelList::$capturedParams;
        $hasStatus = array_key_exists('status', $params['where'] ?? []);
        $assertFalse($hasStatus, '同时拥有 read.all 不应注入 status 限制');
    });

    $test('A4: 无能力时（空 caps）注入 status=0', static function () use ($reset, $assertSame): void {
        $reset();
        \app\api\service\Content\Comments::listAll([], []);
        $params = \app\common\support\ModelList::$capturedParams;
        $assertSame(0, $params['where']['status'] ?? null, '空 caps 应在 where 中注入 status=0');
    });

    $test('A5: 默认 $caps 参数（不传）注入 status=0', static function () use ($reset, $assertSame): void {
        $reset();
        \app\api\service\Content\Comments::listAll([]);
        $params = \app\common\support\ModelList::$capturedParams;
        $assertSame(0, $params['where']['status'] ?? null, '不传 caps 应在 where 中注入 status=0');
    });

    // --- 组 B：分页参数直传 ---

    $test('B1: page 和 list_rows 参数传递到 ModelList', static function () use ($reset, $assertSame): void {
        $reset();
        \app\api\service\Content\Comments::listAll(['page' => 3, 'list_rows' => 20], ['comments.read.all']);
        $params = \app\common\support\ModelList::$capturedParams;
        $assertSame(3, $params['page'] ?? null, 'page 参数应直传');
        $assertSame(20, $params['list_rows'] ?? null, 'list_rows 参数应直传');
    });

    $test('B2: search_default_key 始终被注入', static function () use ($reset, $assertSame): void {
        $reset();
        \app\api\service\Content\Comments::listAll([], ['comments.read']);
        $params = \app\common\support\ModelList::$capturedParams;
        $assertSame('content', $params['search_default_key'] ?? null, 'search_default_key 应为 content');
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
