<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Public;

use app\modules\Operations\SystemLifecycle\Contract\Migration;
use app\modules\Operations\SystemLifecycle\Contract\MigrationLedger;
use app\modules\Operations\SystemLifecycle\Contract\MigrationSource;
use app\modules\Operations\SystemLifecycle\Domain\MigrationProgress;

/** 以当前代码迁移目录和已完成账本共同确定运行时 Schema 身份。 */
final class SchemaIdentity
{
    /** Worker 进程启动后，代码目录作为不可变快照；FPM 请求不会调用 prime。 */
    private static array $workerCatalog = [];

    public function __construct(private MigrationSource $source, private MigrationLedger $ledger) {}

    public function current(): string
    {
        return $this->primeForDirectory(dirname(__DIR__, 5) . '/database/migrations');
    }

    public function primeForDirectory(string $directory): string
    {
        return $this->identityForDirectory($directory, false, true);
    }

    /** FPM 探测验证本进程代码目录与账本，但不触发账本建表。 */
    public function probeIdentity(): string
    {
        return $this->forDirectoryReadOnly(dirname(__DIR__, 5) . '/database/migrations');
    }

    public function forDirectory(string $directory): string
    {
        return $this->identityForDirectory($directory, false);
    }

    public function forDirectoryReadOnly(string $directory): string
    {
        return $this->identityForDirectory($directory, true);
    }

    private function identityForDirectory(string $directory, bool $readOnly, bool $prime = false): string
    {
        $key = realpath($directory) ?: $directory;
        if ($readOnly && isset(self::$workerCatalog[$key])) {
            $declared = self::$workerCatalog[$key];
        } else {
            $declared = [];
            foreach ($this->source->discover($directory) as $migration) {
                if (!$migration instanceof Migration) throw new \RuntimeException('Migration Catalog 返回无效迁移');
                $descriptor = $migration->descriptor();
                if (isset($declared[$descriptor->id()])) throw new \RuntimeException('Migration Catalog 存在重复 ID');
                $declared[$descriptor->id()] = $descriptor->checksum();
            }
        }
        if ($declared === []) throw new \RuntimeException('Migration Catalog 为空');

        $applied = $this->records($readOnly ? $this->ledger->allReadOnly() : $this->ledger->all());
        ksort($declared, SORT_STRING);
        if ($declared !== $applied) throw new \RuntimeException('Migration Catalog 与账本不一致');

        if ($prime) self::$workerCatalog[$key] = $declared;

        return $this->fingerprint($applied);
    }

    private function records(array $rows): array
    {
        $applied = [];
        foreach ($rows as $progress) {
            if (!$progress instanceof MigrationProgress || !$progress->isComplete()) {
                throw new \RuntimeException('Migration Ledger 存在未完成或无效记录');
            }
            if (isset($applied[$progress->id()])) throw new \RuntimeException('Migration Ledger 存在重复 ID');
            $applied[$progress->id()] = $progress->checksum();
        }
        ksort($applied, SORT_STRING);
        if ($applied === []) throw new \RuntimeException('Migration Ledger 为空');
        return $applied;
    }

    private function fingerprint(array $applied): string
    {
        return hash('sha256', json_encode($applied, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
