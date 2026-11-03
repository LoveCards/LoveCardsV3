<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Infrastructure;

use app\modules\Operations\Installer\Infrastructure\Database;
use app\modules\Operations\SystemLifecycle\Contract\Migration;
use app\modules\Operations\SystemLifecycle\Domain\BackfillBatch;
use app\modules\Operations\SystemLifecycle\Domain\MigrationDescriptor;

/** Adapts the existing SQL migration files to the resumable lifecycle runner. */
final class SqlFileMigration implements Migration
{
    private MigrationDescriptor $descriptor;
    private string $path;
    /** @var array<string, string> */
    private array $phases;

    public function __construct(string $id, string $path, string $description)
    {
        $source = file_get_contents($path);
        if ($source === false) throw new \RuntimeException('无法读取迁移文件: ' . $path);
        $this->phases = $this->splitPhases($source);
        $this->descriptor = MigrationDescriptor::fromSource(
            $id,
            $description,
            $source,
            trim($this->phases['contract']) !== ''
        );
        $this->path = $path;
    }

    public function descriptor(): MigrationDescriptor { return $this->descriptor; }
    public function preflight(): void
    {
        if (!is_readable($this->path)) throw new \RuntimeException('迁移文件不可读');
        $this->execute('preflight');
    }
    public function expand(): void { $this->execute('expand'); }
    public function backfill(?string $cursor): BackfillBatch
    {
        if ($cursor === null) $this->execute('backfill');
        return BackfillBatch::complete();
    }
    public function verify(): void { $this->execute('verify'); }
    public function contract(): void { $this->execute('contract'); }

    private function execute(string $phase): void
    {
        $sql = trim($this->phases[$phase] ?? '');
        if ($sql !== '') Database::ImportSQL($sql);
    }

    /**
     * 无 marker 的历史迁移整体作为 expand 执行。新迁移可用
     * `-- @lc-phase preflight|expand|backfill|verify|contract` 显式分段。
     *
     * @return array<string, string>
     */
    private function splitPhases(string $source): array
    {
        $empty = ['preflight'=>'', 'expand'=>'', 'backfill'=>'', 'verify'=>'', 'contract'=>''];
        if (!preg_match('/^\s*--\s*@lc-phase\s+(preflight|expand|migrate|backfill|verify|contract)\s*$/mi', $source)) {
            $empty['expand'] = $source;
            return $empty;
        }

        $current = null;
        foreach (preg_split('/\R/u', $source) ?: [] as $line) {
            if (preg_match('/^\s*--\s*@lc-phase\s+(preflight|expand|migrate|backfill|verify|contract)\s*$/i', $line, $match)) {
                $current = strtolower($match[1]);
                // 已发布 SQL 使用 migrate 标记；运行器统一推进到 backfill，不改写历史文件校验和。
                if ($current === 'migrate') $current = 'backfill';
                continue;
            }
            if ($current === null) {
                if (trim($line) !== '' && !preg_match('/^\s*(?:--|#)/', $line)) {
                    throw new \InvalidArgumentException('分段 SQL 在首个 @lc-phase 前包含可执行语句');
                }
                continue;
            }
            $empty[$current] .= $line . "\n";
        }
        return $empty;
    }
}
