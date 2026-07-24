<?php
declare(strict_types=1);

// ============================================================
// LoveCardsV3 — Files 模块「严格本人文件列表」行为基线测试
//
// 范围：锁定 list() 现有语义 + listOwn() 预期契约
// 运行：@php tests/Files/BehaviorBaseline.php
// 架构：standalone PHP 脚本（无 PHPUnit / 无框架引导）
// 模式：namespace 覆盖 + require 生产代码 + 查询链记录
// ============================================================

// ════════════════════════════════════════════════════════════
// 1. 全局函数替身
// ════════════════════════════════════════════════════════════
namespace
{
    /**
     * 替代 ThinkPHP 的 app() 容器，仅用 new 实例化模型。
     * 供 ModelList::make(string) 调用。
     */
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
    /**
     * 查询链记录器 — 记录所有 think-chain 方法调用。
     * 等价于 Cards 测试中的 CardsQuery。
     */
    class FilesQuery
    {
        /** @var array{0:string,1:string,2:mixed,3?:string}[] */
        public array $calls = [];

        public function where($field, $value = null, string $op = '='): self
        {
            $this->calls[] = ['where', $field, $value, $op];
            return $this;
        }

        public function whereOr(string $field, $value): self
        {
            $this->calls[] = ['whereOr', $field, $value];
            return $this;
        }

        public function order(string $key, string $rule): self
        {
            $this->calls[] = ['order', $key, $rule];
            return $this;
        }

        public function withoutField(array $fields): self
        {
            $this->calls[] = ['withoutField', $fields];
            return $this;
        }

        public function whereLike(string $field, string $value): self
        {
            $this->calls[] = ['whereLike', $field, $value];
            return $this;
        }

        public function paginate(array $config): object
        {
            $this->calls[] = ['paginate', $config];
            return new class {
                public function toArray(): array
                {
                    return ['data' => [], 'total' => 0];
                }
            };
        }
    }

    /**
     * Files 模型替身 — 只提供 StorageManager::list() 路径所需的表面。
     * 定义常量保证其他方法体解析通过（不会被 list 路径执行）。
     */
    class Files
    {
        const STATUS_NORMAL      = 0;
        const STATUS_BANNED      = 1;
        const UPLOAD_PENDING     = 0;
        const UPLOAD_COMPLETED   = 1;
        const UPLOAD_FAILED      = 2;

        const SCENE_CARD     = 'card';
        const SCENE_COMMENT  = 'comment';
        const SCENE_AVATAR   = 'avatar';
        const SCENE_DIRECT   = 'direct';

        public static function generateHash(): string
        {
            return 'mock-hash';
        }

        public function where(...$args): FilesQuery
        {
            return new FilesQuery;
        }

        public static function create(array $data): self
        {
            return new self;
        }
    }
}

namespace app\common\support
{
    /**
     * ModelList 替身 — 捕获 getPaginate() 传入的 where 条件。
     * 不执行实际链式调用，只记录静态状态供断言检查。
     */
    class ModelList
    {
        /** @var array 最近一次 getPaginate 收到的 where */
        public static array $capturedWhere = [];

        /** @var bool 当前请求中是否调用了 withTrashed */
        public static bool $withTrashedCalled = false;

        /** @var bool 当前请求中是否调用了 onlyTrashed */
        public static bool $onlyTrashedCalled = false;

        public static function make($model = null): self
        {
            return new self;
        }

        public function withTrashed(): self
        {
            self::$withTrashedCalled = true;
            return $this;
        }

        public function onlyTrashed(): self
        {
            self::$onlyTrashedCalled = true;
            return $this;
        }

        public function getPaginate(array $params): object
        {
            self::$capturedWhere = $params['where'] ?? [];
            return new class {
                public function toArray(): array
                {
                    return ['data' => [], 'total' => 0, 'per_page' => 15, 'current_page' => 1];
                }
            };
        }
    }
}

// ════════════════════════════════════════════════════════════
// 3. 加载生产代码
//    StorageManager::list() 是此套件的被测入口。
//    仅测试 list/getPaginate 路径，upload/batch 等方法不会被调用。
// ════════════════════════════════════════════════════════════
namespace
{
    require __DIR__ . '/../../app/api/service/Storage/StorageManager.php';
}

// ════════════════════════════════════════════════════════════
// 4. listOwn() 生产入口代理
//    所有 strict-owner 断言必须调用生产 StorageManager::listOwn()，
//    禁止在测试中复制一份参考业务逻辑。
// ════════════════════════════════════════════════════════════
namespace
{
    /**
     * 严格本人文件列表生产入口。
     *
     * @param array $params  查询参数（page, list_rows, scene, status, upload_status …）
     * @param int   $userId  当前认证用户 ID
     * @return array        分页结果
     */
    function listOwn(array $params, int $userId): array
    {
        return \app\api\service\Storage\StorageManager::listOwn($params, $userId);
    }
}

// ════════════════════════════════════════════════════════════
// 5. 单元测试工具函数
// ════════════════════════════════════════════════════════════
namespace
{
    /**
     * 将 $where 数组解析为平面调用记录。
     *
     * - Closure → 以 FilesQuery 作为 $q 执行，捕获内部 where/whereOr
     * - []      → 按 ThinkPHP array-form 约定映射到 mock 签名
     *
     * ThinkPHP array-form:   [field, op, value] 或 [field, value]
     * Mock where() 签名:     where($field, $value, $op)
     *
     * @param array $where
     * @return array{0:string,1:string,2:mixed,3?:string}[]
     */
    function resolveWhere(array $where): array
    {
        $query = new \app\api\model\FilesQuery;
        foreach ($where as $condition) {
            if ($condition instanceof \Closure) {
                $condition($query);
            } elseif (is_array($condition) && count($condition) >= 2) {
                // [field, op, value] → where(field, value, op)
                if (count($condition) >= 3) {
                    $query->where($condition[0], $condition[2], $condition[1]);
                } else {
                    // [field, value] → where(field, value)
                    $query->where($condition[0], $condition[1]);
                }
            }
        }
        return $query->calls;
    }

    /**
     * 检查调用记录中是否存在指定方法+字段的调用。
     */
    function hasCall(array $calls, string $method, string $field, $value = null): bool
    {
        foreach ($calls as $call) {
            if ($call[0] !== $method) {
                continue;
            }
            if ($call[1] !== $field) {
                continue;
            }
            if ($value !== null && array_key_exists(2, $call) && $call[2] !== $value) {
                continue;
            }
            return true;
        }
        return false;
    }
}

// ════════════════════════════════════════════════════════════
// 6. 测试框架
// ════════════════════════════════════════════════════════════
namespace
{
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

    /**
     * 重置全部 mock 静态状态。每个测试开头必须调用。
     */
    $reset = static function (): void {
        \app\common\support\ModelList::$capturedWhere     = [];
        \app\common\support\ModelList::$withTrashedCalled = false;
        \app\common\support\ModelList::$onlyTrashedCalled = false;

        // 清理 listOwn 调用的副作用（它通过 Mock ModelList 注册静态状态）
    };
}

// ════════════════════════════════════════════════════════════
// 7. 测试用例
// ════════════════════════════════════════════════════════════
namespace
{
    // ──────────────────────────────────────────────────────
    // 组 A：现有 list() 语义基线
    // ──────────────────────────────────────────────────────

    $test('A1: 非管理员 list 构建 (user_id OR is_public) 可见范围', static function () use ($reset, $assertTrue): void {
        $reset();
        \app\api\service\Storage\StorageManager::list([], 10, false);

        $where = \app\common\support\ModelList::$capturedWhere;
        $calls = resolveWhere($where);

        $assertTrue(
            hasCall($calls, 'where', 'user_id', 10),
            '非管理员可见范围应包含 where(user_id, 10)'
        );
        $assertTrue(
            hasCall($calls, 'whereOr', 'is_public', 1),
            '非管理员可见范围应包含 whereOr(is_public, 1)'
        );
    });

    $test('A2: 管理员 list 无 owner 限制', static function () use ($reset, $assertSame): void {
        $reset();
        \app\api\service\Storage\StorageManager::list([], 10, true);

        $where = \app\common\support\ModelList::$capturedWhere;
        $calls = resolveWhere($where);

        $userWhere  = array_filter($calls, fn($c) => $c[0] === 'where' && $c[1] === 'user_id');
        $orPublic   = array_filter($calls, fn($c) => $c[0] === 'whereOr' && $c[1] === 'is_public');

        $assertSame(0, count($userWhere), '管理员 list 不应有 user_id 条件');
        $assertSame(0, count($orPublic), '管理员 list 不应有 is_public OR 条件');
    });

    $test('A3: 管理员可通过 show_deleted 查看软删除记录', static function () use ($reset, $assertTrue): void {
        $reset();
        \app\api\service\Storage\StorageManager::list(['show_deleted' => 1], 10, true);

        $assertTrue(
            \app\common\support\ModelList::$withTrashedCalled,
            '管理员 show_deleted=1 应调用 withTrashed'
        );
    });

    $test('A4: 非管理员无法通过 show_deleted 绕过软删除过滤', static function () use ($reset, $assertFalse): void {
        $reset();
        \app\api\service\Storage\StorageManager::list(['show_deleted' => 1], 10, false);

        $assertFalse(
            \app\common\support\ModelList::$withTrashedCalled,
            '非管理员 show_deleted 不应生效'
        );
    });

    // ──────────────────────────────────────────────────────
    // 组 B：strict owner 查询
    // ──────────────────────────────────────────────────────

    $test('B1: strict owner 使用 user_id = uid 不含 is_public 放宽', static function () use ($reset, $assertTrue, $assertFalse): void {
        $reset();
        listOwn([], 10);

        $where = \app\common\support\ModelList::$capturedWhere;
        $calls = resolveWhere($where);

        $assertTrue(
            hasCall($calls, 'where', 'user_id', 10),
            'strict owner 应有 where(user_id, 10)'
        );
        $assertFalse(
            hasCall($calls, 'whereOr', 'is_public'),
            'strict owner 不应有 is_public OR'
        );
        $assertFalse(
            hasCall($calls, 'where', 'is_public'),
            'strict owner 不应有任何 is_public 条件'
        );
    });

    $test('B2: strict owner 有且只有一个 user_id 条件', static function () use ($reset, $assertSame): void {
        $reset();
        listOwn([], 42);

        $where = \app\common\support\ModelList::$capturedWhere;
        $calls = resolveWhere($where);

        $count = 0;
        foreach ($calls as $c) {
            if ($c[0] === 'where' && $c[1] === 'user_id') {
                $count++;
            }
        }
        $assertSame(1, $count, 'strict owner 应有且仅有一个 user_id 条件');
    });

    // ──────────────────────────────────────────────────────
    // 组 C：筛选参数兼容 strict owner
    // ──────────────────────────────────────────────────────

    $test('C1: strict owner + scene 筛选同步生效', static function () use ($reset, $assertSame): void {
        $reset();
        listOwn(['scene' => 'card'], 10);

        $where = \app\common\support\ModelList::$capturedWhere;
        $calls = resolveWhere($where);

        $assertSame(2, count($calls), '应有 2 个条件: user_id + scene');
        $assertSame('where', $calls[0][0], '第 1 个条件应为 where');
        $assertSame('user_id', $calls[0][1], '第 1 个应为 user_id');
        $assertSame(10, $calls[0][2], 'user_id 值应为 10');
        $assertSame('where', $calls[1][0], '第 2 个条件应为 where');
        $assertSame('scene', $calls[1][1], '第 2 个应为 scene');
        $assertSame('card', $calls[1][2], 'scene 值应为 card');
    });

    $test('C2: strict owner + status 筛选同步生效', static function () use ($reset, $assertSame): void {
        $reset();
        listOwn(['status' => 0], 10);

        $where = \app\common\support\ModelList::$capturedWhere;
        $calls = resolveWhere($where);

        $assertSame(2, count($calls));
        $assertSame('where', $calls[0][0]);
        $assertSame('user_id', $calls[0][1]);
        $assertSame('where', $calls[1][0]);
        $assertSame('status', $calls[1][1]);
        $assertSame(0, $calls[1][2]);
    });

    $test('C3: strict owner + upload_status 筛选同步生效', static function () use ($reset, $assertSame): void {
        $reset();
        listOwn(['upload_status' => 1], 10);

        $where = \app\common\support\ModelList::$capturedWhere;
        $calls = resolveWhere($where);

        $assertSame(2, count($calls));
        $assertSame('where', $calls[0][0]);
        $assertSame('user_id', $calls[0][1]);
        $assertSame('where', $calls[1][0]);
        $assertSame('upload_status', $calls[1][1]);
        $assertSame(1, $calls[1][2]);
    });

    $test('C4: strict owner + scene + status + upload_status 三维组合', static function () use ($reset, $assertSame): void {
        $reset();
        listOwn(['scene' => 'avatar', 'status' => 0, 'upload_status' => 1], 10);

        $where = \app\common\support\ModelList::$capturedWhere;
        $calls = resolveWhere($where);

        $assertSame(4, count($calls), '应有 4 个条件: user_id + scene + status + upload_status');
        $assertSame('where', $calls[0][0]);
        $assertSame('user_id', $calls[0][1]);
        $assertSame(10, $calls[0][2]);
    });

    $test('C5: strict owner + ref_type 筛选同步生效', static function () use ($reset, $assertTrue): void {
        $reset();
        listOwn(['ref_type' => 'card'], 10);

        $calls = resolveWhere(\app\common\support\ModelList::$capturedWhere);
        $assertTrue(hasCall($calls, 'where', 'user_id', 10));
        $assertTrue(hasCall($calls, 'where', 'ref_type', 'card'));
    });

    $test('C6: strict owner + ref_id 筛选同步生效', static function () use ($reset, $assertTrue): void {
        $reset();
        listOwn(['ref_id' => 123], 10);

        $calls = resolveWhere(\app\common\support\ModelList::$capturedWhere);
        $assertTrue(hasCall($calls, 'where', 'user_id', 10));
        $assertTrue(hasCall($calls, 'where', 'ref_id', 123));
    });

    // ──────────────────────────────────────────────────────
    // 组 D：软删除过滤
    // ──────────────────────────────────────────────────────

    $test('D1: strict owner 默认排除软删除记录', static function () use ($reset, $assertFalse): void {
        $reset();
        listOwn([], 10);

        $assertFalse(
            \app\common\support\ModelList::$withTrashedCalled,
            'strict owner 不应调用 withTrashed（SoftDelete 默认过滤）'
        );
    });

    $test('D2: strict owner 即使误传 show_deleted 也不暴露删除记录', static function () use ($reset, $assertFalse): void {
        $reset();
        listOwn(['show_deleted' => 1], 10);

        $assertFalse(
            \app\common\support\ModelList::$withTrashedCalled,
            'strict owner 不响应 show_deleted 参数'
        );
    });

    // ──────────────────────────────────────────────────────
    // 组 E：认证守卫（规格注释 — 不可执行）
    // ──────────────────────────────────────────────────────
    //
    // E1: 无有效 token 应返回 401
    //     Route 定义应注册 GET /users/me/files：
    //
    //         Route::get('users/me/files', 'Storage.Upload/listOwn')
    //             ->name('files.listOwn')
    //             ->middleware(JwtAuthCheck::class);
    //         // 不加 PermissionCheck — 文件归属由 listOwn 保证
    //
    //     JwtAuthCheck 在 token 缺失/无效时返回 401。
    //
    // E2: 有效 token 返回本人文件
    //     Controller 方法：
    //
    //         public function listMe()
    //         {
    //             $userId = request()->auth->uid();
    //             if ($userId <= 0) {
    //                 throw ApiException::unauthorized('请先登入');
    //             }
    //             $params = $this->paramIndex(Request::param());
    //             $result = StorageManager::listOwn($params, $userId);
    //             return ApiResponse::createOk($result);
    //         }
    //
    // E3: isAdmin 对 strict owner 无影响
    //     listOwn() 不接受 $isAdmin 参数，所有用户一视同仁。
    //     管理员也无法通过此端点查看其他用户的文件。
}

// ════════════════════════════════════════════════════════════
// 8. 运行器
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
        fwrite(STDERR, "\n{$failures} Files behavior baseline test(s) failed.\n");
        exit(1);
    }

    fwrite(STDOUT, "\n" . count($tests) . " Files behavior baseline tests passed.\n");
}
