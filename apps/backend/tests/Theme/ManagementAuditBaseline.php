<?php
declare(strict_types=1);

// 本测试只使用随机临时目录和内存 DB，不加载应用配置或连接 dev 数据库。
namespace think\facade {
    final class Db
    {
        public static array $rows = [];
        public static function table(string $name): MemoryQuery
        {
            if ($name !== 'theme_bindings') throw new \RuntimeException('unexpected table');
            return new MemoryQuery();
        }
    }
    final class MemoryQuery
    {
        private array $conditions = [];
        public function where(string $key, mixed $value): self { $this->conditions[$key] = $value; return $this; }
        private function matches(array $row): bool
        {
            foreach ($this->conditions as $key => $value) if (($row[$key] ?? null) !== $value) return false;
            return true;
        }
        public function find(): ?array { foreach (Db::$rows as $row) if ($this->matches($row)) return $row; return null; }
        public function update(array $values): int
        {
            $count = 0;
            foreach (Db::$rows as &$row) if ($this->matches($row)) { $row = array_merge($row, $values); $count++; }
            return $count;
        }
        public function insertGetId(array $values): int { $id = count(Db::$rows) + 1; Db::$rows[] = $values + ['id' => $id]; return $id; }
    }
}

namespace {
    $testRoot = sys_get_temp_dir() . '/lc-theme-management-' . bin2hex(random_bytes(8));
    mkdir($testRoot, 0700, true);
    mkdir($testRoot . '/release', 0700);
    $oldInstanceRoot = getenv('LC_INSTANCE_ROOT');
    putenv('LC_INSTANCE_ROOT=' . $testRoot . '/instance');
    function root_path(): string { return $GLOBALS['testRoot'] . '/release'; }
    function request(): object { return new class { public function host(): string { return 'current.example.test'; } }; }
    $backend = dirname(__DIR__, 2);
    foreach ([
        'shared/SystemPaths/SystemPaths.php', 'shared/SystemPaths/SystemPathsResolver.php', 'shared/SystemPaths/LocalStoragePath.php',
        'shared/Observability/AuditLogger.php', 'shared/Transaction/TransactionManager.php',
        'modules/Theme/Domain/Hostname.php', 'modules/Theme/Domain/ThemeManifest.php',
        'modules/Theme/Contract/ThemeMutationLock.php',
        'modules/Theme/Infrastructure/ThemeBindingRepository.php', 'modules/Theme/Infrastructure/FilesystemThemeCatalog.php',
        'modules/Theme/Infrastructure/SecureThemeInstaller.php', 'modules/Theme/Infrastructure/ThemeMutationLock.php', 'modules/Theme/Service/ThemeManager.php',
        'modules/Theme/Application/ThemeOperationException.php', 'modules/Theme/Application/ManageTheme.php',
    ] as $source) require_once $backend . '/app/' . $source;

    final class RecordingAudit implements \app\shared\Observability\AuditLogger
    {
        public array $records = [];
        public ?string $failSuffix = null;
        public function record(?int $actorId, string $action, string $type, ?string $id, array $metadata = []): void
        {
            if ($this->failSuffix !== null && str_ends_with($action, $this->failSuffix)) throw new \RuntimeException('audit unavailable');
            $this->records[] = compact('actorId', 'action', 'type', 'id', 'metadata');
        }
    }
    final class MemoryTransactions implements \app\shared\Transaction\TransactionManager
    {
        public bool $commitWasLocked = false;
        public function __construct(private RecordingAudit $audit) {}
        public function run(callable $callback): mixed
        {
            $rows = \think\facade\Db::$rows; $records = $this->audit->records;
            try {
                $result = $callback();
                $handle = fopen(\app\modules\Theme\Infrastructure\FilesystemThemeCatalog::root() . '/.management.lock', 'c');
                $acquired = flock($handle, LOCK_EX | LOCK_NB);
                $this->commitWasLocked = !$acquired;
                if ($acquired) flock($handle, LOCK_UN);
                fclose($handle);
                return $result;
            }
            catch (\Throwable $error) { \think\facade\Db::$rows = $rows; $this->audit->records = $records; throw $error; }
        }
    }
    $assert = static function (bool $value, string $message): void { if (!$value) throw new \RuntimeException($message); };
    $expectOperationError = static function (callable $action, string $sideEffects) use ($assert): \app\modules\Theme\Application\ThemeOperationException {
        try { $action(); } catch (\app\modules\Theme\Application\ThemeOperationException $error) {
            $assert($error->sideEffects === $sideEffects && strlen($error->operationId) === 32, 'missing truthful outcome details');
            return $error;
        }
        throw new \RuntimeException('operation unexpectedly succeeded');
    };
    $makeZip = static function () use ($testRoot): string {
        $path = $testRoot . '/theme.zip';
        $zip = new \ZipArchive(); $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('theme.json', json_encode([
            'schemaVersion' => 1, 'id' => 'audit-theme', 'name' => 'Audit test', 'version' => '1.0.0', 'mode' => 'spa',
            'entry' => 'dist/index.html', 'config' => ['caption' => ['type' => 'string', 'default' => 'original']],
        ], JSON_THROW_ON_ERROR));
        $zip->addFromString('dist/index.html', '<!doctype html><html><body>test</body></html>'); $zip->close();
        return $path;
    };
    $zip = $makeZip();
    $catalog = \app\modules\Theme\Infrastructure\FilesystemThemeCatalog::class;
    $audit = new RecordingAudit();
    $transactions = new MemoryTransactions($audit);
    $manager = new \app\modules\Theme\Application\ManageTheme($audit, $transactions, new \app\modules\Theme\Infrastructure\ThemeMutationLock());
    $installed = null;
    $tests = [
        'requested audit failure never mutates theme files' => static function () use ($audit, $manager, $zip, $catalog, $expectOperationError, $assert): void {
            $audit->failSuffix = '.requested';
            $error = $expectOperationError(static fn () => $manager->install($zip, 7), 'none');
            $assert(!is_dir($catalog::root()), 'theme root created before requested audit');
            $assert($error->reasonCode === 'theme_audit_unavailable', 'wrong request failure');
            $audit->failSuffix = null;
        },
        'completed audit failure preserves installed release and operation id' => static function () use ($audit, $manager, $zip, $catalog, $expectOperationError, $assert, &$installed): void {
            $audit->failSuffix = '.completed';
            $error = $expectOperationError(static fn () => $manager->install($zip, 7), 'applied');
            $list = $catalog::list(); $installed = $list[0];
            $assert(count($list) === 1 && $error->reasonCode === 'theme_result_unrecorded', 'installed release was hidden or called rolled back');
            $assert(end($audit->records)['metadata']['operation_id'] === $error->operationId, 'requested audit cannot be correlated');
            $audit->failSuffix = null;
        },
        'repeated installation is idempotent with correlated audit' => static function () use ($audit, $manager, $zip, $catalog, $assert, &$installed): void {
            $result = $manager->install($zip, 7);
            $assert($result['release'] === $installed['release'] && count($catalog::list()) === 1, 'duplicate immutable release');
            $records = array_slice($audit->records, -2);
            $assert($records[0]['metadata']['operation_id'] === $records[1]['metadata']['operation_id'], 'requested and completed ids differ');
            $assert($records[1]['id'] === $result['id'] . ':' . $result['release'], 'completion missing release target');
        },
        'enabled binding on another host prevents deletion' => static function () use ($manager, $catalog, $expectOperationError, $assert, &$installed): void {
            \think\facade\Db::$rows = [['id' => 1, 'hostname' => 'other.example.test', 'theme_id' => $installed['id'], 'release_key' => $installed['release'], 'enabled' => 1, 'config_overrides' => '{}']];
            $expectOperationError(static fn () => $manager->delete($installed['id'], $installed['release'], 7), 'possible');
            $assert($catalog::resolve($installed['id'], $installed['release']) !== null, 'another host lost its active release');
        },
        'disabled binding permits deletion and repeated deletion never reports success' => static function () use ($manager, $audit, $catalog, $expectOperationError, $assert, &$installed): void {
            \think\facade\Db::$rows[0]['enabled'] = 0;
            $manager->delete($installed['id'], $installed['release'], 7);
            $assert($catalog::resolve($installed['id'], $installed['release']) === null, 'release remained after deletion');
            $error = $expectOperationError(static fn () => $manager->delete($installed['id'], $installed['release'], 7), 'possible');
            $assert(end($audit->records)['action'] === 'theme.delete.failed' && !str_contains($error->getMessage(), '回滚'), 'repeat delete claimed success or rollback');
        },
        'delete request audit failure leaves release intact' => static function () use ($manager, $audit, $catalog, $zip, $expectOperationError, $assert, &$installed): void {
            $installed = $manager->install($zip, 7); $audit->failSuffix = '.requested';
            $expectOperationError(static fn () => $manager->delete($installed['id'], $installed['release'], 7), 'none');
            $assert($catalog::resolve($installed['id'], $installed['release']) !== null, 'delete ran without request audit');
            $audit->failSuffix = null;
        },
        'delete completion audit failure reports applied result' => static function () use ($manager, $audit, $catalog, $expectOperationError, $assert, &$installed): void {
            $audit->failSuffix = '.completed';
            $expectOperationError(static fn () => $manager->delete($installed['id'], $installed['release'], 7), 'applied');
            $assert($catalog::resolve($installed['id'], $installed['release']) === null, 'delete outcome not observable');
            $audit->failSuffix = null;
        },
        'binding and config mutations roll back with audit failure' => static function () use ($manager, $audit, $transactions, $zip, $expectOperationError, $assert, &$installed): void {
            \think\facade\Db::$rows = []; $installed = $manager->install($zip, 7);
            $audit->failSuffix = '.completed';
            $expectOperationError(static fn () => $manager->activate($installed['id'], $installed['release'], 7, 'current.example.test'), 'none');
            $assert(\think\facade\Db::$rows === [], 'binding committed without audit');
            $audit->failSuffix = null;
            $manager->activate($installed['id'], $installed['release'], 7, 'current.example.test');
            $assert($transactions->commitWasLocked, 'theme lock released before binding transaction committed');
            $audit->failSuffix = '.completed';
            $before = \think\facade\Db::$rows;
            $expectOperationError(static fn () => $manager->updateConfig($installed['id'], $installed['release'], ['caption' => 'secret-value'], 7, 'current.example.test'), 'none');
            $assert(\think\facade\Db::$rows === $before, 'config committed without audit');
            $audit->failSuffix = null;
            $manager->updateConfig($installed['id'], $installed['release'], ['caption' => 'secret-value'], 7, 'current.example.test');
            $record = end($audit->records);
            $assert($record['metadata']['changed_keys'] === ['caption'] && !str_contains(json_encode($record), 'secret-value'), 'audit exposed config values');
        },
        'failed result audit preserves request id without claiming rollback' => static function () use ($manager, $audit, $expectOperationError, $assert): void {
            $audit->failSuffix = '.failed';
            $error = $expectOperationError(static fn () => $manager->delete('missing-theme', '1.0.0-aaaaaaaaaaaaaaaa', 7), 'possible');
            $assert($error->reasonCode === 'theme_result_unrecorded' && end($audit->records)['metadata']['operation_id'] === $error->operationId, 'failed outcome lost correlation');
            $audit->failSuffix = null;
        },
    ];
    $failures = 0;
    try {
        foreach ($tests as $name => $test) {
            try { $test(); fwrite(STDOUT, "PASS {$name}\n"); }
            catch (\Throwable $error) { $failures++; fwrite(STDERR, "FAIL {$name}: {$error->getMessage()}\n"); }
        }
    } finally {
        putenv($oldInstanceRoot === false ? 'LC_INSTANCE_ROOT' : 'LC_INSTANCE_ROOT=' . $oldInstanceRoot);
        // 目标来自本文件创建的随机临时根，不接触应用主题目录。
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($testRoot, \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) $file->isDir() && !is_link($file->getPathname()) ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($testRoot);
    }
    if ($failures > 0) exit(1);
    fwrite(STDOUT, count($tests) . " Theme management audit tests passed.\n");
}
