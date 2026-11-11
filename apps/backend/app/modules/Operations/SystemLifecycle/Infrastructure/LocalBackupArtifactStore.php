<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Infrastructure;

use app\modules\Operations\SystemLifecycle\Contract\BackupArtifactStore;
use app\modules\Operations\SystemLifecycle\Domain\BackupArtifact;
use app\modules\Platform\Storage\Public\Gateway as StorageGateway;
use app\shared\SystemPaths\LocalStoragePath;
use app\shared\SystemPaths\SystemPaths;
use think\facade\Db;

final class LocalBackupArtifactStore implements BackupArtifactStore
{
    private \Closure $defaultChannelResolver;
    private string $applicationRoot;

    public function __construct(
        private SystemPaths $paths,
        $defaultChannelResolver = null,
        ?string $applicationRoot = null
    ) {
        // Keep the optional resolver untyped at the container boundary. Some
        // ThinkPHP container versions treat a nullable Closure parameter as a
        // dependency and attempt `new Closure`, which is illegal. Convert a
        // supplied callable explicitly and otherwise use the production
        // gateway resolver.
        if ($defaultChannelResolver !== null && !is_callable($defaultChannelResolver)) {
            throw new \InvalidArgumentException('默认存储渠道解析器必须是 callable');
        }
        $this->defaultChannelResolver = $defaultChannelResolver === null
            ? static fn(): array => StorageGateway::defaultChannel()
            : \Closure::fromCallable($defaultChannelResolver);
        $this->applicationRoot = rtrim($applicationRoot ?? app()->getRootPath(), '/\\');
    }

    public function capabilities(): array
    {
        $fileReason = null;
        try {
            $context = $this->localStorageContext();
            if (is_link($context['root'])) {
                throw new \RuntimeException('本地存储根目录是 symlink');
            }
        } catch (\Throwable $exception) {
            $fileReason = $exception->getMessage();
        }
        return [
            'database' => [
                'backup' => true,
                'restore' => false,
                'mode' => 'php-logical/manual-restore',
                'reason' => '数据库 Artifact 仅用于经人工维护窗口审核后的恢复，应用进程不会自动导入 SQL',
            ],
            'files' => [
                'backup' => $fileReason === null,
                'restore' => $fileReason === null,
                'mode' => 'local-zip/atomic-directory-swap',
                'reason' => $fileReason,
            ],
            'pointer' => [
                'backup' => true,
                'restore' => true,
                'mode' => 'atomic-release-pointer',
                'reason' => null,
            ],
        ];
    }

    public function database(string $backupId): BackupArtifact
    {
        $path = $this->path($backupId, 'sql');
        $this->ensureParent($path);
        $connection = Db::connect();
        // 新 CLI 进程可能尚未执行过查询；getPdo() 此时返回 false。
        $connection->query('SELECT 1');
        $pdo = $connection->getPdo();
        if (!$pdo instanceof \PDO) throw new \RuntimeException('无法建立数据库备份连接');
        $database = (string) ($connection->getConfig('database') ?: '');
        if ($database === '') throw new \RuntimeException('无法确定数据库名称');

        $handle = fopen($path . '.part', 'wb');
        if ($handle === false) throw new \RuntimeException('无法创建数据库备份文件');
        $committed = false;
        try {
            $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            $pdo->beginTransaction();
            $tables = $pdo->query('SHOW FULL TABLES')->fetchAll(\PDO::FETCH_NUM) ?: [];
            $this->write($handle, "-- LoveCards database backup\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
            foreach ($tables as $tableRow) {
                $table = (string) ($tableRow[0] ?? '');
                $kind = strtoupper((string) ($tableRow[1] ?? 'BASE TABLE'));
                if ($table === '' || $kind !== 'BASE TABLE' || !preg_match('/^[A-Za-z0-9_$-]+$/', $table)) continue;
                $quoted = '`' . str_replace('`', '``', $table) . '`';
                $create = $pdo->query('SHOW CREATE TABLE ' . $quoted)->fetch(\PDO::FETCH_NUM);
                $ddl = (string) ($create[1] ?? '');
                if ($ddl === '') throw new \RuntimeException('无法读取表结构: ' . $table);
                $this->write($handle, "DROP TABLE IF EXISTS {$quoted};\n{$ddl};\n");
            }
            foreach ($tables as $tableRow) {
                $table = (string) ($tableRow[0] ?? '');
                $kind = strtoupper((string) ($tableRow[1] ?? 'BASE TABLE'));
                if ($table === '' || $kind !== 'BASE TABLE' || !preg_match('/^[A-Za-z0-9_$-]+$/', $table)) continue;
                $quoted = '`' . str_replace('`', '``', $table) . '`';
                $statement = $pdo->query('SELECT * FROM ' . $quoted);
                if ($statement === false) throw new \RuntimeException('无法读取表数据: ' . $table);
                $columns = [];
                for ($index = 0; $index < $statement->columnCount(); $index++) {
                    $meta = $statement->getColumnMeta($index);
                    $columns[] = '`' . str_replace('`', '``', (string) ($meta['name'] ?? '')) . '`';
                }
                while (($row = $statement->fetch(\PDO::FETCH_NUM)) !== false) {
                    $values = array_map(fn($value): string => $value === null ? 'NULL' : $pdo->quote((string) $value), $row);
                    $this->write($handle, 'INSERT INTO ' . $quoted . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ");\n");
                }
                $statement->closeCursor();
            }
            $committed = $pdo->commit();
            $this->write($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        } finally {
            fclose($handle);
            if (!$committed) @unlink($path . '.part');
        }
        $this->replaceArtifact($path . '.part', $path);
        $this->restrict($path);
        return $this->artifact($backupId, 'database', 'php-logical', $path, ['database' => $database, 'tables' => count($tables)]);
    }

    public function files(string $backupId): BackupArtifact
    {
        $path = $this->path($backupId, 'zip');
        $this->ensureParent($path);
        $context = $this->localStorageContext();
        $root = $context['root'];
        if (is_link($root)) throw new \RuntimeException('文件存储根目录不能是 symlink');
        $zip = new \ZipArchive();
        if ($zip->open($path . '.part', \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) throw new \RuntimeException('无法创建文件备份');
        $manifest = [];
        try {
            if (is_dir($root)) {
                $base = realpath($root);
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \RecursiveDirectoryIterator::SKIP_DOTS));
                foreach ($iterator as $file) {
                    if ($file->isLink() || !$file->isFile()) throw new \RuntimeException('文件存储包含不支持的链接或特殊文件');
                    $real = realpath($file->getPathname());
                    if ($base === false || $real === false || strpos($real, $base . DIRECTORY_SEPARATOR) !== 0) throw new \RuntimeException('文件备份路径逃逸');
                    $relative = str_replace('\\', '/', substr($real, strlen($base) + 1));
                    if (!$this->safeRelative($relative)) throw new \RuntimeException('文件备份路径不安全');
                    $hash = hash_file('sha256', $real);
                    if ($hash === false || !$zip->addFile($real, $relative)) throw new \RuntimeException('无法加入文件备份');
                    $manifest[$relative] = ['sha256' => $hash, 'size' => (int) filesize($real)];
                }
            }
            ksort($manifest, SORT_STRING);
            $manifestDocument = [
                'format' => 'lovecards-files-backup/v1',
                'scope' => 'default-local-storage',
                'channel_slug' => $context['slug'],
                'entries' => $manifest,
            ];
            $zip->addFromString('backup-manifest.json', json_encode($manifestDocument, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            if (!$zip->close()) throw new \RuntimeException('无法完成文件备份');
        } catch (\Throwable $exception) {
            $zip->close();
            @unlink($path . '.part');
            throw $exception;
        }
        $this->replaceArtifact($path . '.part', $path);
        $this->restrict($path);
        return $this->artifact($backupId, 'files', 'local-zip-v1', $path, [
            'scope' => 'default-local-storage',
            'channel_slug' => $context['slug'],
            'entries' => count($manifest),
            'restore' => 'atomic-directory-swap',
        ]);
    }

    public function pointer(string $backupId): BackupArtifact
    {
        $path = $this->path($backupId, 'pointer');
        $this->ensureParent($path);
        $value = is_file($this->paths->activeReleasePointer()) ? (string) file_get_contents($this->paths->activeReleasePointer()) : "\n";
        if (file_put_contents($path . '.part', $value, LOCK_EX) === false) throw new \RuntimeException('无法创建 Release 指针备份');
        $this->replaceArtifact($path . '.part', $path);
        $this->restrict($path);
        return $this->artifact($backupId, 'pointer', 'filesystem', $path, ['active_release_pointer' => $this->paths->activeReleasePointer()]);
    }

    public function verify(BackupArtifact $artifact): void
    {
        $path = $artifact->location();
        if (!is_file($path) || is_link($path)) throw new \RuntimeException('备份 Artifact 不存在或是 symlink');
        $real = realpath($path);
        $root = realpath($this->paths->backupsRoot());
        if ($real === false || $root === false || !$this->within($real, $root)) {
            throw new \RuntimeException('备份 Artifact 路径越界');
        }
        $checksum = hash_file('sha256', $path);
        $size = filesize($path);
        if ($checksum === false || $size === false || !hash_equals($artifact->checksum(), $checksum) || (int) $size !== $artifact->sizeBytes()) {
            throw new \RuntimeException('备份 Artifact 校验失败');
        }
        if ($artifact->type() === 'database') {
            $handle = fopen($path, 'rb');
            if ($handle === false) throw new \RuntimeException('数据库备份不可读取');
            $tail = '';
            while (!feof($handle)) {
                $chunk = fread($handle, 8192);
                if ($chunk === false) { fclose($handle); throw new \RuntimeException('数据库备份读取失败'); }
                $tail = substr($tail . $chunk, -128);
            }
            fclose($handle);
            if (strpos($tail, 'SET FOREIGN_KEY_CHECKS=1;') === false) throw new \RuntimeException('数据库备份缺少完整结束标记');
        } elseif ($artifact->type() === 'files') {
            $zip = new \ZipArchive();
            if ($zip->open($path, \ZipArchive::RDONLY) !== true) throw new \RuntimeException('文件备份 ZIP 无法打开');
            try {
                $manifestJson = $zip->getFromName('backup-manifest.json', 0, \ZipArchive::FL_UNCHANGED);
                $document = is_string($manifestJson) ? json_decode($manifestJson, true) : null;
                if (
                    !is_array($document)
                    || ($document['format'] ?? null) !== 'lovecards-files-backup/v1'
                    || ($document['scope'] ?? null) !== 'default-local-storage'
                    || !is_string($document['channel_slug'] ?? null)
                    || !is_array($document['entries'] ?? null)
                ) {
                    throw new \RuntimeException('文件备份缺少有效 Manifest');
                }
                $manifest = $document['entries'];
                $archiveNames = [];
                for ($index = 0; $index < $zip->numFiles; $index++) {
                    $name = $zip->getNameIndex($index, \ZipArchive::FL_UNCHANGED);
                    if (!is_string($name) || substr($name, -1) === '/' || !$this->safeRelative($name)) throw new \RuntimeException('文件备份 ZIP 路径不安全');
                    if ($this->zipEntryIsLink($zip, $index)) throw new \RuntimeException('文件备份 ZIP 不能包含链接');
                    $archiveNames[] = $name;
                }
                sort($archiveNames, SORT_STRING);
                $expectedNames = array_keys($manifest);
                sort($expectedNames, SORT_STRING);
                $expectedArchiveNames = array_merge($expectedNames, ['backup-manifest.json']);
                sort($expectedArchiveNames, SORT_STRING);
                if ($archiveNames !== $expectedArchiveNames) throw new \RuntimeException('文件备份 ZIP 与 Manifest 集合不一致');
                foreach ($manifest as $name => $entry) {
                    if (!is_string($name) || !is_array($entry) || !isset($entry['sha256'], $entry['size']) || preg_match('/^[a-f0-9]{64}$/', (string) $entry['sha256']) !== 1 || (int) $entry['size'] < 0) throw new \RuntimeException('文件备份 Manifest 不合法');
                    if (!$this->safeRelative($name) || $name === 'backup-manifest.json') throw new \RuntimeException('文件备份 Manifest 路径不安全');
                    $this->verifyZipEntry($zip, $name, (string) $entry['sha256'], (int) $entry['size']);
                }
            } finally {
                $zip->close();
            }
        } elseif ($artifact->type() === 'pointer') {
            $value = trim((string) file_get_contents($path));
            if ($value !== '' && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $value) !== 1) throw new \RuntimeException('Release 指针备份内容不合法');
        }
    }

    public function restoreFiles(BackupArtifact $artifact): void
    {
        if ($artifact->type() !== 'files') throw new \InvalidArgumentException('只能恢复 files Artifact');
        $this->verify($artifact);
        $context = $this->localStorageContext();
        $root = $context['root'];
        $parent = dirname($root);
        if (is_link($parent) || (!is_dir($parent) && !mkdir($parent, 0750, true) && !is_dir($parent))) {
            throw new \RuntimeException('文件恢复父目录不可用');
        }
        if (is_link($root) || (file_exists($root) && !is_dir($root))) {
            throw new \RuntimeException('文件恢复目标不是安全目录');
        }

        $token = bin2hex(random_bytes(8));
        $staging = $parent . DIRECTORY_SEPARATOR . '.' . basename($root) . '.restore-' . $token;
        $previous = $parent . DIRECTORY_SEPARATOR . '.' . basename($root) . '.previous-' . $token;
        if (!mkdir($staging, 0700) || is_link($staging)) throw new \RuntimeException('无法创建文件恢复 Staging');

        $zip = new \ZipArchive();
        $movedPrevious = false;
        try {
            if ($zip->open($artifact->location(), \ZipArchive::RDONLY) !== true) throw new \RuntimeException('文件备份 ZIP 无法打开');
            $document = $this->fileManifest($zip);
            if (!hash_equals((string) $document['channel_slug'], $context['slug'])) {
                throw new \RuntimeException('文件备份渠道与当前默认存储渠道不一致');
            }
            foreach ($document['entries'] as $name => $entry) {
                $destination = LocalStoragePath::prepareDestination($staging, (string) $name);
                $input = $zip->getStream((string) $name);
                $output = fopen($destination, 'xb');
                if (!is_resource($input) || $output === false) {
                    if (is_resource($input)) fclose($input);
                    if (is_resource($output)) fclose($output);
                    throw new \RuntimeException('无法创建文件恢复条目');
                }
                $hash = hash_init('sha256');
                $written = 0;
                try {
                    while (!feof($input)) {
                        $chunk = fread($input, 1048576);
                        if ($chunk === false) throw new \RuntimeException('读取文件恢复条目失败');
                        $length = strlen($chunk);
                        if ($length === 0) continue;
                        $written += $length;
                        if ($written > (int) $entry['size']) throw new \RuntimeException('文件恢复条目超过 Manifest 大小');
                        hash_update($hash, $chunk);
                        $offset = 0;
                        while ($offset < $length) {
                            $count = fwrite($output, substr($chunk, $offset));
                            if ($count === false || $count === 0) throw new \RuntimeException('写入文件恢复条目失败');
                            $offset += $count;
                        }
                    }
                    if (!fflush($output) || (function_exists('fsync') && !fsync($output))) {
                        throw new \RuntimeException('文件恢复条目无法同步');
                    }
                } finally {
                    fclose($input);
                    fclose($output);
                }
                if ($written !== (int) $entry['size'] || !hash_equals((string) $entry['sha256'], hash_final($hash))) {
                    throw new \RuntimeException('文件恢复条目校验失败');
                }
                @chmod($destination, 0640);
            }
            $zip->close();

            if (is_dir($root)) {
                if (!rename($root, $previous)) throw new \RuntimeException('无法暂存现有文件存储目录');
                $movedPrevious = true;
            }
            if (!rename($staging, $root)) {
                if ($movedPrevious) rename($previous, $root);
                $movedPrevious = false;
                throw new \RuntimeException('无法提交文件恢复目录');
            }
            @chmod($root, 0750);
            if ($movedPrevious) {
                $this->removeTree($previous);
                $movedPrevious = false;
            }
        } catch (\Throwable $exception) {
            if ($zip->status === \ZipArchive::ER_OK) $zip->close();
            if (is_dir($staging) && !is_link($staging)) $this->removeTree($staging);
            if ($movedPrevious && !file_exists($root)) @rename($previous, $root);
            throw $exception;
        }
    }

    private function artifact(string $backupId, string $type, string $driver, string $path, array $metadata): BackupArtifact
    {
        $checksum = hash_file('sha256', $path);
        $size = filesize($path);
        if ($checksum === false || $size === false) throw new \RuntimeException('无法校验备份文件');
        return new BackupArtifact($backupId, $type, $driver, $path, $checksum, (int) $size, $metadata);
    }

    private function path(string $backupId, string $extension): string
    {
        $slug = preg_replace('/[^A-Za-z0-9._-]+/', '_', $backupId);
        return $this->paths->backupsRoot() . DIRECTORY_SEPARATOR . $slug . '.' . $extension;
    }

    private function ensureParent(string $path): void
    {
        $root = $this->paths->backupsRoot();
        if (is_link($root)) throw new \RuntimeException('备份根目录不能是 symlink');
        if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) throw new \RuntimeException('无法创建备份根目录');
        $parent = dirname($path);
        if (!is_dir($parent) && !mkdir($parent, 0700, true) && !is_dir($parent)) throw new \RuntimeException('无法创建备份目录');
        if (is_link($parent)) throw new \RuntimeException('备份目录不能是 symlink');
    }
    private function replaceArtifact(string $temporary, string $destination): void
    {
        if (is_link($destination)) throw new \RuntimeException('备份目标不能是 symlink');
        if (file_exists($destination) && !unlink($destination)) throw new \RuntimeException('无法替换现有备份 Artifact');
        if (!rename($temporary, $destination)) throw new \RuntimeException('无法提交备份 Artifact');
    }
    private function restrict(string $path): void { @chmod($path, 0600); }
    private function write($handle, string $value): void { if (fwrite($handle, $value) === false) throw new \RuntimeException('备份写入失败'); }
    private function safeRelative(string $path): bool
    {
        return strpos($path, '\\') === false && LocalStoragePath::safeRelative($path);
    }

    private function within(string $candidate, string $root): bool
    {
        $candidate = str_replace('\\', '/', rtrim($candidate, '/\\'));
        $root = str_replace('\\', '/', rtrim($root, '/\\'));
        if (DIRECTORY_SEPARATOR === '\\') { $candidate = strtolower($candidate); $root = strtolower($root); }
        return $candidate === $root || strpos($candidate, $root . '/') === 0;
    }

    /** @return array{format:string,scope:string,channel_slug:string,entries:array<string,array{sha256:string,size:int}>} */
    private function fileManifest(\ZipArchive $zip): array
    {
        $json = $zip->getFromName('backup-manifest.json', 0, \ZipArchive::FL_UNCHANGED);
        $document = is_string($json) ? json_decode($json, true) : null;
        if (
            !is_array($document)
            || ($document['format'] ?? null) !== 'lovecards-files-backup/v1'
            || ($document['scope'] ?? null) !== 'default-local-storage'
            || !is_string($document['channel_slug'] ?? null)
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', $document['channel_slug']) !== 1
            || !is_array($document['entries'] ?? null)
        ) {
            throw new \RuntimeException('文件备份缺少有效 Manifest');
        }
        return $document;
    }

    private function verifyZipEntry(\ZipArchive $zip, string $name, string $checksum, int $expectedSize): void
    {
        $stream = $zip->getStream($name);
        if (!is_resource($stream)) throw new \RuntimeException('文件备份内容不可读取');
        $hash = hash_init('sha256');
        $size = 0;
        try {
            while (!feof($stream)) {
                $chunk = fread($stream, 1048576);
                if ($chunk === false) throw new \RuntimeException('文件备份内容读取失败');
                $size += strlen($chunk);
                if ($size > $expectedSize) throw new \RuntimeException('文件备份内容超过 Manifest 大小');
                hash_update($hash, $chunk);
            }
        } finally {
            fclose($stream);
        }
        if ($size !== $expectedSize || !hash_equals($checksum, hash_final($hash))) {
            throw new \RuntimeException('文件备份内容校验失败');
        }
    }

    private function zipEntryIsLink(\ZipArchive $zip, int $index): bool
    {
        $operationsSystem = 0;
        $attributes = 0;
        if (!$zip->getExternalAttributesIndex($index, $operationsSystem, $attributes)) return false;
        return (($attributes >> 16) & 0170000) === 0120000;
    }

    /** @return array{slug:string,root:string} */
    private function localStorageContext(): array
    {
        $channel = ($this->defaultChannelResolver)();
        if (!is_array($channel)) throw new \RuntimeException('无法读取默认存储渠道');
        $slug = trim((string) ($channel['slug'] ?? ''));
        $type = strtolower(trim((string) ($channel['type'] ?? $slug)));
        if ($slug === '' || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/', $slug) !== 1) {
            throw new \RuntimeException('默认存储渠道标识不合法');
        }
        if ($type !== 'local') {
            throw new \RuntimeException('默认存储渠道不是本地存储；必须配置该云存储的原生快照能力后才能升级');
        }
        return [
            'slug' => $slug,
            'root' => LocalStoragePath::root($this->paths, $this->applicationRoot, $channel),
        ];
    }

    private function removeTree(string $directory): void
    {
        if (is_link($directory)) {
            if (!unlink($directory)) throw new \RuntimeException('无法清理恢复链接');
            return;
        }
        if (!is_dir($directory)) return;
        foreach (scandir($directory) ?: [] as $name) {
            if ($name === '.' || $name === '..') continue;
            $path = $directory . DIRECTORY_SEPARATOR . $name;
            if (is_dir($path) && !is_link($path)) $this->removeTree($path);
            elseif (!unlink($path)) throw new \RuntimeException('无法清理恢复文件');
        }
        if (!rmdir($directory)) throw new \RuntimeException('无法清理恢复目录');
    }
}
