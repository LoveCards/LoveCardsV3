<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Infrastructure;

use app\modules\Operations\SystemLifecycle\Application\ReleaseManifestVerifier;
use app\modules\Operations\SystemLifecycle\Contract\ReleasePackageImporter;
use app\modules\Operations\SystemLifecycle\Domain\ReleaseManifest;
use app\modules\Operations\SystemLifecycle\Domain\VerifiedReleaseManifest;
use app\modules\Operations\SystemLifecycle\Exception\ReleaseManifestException;
use app\shared\SystemPaths\SystemPaths;

/** 验签并发布不可变系统 Release；本类不负责激活 Release。 */
final class SecureReleaseImporter implements ReleasePackageImporter
{
    /** @deprecated Use lifecycle configuration through ReleaseArchiveLimits. */
    public const MAX_ZIP_BYTES = 268435456;
    private const MAX_PATH_BYTES = 1024;
    private const MAX_PATH_DEPTH = 64;
    private const COPY_CHUNK_BYTES = 1048576;

    private SystemPaths $paths;
    private ReleaseManifestVerifier $manifestVerifier;
    private ReleaseArchiveLimits $limits;

    public function __construct(
        SystemPaths $paths,
        ReleaseManifestVerifier $manifestVerifier,
        ?ReleaseArchiveLimits $limits = null
    )
    {
        $this->paths = $paths;
        $this->manifestVerifier = $manifestVerifier;
        $this->limits = $limits ?? ReleaseArchiveLimits::configured();
    }

    public function inspect(string $zipPath): ReleaseManifest
    {
        $this->assertPackage($zipPath);
        $zip = $this->openArchive($zipPath);
        try {
            return $this->readManifest($zip, $this->inspectArchive($zip));
        } finally {
            $zip->close();
        }
    }

    public function verify(
        string $zipPath,
        int $currentSequence = 0,
        ?\DateTimeImmutable $now = null,
        ?string $expectedPackageChecksum = null
    ): VerifiedReleaseManifest {
        $this->assertPackage($zipPath, $expectedPackageChecksum);
        $zip = $this->openArchive($zipPath);
        try {
            $manifest = $this->readManifest($zip, $this->inspectArchive($zip));
            return $this->manifestVerifier->verify(
                $manifest,
                $currentSequence,
                $now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
            );
        } finally {
            $zip->close();
        }
    }

    public function import(
        string $zipPath,
        int $currentSequence = 0,
        ?\DateTimeImmutable $now = null,
        ?string $expectedPackageChecksum = null
    ): VerifiedReleaseManifest {
        $this->assertPackage($zipPath, $expectedPackageChecksum);
        $zip = $this->openArchive($zipPath);

        $staging = null;
        $lock = null;
        try {
            $entries = $this->inspectArchive($zip);
            $manifest = $this->readManifest($zip, $entries);

            // 验签必须发生在创建 staging 和写入任何包内容之前。
            $verified = $this->manifestVerifier->verify(
                $manifest,
                $currentSequence,
                $now ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC'))
            );

            $releasesRoot = $this->prepareReleasesRoot();
            $lock = $this->acquireReleaseLock($releasesRoot, $manifest->releaseId());
            $staging = $this->createStagingDirectory($releasesRoot, $manifest->releaseId());
            $this->extractArchive($zip, $entries, $staging);
            $this->auditExtractedTree($staging);
            $this->verifyFileHashes($staging, $manifest);
            $this->makeImmutable($staging);

            $destination = $this->paths->releaseRoot($manifest->releaseId());
            if (file_exists($destination) || is_link($destination)) {
                if (!$this->treesAreIdentical($staging, $destination)) {
                    throw new \RuntimeException('同名不可变 Release 已存在且内容不同');
                }
                $this->makeImmutable($destination);
                return $verified;
            }

            if (!rename($staging, $destination)) {
                if ((file_exists($destination) || is_link($destination))
                    && $this->treesAreIdentical($staging, $destination)) {
                    return $verified;
                }
                throw new \RuntimeException('发布系统 Release 失败，目标未被覆盖');
            }
            $staging = null;
            return $verified;
        } finally {
            $zip->close();
            if ($staging !== null) $this->removeDirectory($staging);
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    /**
     * @return array<int, array{name: string, directory: bool, size: int, mode: int}>
     */
    private function inspectArchive(\ZipArchive $zip): array
    {
        if ($zip->numFiles < 1 || $zip->numFiles > $this->limits->entries()) {
            throw new \RuntimeException('Release ZIP 条目数量超出限制');
        }

        $entries = [];
        $types = [];
        $manifestCount = 0;
        $totalBytes = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index, \ZipArchive::FL_UNCHANGED);
            $stat = $zip->statIndex($index, \ZipArchive::FL_UNCHANGED);
            if (!is_string($name) || !is_array($stat)) {
                throw new \RuntimeException('Release ZIP 包含无法读取的条目');
            }

            $directory = substr($name, -1) === '/';
            $cleanName = $directory ? substr($name, 0, -1) : $name;
            $this->assertSafePath($cleanName);
            $normalized = strtolower($cleanName);
            if (isset($types[$normalized])) throw new \RuntimeException('Release ZIP 包含大小写重复路径');
            $types[$normalized] = $directory;

            if (strtolower((string) basename($cleanName)) === 'release.json') {
                if ($directory || $cleanName !== 'release.json') {
                    throw new \RuntimeException('release.json 必须是 ZIP 根目录唯一文件');
                }
                $manifestCount++;
            }

            $size = $stat['size'] ?? null;
            $compressedSize = $stat['comp_size'] ?? null;
            if (!is_int($size) || $size < 0 || !is_int($compressedSize) || $compressedSize < 0) {
                throw new \RuntimeException('Release ZIP 条目大小信息不合法');
            }
            if ($directory && $size !== 0) throw new \RuntimeException('Release ZIP 目录条目包含数据');
            if ($size > $this->limits->fileBytes() || $totalBytes > $this->limits->unpackedBytes() - $size) {
                throw new \RuntimeException('Release ZIP 解压体积超出限制');
            }
            $totalBytes += $size;
            if (($stat['encryption_method'] ?? 0) !== 0) {
                throw new \RuntimeException('Release ZIP 不允许加密条目');
            }

            [$mode, $kind] = $this->entryModeAndKind($zip, $index);
            if ($kind !== 0 && $kind !== 0x4000 && $kind !== 0x8000) {
                throw new \RuntimeException('Release ZIP 不允许 symlink 或特殊文件');
            }
            if (($kind === 0x4000 && !$directory) || ($kind === 0x8000 && $directory)) {
                throw new \RuntimeException('Release ZIP 条目类型与路径不一致');
            }

            $entries[$index] = [
                'name' => $cleanName,
                'directory' => $directory,
                'size' => $size,
                'mode' => $mode,
            ];
        }

        if ($manifestCount !== 1) throw new \RuntimeException('Release ZIP 必须包含一个根目录 release.json');

        foreach ($types as $path => $directory) {
            $segments = explode('/', $path);
            array_pop($segments);
            $parent = '';
            foreach ($segments as $segment) {
                $parent = $parent === '' ? $segment : $parent . '/' . $segment;
                if (isset($types[$parent]) && $types[$parent] === false) {
                    throw new \RuntimeException('Release ZIP 文件路径与子条目冲突');
                }
            }
        }
        return $entries;
    }

    /** @param array<int, array{name: string, directory: bool, size: int, mode: int}> $entries */
    private function readManifest(\ZipArchive $zip, array $entries): ReleaseManifest
    {
        $manifestIndex = null;
        foreach ($entries as $index => $entry) {
            if ($entry['name'] !== 'release.json') continue;
            if ($entry['size'] < 2 || $entry['size'] > $this->limits->manifestBytes()) {
                throw new ReleaseManifestException('release.json 大小不合法');
            }
            $manifestIndex = $index;
            break;
        }
        if ($manifestIndex === null) throw new ReleaseManifestException('release.json 不存在');

        $json = $zip->getFromIndex($manifestIndex, $this->limits->manifestBytes(), \ZipArchive::FL_UNCHANGED);
        if (!is_string($json) || strlen($json) !== $entries[$manifestIndex]['size']) {
            throw new ReleaseManifestException('release.json 读取不完整');
        }
        try {
            $data = json_decode($json, true, 128, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ReleaseManifestException('release.json 不是有效 JSON', 0, $exception);
        }
        if (!is_array($data)) throw new ReleaseManifestException('release.json 根节点必须是对象');
        return ReleaseManifest::fromArray($data);
    }

    private function prepareReleasesRoot(): string
    {
        $instanceRoot = $this->paths->instanceRoot();
        if (!is_dir($instanceRoot) && !mkdir($instanceRoot, 0750, true) && !is_dir($instanceRoot)) {
            throw new \RuntimeException('无法创建 LoveCards 实例目录');
        }
        if (is_link($instanceRoot)) throw new \RuntimeException('LoveCards 实例目录不能是 symlink');
        $instanceReal = realpath($instanceRoot);
        if ($instanceReal === false) throw new \RuntimeException('LoveCards 实例目录无效');

        $releasesRoot = $this->paths->releasesRoot();
        if (!is_dir($releasesRoot) && !mkdir($releasesRoot, 0750, true) && !is_dir($releasesRoot)) {
            throw new \RuntimeException('无法创建系统 Release 根目录');
        }
        if (is_link($releasesRoot)) throw new \RuntimeException('系统 Release 根目录不能是 symlink');
        $releasesReal = realpath($releasesRoot);
        if ($releasesReal === false || !$this->isWithin($releasesReal, $instanceReal)) {
            throw new \RuntimeException('系统 Release 根目录逃逸实例目录');
        }
        return $releasesReal;
    }

    /** @return resource */
    private function acquireReleaseLock(string $releasesRoot, string $releaseId)
    {
        $lockRoot = $releasesRoot . DIRECTORY_SEPARATOR . '.locks';
        if (!is_dir($lockRoot) && !mkdir($lockRoot, 0750) && !is_dir($lockRoot)) {
            throw new \RuntimeException('无法创建 Release 导入锁目录');
        }
        if (is_link($lockRoot)) throw new \RuntimeException('Release 导入锁目录不能是 symlink');
        $lockPath = $lockRoot . DIRECTORY_SEPARATOR . hash('sha256', $releaseId) . '.lock';
        $handle = fopen($lockPath, 'c+b');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) fclose($handle);
            throw new \RuntimeException('无法获取 Release 导入锁');
        }
        return $handle;
    }

    private function createStagingDirectory(string $releasesRoot, string $releaseId): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $path = $releasesRoot . DIRECTORY_SEPARATOR . '.staging-'
                . hash('sha256', $releaseId) . '-' . bin2hex(random_bytes(8));
            if (mkdir($path, 0750)) return $path;
        }
        throw new \RuntimeException('无法创建 Release staging 目录');
    }

    /** @param array<int, array{name: string, directory: bool, size: int, mode: int}> $entries */
    private function extractArchive(\ZipArchive $zip, array $entries, string $staging): void
    {
        foreach ($entries as $entry) {
            $destination = $staging . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $entry['name']);
            if ($entry['directory']) {
                $this->ensureExtractDirectory($destination);
                continue;
            }

            $this->ensureExtractDirectory(dirname($destination));
            $source = $zip->getStream($entry['name']);
            if (!is_resource($source)) throw new \RuntimeException('无法读取 Release ZIP 条目: ' . $entry['name']);
            $target = fopen($destination, 'xb');
            if ($target === false) {
                fclose($source);
                throw new \RuntimeException('Release ZIP 条目拒绝覆盖: ' . $entry['name']);
            }

            $written = 0;
            try {
                while (!feof($source)) {
                    $chunk = fread($source, self::COPY_CHUNK_BYTES);
                    if ($chunk === false) throw new \RuntimeException('读取 Release ZIP 条目失败: ' . $entry['name']);
                    if ($chunk === '') continue;
                    $length = strlen($chunk);
                    if ($written > $entry['size'] - $length) {
                        throw new \RuntimeException('Release ZIP 条目实际大小超出声明');
                    }
                    $offset = 0;
                    while ($offset < $length) {
                        $count = fwrite($target, substr($chunk, $offset));
                        if ($count === false || $count === 0) {
                            throw new \RuntimeException('写入 Release staging 失败');
                        }
                        $offset += $count;
                    }
                    $written += $length;
                }
            } finally {
                fclose($target);
                fclose($source);
            }
            if ($written !== $entry['size']) throw new \RuntimeException('Release ZIP 条目实际大小不符');
            chmod($destination, ($entry['mode'] & 0111) !== 0 ? 0750 : 0640);
        }
    }

    private function ensureExtractDirectory(string $directory): void
    {
        if (is_dir($directory)) return;
        if (file_exists($directory) || is_link($directory)
            || (!mkdir($directory, 0750, true) && !is_dir($directory))) {
            throw new \RuntimeException('无法创建 Release staging 子目录');
        }
    }

    private function auditExtractedTree(string $root): void
    {
        if (is_link($root)) throw new \RuntimeException('Release staging 不能是 symlink');
        $rootReal = realpath($root);
        if ($rootReal === false) throw new \RuntimeException('Release staging 路径无效');

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $path = $item->getPathname();
            if (is_link($path)) throw new \RuntimeException('Release staging 包含 symlink');
            $real = realpath($path);
            if ($real === false || !$this->isWithin($real, $rootReal) || $real === $rootReal) {
                throw new \RuntimeException('Release staging 路径逃逸');
            }
            if (!$item->isDir() && !$item->isFile()) {
                throw new \RuntimeException('Release staging 包含特殊文件');
            }
        }
    }

    private function verifyFileHashes(string $root, ReleaseManifest $manifest): void
    {
        $expected = $manifest->fileHashes();
        $folded = [];
        foreach ($expected as $path => $_hash) {
            if (strtolower($path) === 'release.json') {
                throw new ReleaseManifestException('file_hashes 不能包含 release.json 自引用');
            }
            $normalized = strtolower($path);
            if (isset($folded[$normalized])) {
                throw new ReleaseManifestException('file_hashes 包含大小写重复路径');
            }
            $folded[$normalized] = true;
        }

        $actual = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if (!$item->isFile() || is_link($item->getPathname())) continue;
            $path = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
            if ($path === 'release.json') continue;
            $hash = hash_file('sha256', $item->getPathname());
            if ($hash === false) throw new \RuntimeException('无法计算 Release 文件 Hash');
            $actual[$path] = $hash;
        }
        ksort($actual, SORT_STRING);

        if (array_keys($actual) !== array_keys($expected)) {
            $missing = array_values(array_diff(array_keys($expected), array_keys($actual)));
            $unexpected = array_values(array_diff(array_keys($actual), array_keys($expected)));
            $details = [];
            if ($missing !== []) $details[] = '缺少: ' . implode(', ', array_slice($missing, 0, 5));
            if ($unexpected !== []) $details[] = '未声明: ' . implode(', ', array_slice($unexpected, 0, 5));
            throw new ReleaseManifestException('Release 文件集合与 file_hashes 不一致' . ($details === [] ? '' : '（' . implode('；', $details) . '）'));
        }
        foreach ($expected as $path => $hash) {
            if (!hash_equals($hash, $actual[$path])) {
                throw new ReleaseManifestException('Release 文件 Hash 不匹配: ' . $path);
            }
        }
    }

    private function treesAreIdentical(string $candidate, string $destination): bool
    {
        if (is_link($destination) || !is_dir($destination)) return false;
        $this->auditExtractedTree($destination);
        return $this->treeSnapshot($candidate) === $this->treeSnapshot($destination);
    }

    /** @return string[] */
    private function treeSnapshot(string $root): array
    {
        $snapshot = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $path = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
            if ($item->isDir()) {
                $snapshot[] = 'D:' . $path;
                continue;
            }
            $hash = hash_file('sha256', $item->getPathname());
            if ($hash === false) throw new \RuntimeException('无法核对既有 Release 内容');
            $snapshot[] = 'F:' . $path . ':' . $hash;
        }
        sort($snapshot, SORT_STRING);
        return $snapshot;
    }

    private function assertPackage(string $zipPath, ?string $expectedChecksum = null): void
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('ZIP 扩展不可用，无法导入系统 Release');
        }
        if (!is_file($zipPath) || is_link($zipPath) || !is_readable($zipPath)) {
            throw new \RuntimeException('Release ZIP 不存在、是 symlink 或不可读');
        }
        $zipBytes = filesize($zipPath);
        if ($zipBytes === false || $zipBytes < 1 || $zipBytes > $this->limits->zipBytes()) {
            throw new \RuntimeException('Release ZIP 大小超出限制');
        }
        if ($expectedChecksum !== null) {
            $expectedChecksum = strtolower($expectedChecksum);
            if (preg_match('/^[a-f0-9]{64}$/', $expectedChecksum) !== 1) {
                throw new \InvalidArgumentException('Release ZIP 预期 Checksum 不合法');
            }
            $actual = hash_file('sha256', $zipPath);
            if ($actual === false || !hash_equals($expectedChecksum, $actual)) {
                throw new \RuntimeException('Release ZIP 在计划创建后发生变化');
            }
        }
    }

    private function openArchive(string $zipPath): \ZipArchive
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::RDONLY) !== true) {
            throw new \RuntimeException('无法打开 Release ZIP');
        }
        return $zip;
    }

    private function makeImmutable(string $root): void
    {
        if (is_link($root) || !is_dir($root)) {
            throw new \RuntimeException('不可变 Release 根目录不合法');
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $path = $item->getPathname();
            if (is_link($path)) throw new \RuntimeException('不可变 Release 包含 symlink');
            if ($item->isDir()) {
                if (!@chmod($path, 0550) && DIRECTORY_SEPARATOR !== '\\') {
                    throw new \RuntimeException('无法冻结 Release 目录权限');
                }
                continue;
            }
            $mode = ($item->getPerms() & 0111) !== 0 ? 0550 : 0440;
            if (!@chmod($path, $mode) && DIRECTORY_SEPARATOR !== '\\') {
                throw new \RuntimeException('无法冻结 Release 文件权限');
            }
        }
        if (!@chmod($root, 0550) && DIRECTORY_SEPARATOR !== '\\') {
            throw new \RuntimeException('无法冻结 Release 根目录权限');
        }
    }

    private function assertSafePath(string $path): void
    {
        if ($path === ''
            || strlen($path) > self::MAX_PATH_BYTES
            || strpos($path, "\0") !== false
            || strpos($path, '\\') !== false
            || $path[0] === '/'
            || preg_match('/^[A-Za-z]:/', $path) === 1
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1) {
            throw new \RuntimeException('Release ZIP 包含不安全路径');
        }
        $segments = explode('/', $path);
        if (count($segments) > self::MAX_PATH_DEPTH) throw new \RuntimeException('Release ZIP 路径层级超出限制');
        foreach ($segments as $segment) {
            if ($segment === ''
                || $segment === '.'
                || $segment === '..'
                || strlen($segment) > 255
                || preg_match('/[<>:"|?*]/', $segment) === 1
                || preg_match('/[. ]$/', $segment) === 1
                || preg_match('/^(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/i', $segment) === 1) {
                throw new \RuntimeException('Release ZIP 包含不安全路径');
            }
        }
    }

    /** @return array{int, int} mode 与 Unix 文件类型。 */
    private function entryModeAndKind(\ZipArchive $zip, int $index): array
    {
        $opsys = 0;
        $attributes = 0;
        if (!$zip->getExternalAttributesIndex($index, $opsys, $attributes, \ZipArchive::FL_UNCHANGED)) {
            return [0, 0];
        }
        $mode = ($attributes >> 16) & 0xFFFF;
        return [$mode, $mode & 0xF000];
    }

    private function isWithin(string $candidate, string $root): bool
    {
        $candidate = str_replace('\\', '/', rtrim($candidate, '/\\'));
        $root = str_replace('\\', '/', rtrim($root, '/\\'));
        if (DIRECTORY_SEPARATOR === '\\') {
            $candidate = strtolower($candidate);
            $root = strtolower($root);
        }
        return strncmp($candidate, $root . '/', strlen($root) + 1) === 0;
    }

    private function removeDirectory(string $directory): void
    {
        if (is_link($directory)) {
            @unlink($directory);
            return;
        }
        if (!is_dir($directory)) return;
        @chmod($directory, 0750);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $path = $item->getPathname();
            if ($item->isDir() && !is_link($path)) {
                @chmod($path, 0750);
                @rmdir($path);
            } else {
                @chmod($path, 0640);
                @unlink($path);
            }
        }
        @rmdir($directory);
    }
}
