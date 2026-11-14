<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Public;

use app\modules\Operations\SystemLifecycle\Contract\Migration;
use app\modules\Operations\SystemLifecycle\Contract\MigrationSource;
use think\facade\Db;

/** 只证明安装器已成功导入当前包的完整 schema.sql，不推断或执行结构差异。 */
final class CleanInstallReceipt
{
    private const MARKER = '__clean_install_schema__';

    public function __construct(private MigrationSource $migrations) {}

    public function record(string $schemaPath, string $migrationDirectory): void
    {
        $checksum = $this->fingerprint($schemaPath, $migrationDirectory);
        $existing = Db::table('schema_migrations')->where('migration_id', self::MARKER)->find();
        if ($existing !== null) {
            if (hash_equals($checksum, (string) ($existing['checksum'] ?? ''))) return;
            throw new \RuntimeException('首次安装 Schema 凭据与当前包不一致');
        }
        if ((int) Db::table('system_upgrade_steps')->count() !== 0) {
            throw new \RuntimeException('已有迁移账本，不能补记首次安装 Schema 凭据');
        }
        Db::table('schema_migrations')->insert([
            'migration_id' => self::MARKER,
            'checksum' => $checksum,
            'applied_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function assertCurrent(string $schemaPath, string $migrationDirectory): void
    {
        $receipt = Db::table('schema_migrations')->where('migration_id', self::MARKER)->find();
        if (!is_array($receipt) || !hash_equals($this->fingerprint($schemaPath, $migrationDirectory), (string) ($receipt['checksum'] ?? ''))) {
            throw new \RuntimeException('当前包缺少匹配的首次安装 Schema 导入凭据');
        }
    }

    public function fingerprint(string $schemaPath, string $migrationDirectory): string
    {
        $schemaChecksum = is_file($schemaPath) ? hash_file('sha256', $schemaPath) : false;
        if ($schemaChecksum === false) throw new \RuntimeException('安装 Schema 文件不可读');
        $catalog = [];
        foreach ($this->migrations->discover($migrationDirectory) as $migration) {
            if (!$migration instanceof Migration) throw new \RuntimeException('安装迁移目录包含无效对象');
            $descriptor = $migration->descriptor();
            if (isset($catalog[$descriptor->id()])) throw new \RuntimeException('安装迁移目录包含重复 ID');
            $catalog[$descriptor->id()] = $descriptor->checksum();
        }
        if ($catalog === []) throw new \RuntimeException('安装迁移目录为空');
        ksort($catalog, SORT_STRING);
        return hash('sha256', $schemaChecksum . "\n" . json_encode($catalog, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
