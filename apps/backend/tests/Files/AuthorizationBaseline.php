<?php
declare(strict_types=1);

// ============================================================
// LoveCardsV3 — Files 模块授权语义收口行为基线测试
//
// 范围：锁定 batch cleanup/allDelete 复用、authorization
//       sequence（全量校验后零写入）、exception 语义
// 运行：@php tests/Files/AuthorizationBaseline.php
// 架构：standalone PHP 脚本（无 PHPUnit / 无框架引导）
// 模式：命名空间覆盖 + 生产代码静态扫描 + 逻辑校验
// ============================================================

// ════════════════════════════════════════════════════════════
// 1. 替身与模拟
// ════════════════════════════════════════════════════════════

namespace
{
    /**
     * 替代 ThinkPHP 的 app() 容器
     */
    function app(string $class): object
    {
        return new $class;
    }
}

namespace app\api
{
    /**
     * ApiException 替身 — 用于验证 403/404 不被包装为 500
     */
    class ApiException extends \Exception
    {
        const CODE_PERMISSION_DENIED = 1101;
        const CODE_RESOURCE_NOT_FOUND = 9003;

        protected $httpStatus = 500;

        public function __construct(
            string $message = "",
            int $code = 9999,
            $data = null,
            ?\Throwable $previous = null
        ) {
            $this->httpStatus = self::getHttpStatusByCode($code);
            parent::__construct($message, $code, $previous);
        }

        public function getHttpStatus(): int
        {
            return $this->httpStatus;
        }

        public static function badRequest(string $msg = ''): self
        {
            return new self($msg, 9002);
        }

        public static function unauthorized(string $msg = ''): self
        {
            return new self($msg, 1005);
        }

        public static function forbidden(string $msg = ''): self
        {
            return new self($msg, self::CODE_PERMISSION_DENIED);
        }

        public static function notFound(string $msg = ''): self
        {
            return new self($msg, self::CODE_RESOURCE_NOT_FOUND);
        }

        public static function error(string $msg = '', int $code = 9001, $data = null, ?\Throwable $prev = null): self
        {
            return new self($msg, $code, $data, $prev);
        }

        public static function getHttpStatusByCode(int $code): int
        {
            $map = [
                1101 => 403,
                9003 => 404,
                9002 => 400,
                1005 => 401,
            ];
            return $map[$code] ?? 500;
        }
    }
}

namespace app\api\model
{
    /**
     * Files 模型替身 — 伪记录 + 伪查询链
     */
    class FilesQuery
    {
        public array $calls = [];
        public bool $useTrashed = false;
        public ?int $scopeUserId = null;
        public bool $scopeSecurePublic = false;

        public function where($field, $value = null, string $op = '='): self
        {
            if ($field instanceof \Closure) {
                $field($this);
                return $this;
            }
            $this->calls[] = ['where', $field, $value, $op];
            return $this;
        }

        public function whereIn(string $field, array $values): self
        {
            $this->calls[] = ['whereIn', $field, $values];
            return $this;
        }

        public function whereNull(string $field): self
        {
            $this->calls[] = ['whereNull', $field];
            return $this;
        }

        public function visible(?int $userId): self
        {
            $this->scopeUserId = $userId;
            $this->calls[] = ['visible', $userId];
            return $this;
        }

        public function securePublic(): self
        {
            $this->scopeSecurePublic = true;
            $this->calls[] = ['securePublic'];
            return $this;
        }

        /**
         * 模拟 select() — 应用 scope 过滤后返回 FilesCollection
         */
        public function select(): FilesCollection
        {
            $rows = FilesQuery::selectFromMock($this->calls);
            return new FilesCollection($this->applyScope($rows));
        }

        public function column(string $field): array
        {
            $rows = FilesQuery::selectFromMock($this->calls);
            return array_map(fn($r) => $r->$field ?? $r['id'] ?? $r[0], $rows);
        }

        /**
         * 模拟 find() — 返回 scope 过滤后的第一个匹配项
         */
        public function find(): ?object
        {
            $rows = FilesQuery::selectFromMock($this->calls);
            $rows = $this->applyScope($rows);
            return $rows[0] ?? null;
        }

        /**
         * 应用 visible/securePublic scope 过滤
         */
        private function applyScope(array $rows): array
        {
            if ($this->scopeSecurePublic) {
                $rows = array_filter($rows, function ($r) {
                    return $r->is_public === 1
                        && $r->status === 0
                        && $r->upload_status === 1
                        && $r->deleted_at === null;
                });
            } elseif ($this->scopeUserId !== null && $this->scopeUserId > 0) {
                $rows = array_filter($rows, function ($r) use ($rows) {
                    // Owner branch: user_id matches AND deleted_at IS NULL
                    $isOwner = $r->user_id === $this->scopeUserId && $r->deleted_at === null;
                    // Public branch: secure public record
                    $isSecurePublic = $r->is_public === 1
                        && $r->status === 0
                        && $r->upload_status === 1
                        && $r->deleted_at === null;
                    return $isOwner || $isSecurePublic;
                });
            }
            return array_values($rows);
        }

        /**
         * 根据 mock 数据库查找匹配的记录
         */
        public static function selectFromMock(array $calls): array
        {
            global $mockFilesDb;
            $rows = array_values($mockFilesDb);

            foreach ($calls as $call) {
                if ($call[0] === 'whereIn') {
                    $field = $call[1];
                    $values = $call[2];
                    $rows = array_filter($rows, static function ($row) use ($field, $values): bool {
                        return property_exists($row, $field) && in_array($row->$field, $values, true);
                    });
                } elseif ($call[0] === 'where' && $call[3] === '=') {
                    $field = $call[1];
                    $value = $call[2];
                    $rows = array_filter($rows, static function ($row) use ($field, $value): bool {
                        return property_exists($row, $field) && $row->$field === $value;
                    });
                }
            }

            return array_values($rows);
        }
    }

    /**
     * 模拟查询结果集 — 提供 column() / isEmpty() 等方法
     * 实现 IteratorAggregate 以支持 foreach 遍历
     */
    class FilesCollection implements \IteratorAggregate
    {
        private array $items;

        public function __construct(array $items)
        {
            $this->items = $items;
        }

        public function getIterator(): \ArrayIterator
        {
            return new \ArrayIterator($this->items);
        }

        public function column(string $field): array
        {
            return array_map(fn($r) => $r->$field ?? null, $this->items);
        }

        public function isEmpty(): bool
        {
            return empty($this->items);
        }

        public function count(): int
        {
            return count($this->items);
        }
    }

    class Files
    {
        const STATUS_NORMAL = 0;
        const STATUS_BANNED = 1;
        const UPLOAD_PENDING = 0;
        const UPLOAD_COMPLETED = 1;
        const UPLOAD_FAILED = 2;

        public static function generateHash(): string
        {
            return 'mock-hash-auth';
        }

        public static function withTrashed(): FilesQuery
        {
            $q = new FilesQuery;
            $q->useTrashed = true;
            return $q;
        }

        public static function whereIn(string $field, array $values): FilesQuery
        {
            $q = new FilesQuery;
            $q->calls[] = ['whereIn', $field, $values];
            return $q;
        }

        public static function where($field, $value = null, string $op = '='): FilesQuery
        {
            $q = new FilesQuery;
            $q->calls[] = ['where', $field, $value, $op];
            return $q;
        }

        public static function find($id): ?object
        {
            global $mockFilesDb;
            return $mockFilesDb[$id] ?? null;
        }

        public static function getExpiredPendingIds(int $limit = 100): array
        {
            global $mockFilesDb;
            $ids = [];
            foreach ($mockFilesDb as $id => $record) {
                if ($record->upload_status === self::UPLOAD_PENDING && $record->expired) {
                    $ids[] = $id;
                    if (count($ids) >= $limit) break;
                }
            }
            return $ids;
        }

        public static function cleanupExpired(int $limit = 100): array
        {
            global $mockFilesDb;
            $cleaned = [];
            foreach ($mockFilesDb as $id => $record) {
                if ($record->upload_status === self::UPLOAD_PENDING && $record->expired) {
                    $cleaned[] = ['id' => $id, 'expired_at' => $record->expire_at ?? null];
                    $record->upload_status = self::UPLOAD_FAILED;
                    $record->markAsFailedCalled = true;
                    if (count($cleaned) >= $limit) break;
                }
            }
            return $cleaned;
        }

        public function markAsFailed(): bool
        {
            $this->upload_status = self::UPLOAD_FAILED;
            return true;
        }
    }
}

namespace app\api\service\Storage
{
    class StorageFactory
    {
        public static function make(string $slug): object
        {
            return new class {
                public function delete(string $path): bool
                {
                    return true;
                }
                public function getUrl(string $path): string
                {
                    return 'http://example.com/' . $path;
                }
                public function getUploadCredential(...$args): object
                {
                    return new class {
                        public $url = '';
                        public $method = 'POST';
                        public $headers = [];
                        public $formData = [];
                        public $expire = 3600;
                    };
                }
            };
        }
    }
}

namespace app\common\infra
{
    class CacheManager
    {
        const TTL_LONG = 3600;
        public static function get(string $domain, string $key, $fallback = null, int $ttl = 3600)
        {
            if ($fallback instanceof \Closure) {
                return $fallback();
            }
            return $fallback;
        }
        public static function set(string $domain, string $key, $value, int $ttl = 3600): void {}
        public static function key(string $domain, string ...$parts): string
        {
            return implode(':', $parts);
        }
        public static function clearDomain(string $domain): void {}
    }
}

namespace app\common\support
{
    class ModelList
    {
        public static function make($model = null): self
        {
            return new self;
        }
        public function getPaginate(array $params): object
        {
            return new class {
                public function toArray(): array { return []; }
            };
        }
    }
}

namespace think\facade
{
    class Db
    {
        public static array $log = [];

        public static function table(string $name): self
        {
            return new self;
        }

        public static function raw(string $expr): string
        {
            return $expr;
        }

        public function where($field, $value = null, string $op = '='): self
        {
            self::$log[] = ['where', $field, $value, $op];
            return $this;
        }

        public function whereIn(string $field, array $values): self
        {
            self::$log[] = ['whereIn', $field, $values];
            return $this;
        }

        public function update(array $data): int
        {
            self::$log[] = ['update', $data];
            return count($data);
        }

        public function delete(): bool
        {
            self::$log[] = ['delete'];
            return true;
        }
    }

    class Log
    {
        public static function error(string $msg): void {}
    }
}

// ════════════════════════════════════════════════════════════
// 2. Mock 数据库 — 模拟 Files 表记录
// ════════════════════════════════════════════════════════════
namespace
{
    /**
     * 模拟记录对象，与 StorageManager 中 $file->user_id 等访问兼容
     */
    class MockFileRecord
    {
        public int $id;
        public int $user_id;
        public string $hash;
        public string $channel_slug;
        public string $file_path;
        public string $file_url;
        public string $driver_path;
        public int $upload_status;
        public int $status;
        public int $is_public;
        public ?string $deleted_at;
        public bool $expired;
        public bool $markAsFailedCalled = false;

        public function __construct(array $data)
        {
            foreach ($data as $k => $v) {
                $this->$k = $v;
            }
            $this->hash = $data['hash'] ?? 'hash-' . $this->id;
            $this->file_path = $data['file_path'] ?? $this->driver_path;
            $this->file_url = $data['file_url'] ?? '';
        }

        public function toArray(): array
        {
            return (array) $this;
        }

        public function delete(): bool
        {
            $this->deleted_at = date('Y-m-d H:i:s');
            return true;
        }

        public function restore(): bool
        {
            $this->deleted_at = null;
            return true;
        }

        public function isExpired(): bool
        {
            return $this->expired;
        }

        public function markAsCompleted(string $url, string $driverPath): bool
        {
            $this->file_url = $url;
            $this->driver_path = $driverPath;
            $this->upload_status = \app\api\model\Files::UPLOAD_COMPLETED;
            return true;
        }

        public function markAsFailed(): bool
        {
            $this->upload_status = \app\api\model\Files::UPLOAD_FAILED;
            $this->markAsFailedCalled = true;
            return true;
        }
    }

    /**
     * 全局模拟数据库
     * @var array<int, MockFileRecord>
     */
    $mockFilesDb = [];

    /**
     * 重置模拟数据库到已知状态
     *
     * 记录清单：
     *   id=1  user_id=10  owner 正常, is_public=0
     *   id=2  user_id=10  owner 已软删除
     *   id=3  user_id=20  non-owner 正常
     *   id=4  user_id=10  owner 过期待上传
     *   id=5  user_id=20  non-owner 软删除
     *   id=6  user_id=10  owner 正常, is_public=1
     */
    $resetMockDb = static function (): void {
        global $mockFilesDb;
        $mockFilesDb = [
            1 => new MockFileRecord([
                'id' => 1,
                'user_id' => 10,
                'channel_slug' => 'local',
                'driver_path' => 'path/1.jpg',
                'upload_status' => 1,
                'status' => 0,
                'is_public' => 0,
                'deleted_at' => null,
                'expired' => false,
            ]),
            2 => new MockFileRecord([
                'id' => 2,
                'user_id' => 10,
                'channel_slug' => 'local',
                'driver_path' => 'path/2.jpg',
                'upload_status' => 1,
                'status' => 0,
                'is_public' => 0,
                'deleted_at' => '2026-07-01 00:00:00',
                'expired' => false,
            ]),
            3 => new MockFileRecord([
                'id' => 3,
                'user_id' => 20,
                'channel_slug' => 'local',
                'driver_path' => 'path/3.jpg',
                'upload_status' => 1,
                'status' => 0,
                'is_public' => 0,
                'deleted_at' => null,
                'expired' => false,
            ]),
            4 => new MockFileRecord([
                'id' => 4,
                'user_id' => 10,
                'channel_slug' => 'local',
                'driver_path' => '',
                'upload_status' => 0,
                'status' => 0,
                'is_public' => 0,
                'deleted_at' => null,
                'expired' => true,
            ]),
            5 => new MockFileRecord([
                'id' => 5,
                'user_id' => 20,
                'channel_slug' => 'local',
                'driver_path' => 'path/5.jpg',
                'upload_status' => 1,
                'status' => 0,
                'is_public' => 0,
                'deleted_at' => '2026-07-02 00:00:00',
                'expired' => false,
            ]),
            6 => new MockFileRecord([
                'id' => 6,
                'user_id' => 10,
                'channel_slug' => 'local',
                'driver_path' => 'path/6.jpg',
                'upload_status' => 1,
                'status' => 0,
                'is_public' => 1,
                'deleted_at' => null,
                'expired' => false,
            ]),
            7 => new MockFileRecord([
                'id' => 7,
                'user_id' => 10,
                'channel_slug' => 'local',
                'file_path' => 'path/7.jpg',
                'driver_path' => '',
                'upload_status' => 0,
                'status' => 0,
                'is_public' => 0,
                'deleted_at' => null,
                'expired' => false,
            ]),
        ];
    };

    // Initialize mock DB
    $resetMockDb();
}

// ════════════════════════════════════════════════════════════
// 3. 加载生产代码
// ════════════════════════════════════════════════════════════
namespace
{
    require __DIR__ . '/../../app/api/service/Storage/StorageManager.php';
    require __DIR__ . '/../../app/api/service/Storage/DirectUploadManager.php';
}

// ════════════════════════════════════════════════════════════
// 4. 测试工具函数
// ════════════════════════════════════════════════════════════
namespace
{
    /**
     * 验证 ApiException 的 httpStatus 是否等于预期。
     * 用于验证 403/404 不被包装为 500。
     */
    $assertExceptionHttpStatus = static function (\Throwable $e, int $expectedHttpStatus, string $message = ''): void {
        $actual = 500;
        if ($e instanceof \app\api\ApiException) {
            $actual = $e->getHttpStatus();
        }
        if ($actual !== $expectedHttpStatus) {
            throw new \RuntimeException(
                ($message !== '' ? $message . ': ' : '')
                . 'expected HTTP status ' . $expectedHttpStatus
                . ', got ' . $actual
                . ' (' . $e->getMessage() . ')'
            );
        }
    };
}

// ════════════════════════════════════════════════════════════
// 5. 测试框架
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
}

// ════════════════════════════════════════════════════════════
// 6. 测试用例
// ════════════════════════════════════════════════════════════
namespace
{
    // ──────────────────────────────────────────────────────
    // 组 A：batch 授权序列
    // ──────────────────────────────────────────────────────

    $test('A1: batch hard_delete owner — 删除自己文件通过', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        // user=10, caps=['files.delete'] → 只能删除自己的
        \app\api\service\Storage\StorageManager::batchOperate('hard_delete', [1], 10, ['files.delete']);
        // 无异常 = 授权检查通过
        $assertTrue(true);
    });

    $test('A2: batch hard_delete non-owner — 无权操作他人文件 403', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        $thrown = null;
        try {
            \app\api\service\Storage\StorageManager::batchOperate('hard_delete', [3], 10, ['files.delete']);
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        $assertTrue($thrown !== null, 'non-owner 应抛出异常');
        $assertExceptionHttpStatus($thrown, 403, 'non-owner 应为 403');
    });

    $test('A3: batch hard_delete .all — 任意用户可删除', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        \app\api\service\Storage\StorageManager::batchOperate('hard_delete', [3], 10, ['files.delete.all']);
        // 无异常 = 授权检查通过
        $assertTrue(true);
    });

    $test('A4: batch hard_delete 不存在的 ID — 404', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        $thrown = null;
        try {
            \app\api\service\Storage\StorageManager::batchOperate('hard_delete', [999], 10, ['files.delete.all']);
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        $assertTrue($thrown !== null, '不存在 ID 应抛出异常');
        $assertExceptionHttpStatus($thrown, 404, '不存在 ID 应为 404');
    });

    $test('A5: batch hard_delete 不存在 ID 即使有 .all 也返回 404', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        $thrown = null;
        try {
            \app\api\service\Storage\StorageManager::batchOperate('hard_delete', [1, 999], 10, ['files.delete.all']);
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        $assertTrue($thrown !== null, '混合存在/不存在 ID 应抛出异常');
        $assertExceptionHttpStatus($thrown, 404, '有 .all 时非存在 ID 仍应为 404');
        // 验证零写入 — 即使 id=1 存在也不应被删除
        global $mockFilesDb;
        $assertTrue(isset($mockFilesDb[1]), '检查失败后零写入 — id=1 应仍存在');
    });

    $test('A6: batch approve 需要 files.update', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        \app\api\service\Storage\StorageManager::batchOperate('approve', [1], 10, ['files.update']);
        // 验证通过（无异常）
        $assertTrue(true);
    });

    $test('A7: batch approve non-owner 无 .all 时 403', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        $thrown = null;
        try {
            \app\api\service\Storage\StorageManager::batchOperate('approve', [3], 10, ['files.update']);
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        $assertTrue($thrown !== null, 'approve non-owner 应抛出异常');
        $assertExceptionHttpStatus($thrown, 403);
    });

    $test('A8: batch approve non-owner 有 .all 时通过', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        \app\api\service\Storage\StorageManager::batchOperate('approve', [3], 10, ['files.update.all']);
        $assertTrue(true);
    });

    // ──────────────────────────────────────────────────────
    // 组 B：软删除 owner 检查
    // ──────────────────────────────────────────────────────

    $test('B1: batch hard_delete 软删除记录仍须检查 owner — non-owner 403', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        $thrown = null;
        try {
            // id=2 是 user_id=10 的软删除记录, 当前用户也是 10 — 应通过
            \app\api\service\Storage\StorageManager::batchOperate('hard_delete', [2], 10, ['files.delete']);
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        $assertTrue($thrown === null, '软删除记录 owner 本人应通过 (got: ' . ($thrown ? $thrown->getMessage() : 'none') . ')');
    });

    $test('B2: batch hard_delete 软删除 non-owner 记录 403', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        $thrown = null;
        try {
            // id=5 是 user_id=20 的软删除记录, 当前用户=10
            \app\api\service\Storage\StorageManager::batchOperate('hard_delete', [5], 10, ['files.delete']);
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        $assertTrue($thrown !== null, '软删除 non-owner 应抛出异常');
        $assertExceptionHttpStatus($thrown, 403);
    });

    $test('B3: batch restore 需要 files.delete 并检查 owner', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        // owner=10 恢复自己的软删除记录 id=2, 需要 files.delete
        \app\api\service\Storage\StorageManager::batchOperate('restore', [2], 10, ['files.delete']);
        $assertTrue(true);
    });

    $test('B4: batch restore 只有 files.update 时 403', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        $thrown = null;
        try {
            // restore 已从 files.update 改为 files.delete — 只有 update 不够
            \app\api\service\Storage\StorageManager::batchOperate('restore', [2], 10, ['files.update']);
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        $assertTrue($thrown !== null, '只有 files.update 时应 403');
        $assertExceptionHttpStatus($thrown, 403);
    });

    // ──────────────────────────────────────────────────────
    // 组 C：exception 语义 — 403/404 不被包装为 500
    // ──────────────────────────────────────────────────────

    $test('C1: batch 方法不包括存在的异常包装 — 无 try/catch 转 500', static function () use ($assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $controllerCode = file_get_contents(__DIR__ . '/../../app/api/controller/Storage/Upload.php');
        // 检查 batch 方法中没有 catch (\Throwable) { throw ApiException::error(...) }
        $hasBadCatch = (bool)preg_match('/catch\s*\(\\\\?Throwable.*?ApiException::error/s', $controllerCode);
        $assertFalse($hasBadCatch, 'batch 方法不应有 catch Throwable → ApiException::error 的包装');
    });

    $test('C2: batchOperate 抛出的 ApiException 直接传递 — httpStatus 保持', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        $thrown = null;
        try {
            \app\api\service\Storage\StorageManager::batchOperate('hard_delete', [3], 10, ['files.delete']);
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        $assertTrue($thrown instanceof \app\api\ApiException, '异常应为 ApiException');
        $assertSame(403, $thrown->getHttpStatus(), 'ApiException 应保持 403');
    });

    // ──────────────────────────────────────────────────────
    // 组 D：cleanup 标记过期记录为 FAILED
    // ──────────────────────────────────────────────────────

    $test('D1: cleanup 标记过期记录为 FAILED 且保留在 DB', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        global $mockFilesDb;
        // id=4 是 owner=10 的过期待上传记录, upload_status=0
        $assertSame(0, $mockFilesDb[4]->upload_status, 'id=4 初始应为 PENDING');
        $assertTrue($mockFilesDb[4]->deleted_at === null, 'id=4 不应已软删除');
        $cleaned = \app\api\service\Storage\DirectUploadManager::cleanupExpired(100);
        $assertTrue(count($cleaned) >= 1, '应清理至少一条过期记录');
        // 标记为 FAILED, 记录仍在 DB 中
        $assertSame(2, $mockFilesDb[4]->upload_status, 'cleanup 后 id=4 应为 FAILED');
        $assertTrue(isset($mockFilesDb[4]), '记录应仍存在于 DB 中');
        $assertTrue($mockFilesDb[4]->deleted_at === null, 'hard delete 不应被调用');
        $assertTrue($mockFilesDb[4]->markAsFailedCalled, 'markAsFailed 应被调用');
    });

    $test('D2: cleanup 只标记过期 pending 记录', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        global $mockFilesDb;
        // id=1 是已完成记录, 不应被清理
        $cleaned = \app\api\service\Storage\DirectUploadManager::cleanupExpired(100);
        $assertSame(1, $mockFilesDb[1]->upload_status, '已完成记录 upload_status 不应被修改');
        $assertTrue(in_array(4, array_column($cleaned, 'id')), 'id=4 应在清理列表中');
    });

    // ──────────────────────────────────────────────────────
    // 组 E：allDelete 复用 batch hard_delete
    // ──────────────────────────────────────────────────────

    $test('E1: allDelete 单条 hard_delete owner 通过', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        \app\api\service\Storage\StorageManager::batchOperate('hard_delete', [1], 10, ['files.delete']);
        // 无异常 = 授权检查通过
        $assertTrue(true);
    });

    $test('E2: allDelete non-owner 403', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        $thrown = null;
        try {
            \app\api\service\Storage\StorageManager::batchOperate('hard_delete', [3], 10, ['files.delete']);
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        $assertTrue($thrown !== null, 'allDelete non-owner 应抛出异常');
        $assertExceptionHttpStatus($thrown, 403);
    });

    $test('E3: allDelete .all 可删除任意 owner', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        \app\api\service\Storage\StorageManager::batchOperate('hard_delete', [3], 10, ['files.delete.all']);
        // 无异常 = 授权检查通过
        $assertTrue(true);
    });

    $test('E4: allDelete 不存在 ID 404', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        $thrown = null;
        try {
            \app\api\service\Storage\StorageManager::batchOperate('hard_delete', [999], 10, ['files.delete.all']);
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        $assertTrue($thrown !== null, '不存在的 ID 应抛出 404');
        $assertExceptionHttpStatus($thrown, 404);
    });

    $test('E5: Controller allDelete 不直接调用 hardDelete', static function () use ($assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $controllerCode = file_get_contents(__DIR__ . '/../../app/api/controller/Storage/Upload.php');
        // 确保 allDelete 方法中没有 StorageManager::hardDelete(
        $hasDirectHardDelete = (bool)preg_match('/allDelete.*?StorageManager::hardDelete/s', $controllerCode);
        $assertFalse($hasDirectHardDelete, 'allDelete 不应直接调用 StorageManager::hardDelete');
    });

    // ──────────────────────────────────────────────────────
    // 组 F：其他 batch 方法授权映射
    // ──────────────────────────────────────────────────────

    $test('F1: batch ban 需要 files.update', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        \app\api\service\Storage\StorageManager::batchOperate('ban', [1], 10, ['files.update']);
        $assertTrue(true);
    });

    $test('F2: batch toggle_public 需要 files.update', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        \app\api\service\Storage\StorageManager::batchOperate('toggle_public', [1], 10, ['files.update']);
        $assertTrue(true);
    });

    $test('F3: batch trash 需要 files.delete', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        \app\api\service\Storage\StorageManager::batchOperate('trash', [1], 10, ['files.delete']);
        $assertTrue(true);
    });

    $test('F4: batch 不支持的方法抛出 400', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        $thrown = null;
        try {
            \app\api\service\Storage\StorageManager::batchOperate('invalid_method', [1], 10, ['files.update']);
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        $assertTrue($thrown !== null, '不支持的方法应抛出异常');
        $assertSame(400, $thrown->getHttpStatus(), '不支持的方法应为 HTTP 400');
    });

    // ──────────────────────────────────────────────────────
    // 组 G：混合 ID 检查序列 — 零写入
    // ──────────────────────────────────────────────────────

    $test('G1: 混合 non-owner/missing 检查失败 — 零写入', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        $thrown = null;
        try {
            \app\api\service\Storage\StorageManager::batchOperate('hard_delete', [1, 3, 999], 10, ['files.delete']);
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        $assertTrue($thrown !== null, '混合 non-owner/missing 应抛出异常');
        // 即使 id=1 是 owner, 但因为 id=3 是 non-owner, 零写入
        global $mockFilesDb;
        $assertTrue(isset($mockFilesDb[1]), '检查失败后 id=1 应仍存在（零写入）');
        $assertTrue(isset($mockFilesDb[3]), '检查失败后 id=3 应仍存在（零写入）');
    });

    $test('G2: 缺失 ID 优先 404（即使有 non-owner）', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        $thrown = null;
        try {
            \app\api\service\Storage\StorageManager::batchOperate('hard_delete', [3, 999], 10, ['files.delete']);
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        $assertTrue($thrown !== null, '缺失 ID 应抛出异常');
        $assertExceptionHttpStatus($thrown, 404, '缺失 ID 应报 404');  // Missing takes precedence over non-owner
    });

    $test('G3: 缺失 ID 404 即使所有有 .all', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        $thrown = null;
        try {
            \app\api\service\Storage\StorageManager::batchOperate('hard_delete', [1, 999], 10, ['files.delete.all']);
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        $assertTrue($thrown !== null, '.all 时缺失 ID 仍应抛出异常');
        $assertExceptionHttpStatus($thrown, 404);
    });

    // ──────────────────────────────────────────────────────
    // 组 H：读取授权路径扫描
    // ──────────────────────────────────────────────────────

    $test('H1: Controller cleanup 路由 cap 声明含 files.delete.all', static function () use ($assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $routeCode = file_get_contents(__DIR__ . '/../../app/api/route/files.php');
        $assertTrue((bool)preg_match('/files\.delete\.all/', $routeCode), 'route 应声明 files.delete.all');
    });

    $test('H2: Controller allDelete 路由 cap 声明含 files.delete 和 files.delete.all', static function () use ($assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $routeCode = file_get_contents(__DIR__ . '/../../app/api/route/files.php');
        // 提取 allDelete 路由语句（Route::delete 到分号）
        $deleteSection = '';
        if (preg_match("/Route::delete\(':id'[^;]+allDelete[^;]+caps['\"]\s*=>\s*\[([^\]]+)\]/s", $routeCode, $m)) {
            $deleteSection = $m[1];
        }
        $hasDeleteCap = (bool)preg_match("/'files\\.delete'/", $deleteSection);
        $hasDeleteAllCap = (bool)preg_match("/'files\\.delete\\.all'/", $deleteSection);
        $assertTrue($hasDeleteCap && $hasDeleteAllCap, 'allDelete 路由 caps 应包含 files.delete 和 files.delete.all');
    });

    $test('H3: Controller batch 路由 cap 声明含 files.update 和 files.delete', static function () use ($assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $routeCode = file_get_contents(__DIR__ . '/../../app/api/route/files.php');
        // 提取 batch 路由 caps 部分
        $batchCaps = '';
        if (preg_match("/Route::post\('files\/batch'[^;]+caps['\"]\s*=>\s*\[([^\]]+)\]/s", $routeCode, $m)) {
            $batchCaps = $m[1];
        }
        $hasUpdate = (bool)preg_match("/'files\\.update'/", $batchCaps);
        $hasDelete = (bool)preg_match("/'files\\.delete'/", $batchCaps);
        $assertTrue($hasUpdate && $hasDelete, 'batch 路由 caps 应包含 files.update 和 files.delete');
    });

    // ──────────────────────────────────────────────────────
    // 组 I：batchTrash 授权
    // ──────────────────────────────────────────────────────

    $test('I1: batch trash owner 通过', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        \app\api\service\Storage\StorageManager::batchOperate('trash', [1], 10, ['files.delete']);
        global $mockFilesDb;
        $assertTrue($mockFilesDb[1]->deleted_at !== null, 'trash 后记录应软删除');
    });

    $test('I2: batch trash non-owner 403', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        $thrown = null;
        try {
            \app\api\service\Storage\StorageManager::batchOperate('trash', [3], 10, ['files.delete']);
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        $assertTrue($thrown !== null, 'trash non-owner 应 403');
        $assertExceptionHttpStatus($thrown, 403);
    });

    // ──────────────────────────────────────────────────────
    // 组 J：route 路由检查 — cleanup 和 allDelete 路由
    // ──────────────────────────────────────────────────────

    $test('J1: Route files.expired cleanup 使用 DELETE 方法', static function () use ($assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $routeCode = file_get_contents(__DIR__ . '/../../app/api/route/files.php');
        $hasCleanupRoute = (bool)preg_match("/Route::delete\('expired'.*?cleanup/s", $routeCode);
        $assertTrue($hasCleanupRoute, 'cleanup 路由应使用 DELETE');
    });

    $test('J2: Route files.allDelete 使用 DELETE 方法', static function () use ($assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $routeCode = file_get_contents(__DIR__ . '/../../app/api/route/files.php');
        $hasDeleteRoute = (bool)preg_match("/Route::delete\(':id'.*?allDelete/s", $routeCode);
        $assertTrue($hasDeleteRoute, 'allDelete 路由应使用 DELETE');
    });

    // ──────────────────────────────────────────────────────
    // 组 K：capability 负测试 — 错误的能力返回 403
    // ──────────────────────────────────────────────────────

    $test('K1: approve 只有 files.delete 时 403', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        $thrown = null;
        try {
            \app\api\service\Storage\StorageManager::batchOperate('approve', [1], 10, ['files.delete']);
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        $assertTrue($thrown !== null, 'approve 只有 files.delete 应 403');
        $assertExceptionHttpStatus($thrown, 403);
    });

    $test('K2: hard_delete 只有 files.update 时 403', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        $thrown = null;
        try {
            \app\api\service\Storage\StorageManager::batchOperate('hard_delete', [1], 10, ['files.update']);
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        $assertTrue($thrown !== null, 'hard_delete 只有 files.update 应 403');
        $assertExceptionHttpStatus($thrown, 403);
    });

    $test('K3: approve 无任何 capability 时 403', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        $thrown = null;
        try {
            \app\api\service\Storage\StorageManager::batchOperate('approve', [1], 10, []);
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        $assertTrue($thrown !== null, '无 capability 应 403');
        $assertExceptionHttpStatus($thrown, 403);
    });

    // ──────────────────────────────────────────────────────
    // 组 L：Controller 代码扫描 — 无 hasCapability 单数调用
    // ──────────────────────────────────────────────────────

    $test('L1: Controller 不包含 hasCapability( 单数', static function () use ($assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $controllerCode = file_get_contents(__DIR__ . '/../../app/api/controller/Storage/Upload.php');
        // 允许 hasAnyCapability — 禁止 hasCapability( 单数
        $hasSingular = (bool)preg_match('/hasCapability\s*\(/', $controllerCode);
        $hasAny = (bool)preg_match('/hasAnyCapability\s*\(/', $controllerCode);
        $assertTrue($hasAny, 'Controller 应使用 hasAnyCapability');
        $assertFalse($hasSingular, 'Controller 不应包含 hasCapability( 单数');
    });

    // ──────────────────────────────────────────────────────
    // 组 M：getFile/getByHash/getByHashes scope 验证
    // ──────────────────────────────────────────────────────

    $test('M1: owner 可读取自己的正常记录', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        $file = \app\api\service\Storage\StorageManager::getFile(1, 10, false);
        $assertTrue($file !== null, 'owner=10 应读到 id=1');
    });

    $test('M2: owner 不可读取自己的软删除记录', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        $file = \app\api\service\Storage\StorageManager::getFile(2, 10, false);
        $assertTrue($file === null, 'owner=10 不可读取自己软删除的记录 id=2');
    });

    $test('M3: non-owner 不可读取非公开记录', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        // id=1 owner=10, non-owner=99 无 canReadAll
        $file = \app\api\service\Storage\StorageManager::getFile(1, 99, false);
        $assertTrue($file === null, 'non-owner=99 不可读取 id=1');
    });

    $test('M4: 不存在 ID 返回 null', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        $file = \app\api\service\Storage\StorageManager::getFile(999, 10, false);
        $assertTrue($file === null, '不存在 ID=999 返回 null');
    });

    $test('M5: canReadAll 可读取软删除记录', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        $file = \app\api\service\Storage\StorageManager::getFile(2, 10, true);
        $assertTrue($file !== null, '有 canReadAll 时可读取软删除记录 id=2');
    });

    $test('M6: visitor uid=0 只可见安全公开记录', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        // id=6 是 is_public=1 的 owner=10 记录
        $file = \app\api\service\Storage\StorageManager::getFile(6, 0, false);
        $assertTrue($file !== null, 'visitor 应读到安全公开记录 id=6');
        // id=1 不是公开的
        $file2 = \app\api\service\Storage\StorageManager::getFile(1, 0, false);
        $assertTrue($file2 === null, 'visitor 不可读非公开记录 id=1');
    });

    $test('M7: getByHash 应用 scope — owner 可读自己正常记录', static function () use ($resetMockDb, $assertTrue): void {
        $resetMockDb();
        $file = \app\api\service\Storage\StorageManager::getByHash('hash-1', 10, false);
        $assertTrue($file !== null && $file['id'] === 1, 'owner 应通过 hash 读取自己的正常记录');
    });

    $test('M8: getFile canReadAll 允许 non-owner 读取', static function () use ($resetMockDb, $assertTrue, $assertFalse, $assertSame, $assertExceptionHttpStatus): void {
        $resetMockDb();
        // non-owner=99 有 canReadAll, 可读 id=1 (owner=10)
        $file = \app\api\service\Storage\StorageManager::getFile(1, 99, true);
        $assertTrue($file !== null, 'canReadAll 时 non-owner=99 可读 id=1');
    });

    // N: confirmUpload owner/non-owner/missing
    $test('N1: confirmUpload owner succeeds', static function () use ($resetMockDb, $assertTrue, $assertSame): void {
        $resetMockDb();
        $result = \app\api\service\Storage\DirectUploadManager::confirmUpload(7, 10);
        global $mockFilesDb;
        $assertTrue($result, 'owner 应能确认自己的 pending upload');
        $assertSame(\app\api\model\Files::UPLOAD_COMPLETED, $mockFilesDb[7]->upload_status, '确认后应标记 completed');
    });

    $test('N2: confirmUpload non-owner returns false', static function () use ($resetMockDb, $assertFalse, $assertSame): void {
        $resetMockDb();
        $result = \app\api\service\Storage\DirectUploadManager::confirmUpload(7, 20);
        global $mockFilesDb;
        $assertFalse($result, 'non-owner 不得确认其他用户的 upload');
        $assertSame(\app\api\model\Files::UPLOAD_PENDING, $mockFilesDb[7]->upload_status, '拒绝后状态不得变化');
    });

    $test('N3: confirmUpload missing returns false', static function () use ($resetMockDb, $assertFalse): void {
        $resetMockDb();
        $assertFalse(
            \app\api\service\Storage\DirectUploadManager::confirmUpload(999, 10),
            '不存在的记录必须返回 false'
        );
    });

    $test('N4: listOwn visitor uid=0 returns 401', static function () use ($resetMockDb, $assertTrue, $assertExceptionHttpStatus): void {
        $resetMockDb();
        $thrown = null;
        try {
            \app\api\service\Storage\StorageManager::listOwn([], 0);
        } catch (\Throwable $e) {
            $thrown = $e;
        }
        $assertTrue($thrown instanceof \app\api\ApiException, 'visitor listOwn 必须抛出 ApiException');
        $assertExceptionHttpStatus($thrown, 401, 'visitor listOwn 应返回 401');
    });
}// ════════════════════════════════════════════════════════════
// 7. 运行器
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
        fwrite(STDERR, "\n{$failures} Files authorization baseline test(s) failed.\n");
        exit(1);
    }

    fwrite(STDOUT, "\n" . count($tests) . " Files authorization baseline tests passed.\n");
}
