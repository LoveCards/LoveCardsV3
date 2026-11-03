<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Infrastructure;

use app\modules\Operations\SystemLifecycle\Domain\HandlerCatalogHasher;
use app\modules\Operations\SystemLifecycle\Domain\ReleaseManifest;
use app\modules\Platform\Eventing\Public\HandlerRegistry;

/** Builds a root-level, fully hashed and signed immutable backend Release ZIP. */
final class SignedReleaseBuilder
{
    private const EXCLUDED_ROOTS = [
        '.git', '.github', '.idea', '.vscode', '.lovecards-instance',
        'node_modules', 'runtime', 'tests',
    ];

    public function __construct(
        private HandlerRegistry $handlers,
        private ?ReleaseArchiveLimits $limits = null
    ) {
        $this->limits ??= ReleaseArchiveLimits::configured();
    }

    /**
     * @param string[] $requiredExtensions
     * @param string[] $optionalFacilities
     * @return array{path:string,checksum:string,release_id:string,sequence:int,file_count:int,bytes:int}
     */
    public function build(
        string $sourceRoot,
        string $outputZip,
        string $privateKeyPath,
        string $signatureKeyId,
        string $releaseId,
        string $version,
        string $build,
        int $sequence,
        string $sourceSchemaMin = '0',
        ?string $sourceSchemaMax = null,
        string $channel = 'stable',
        string $minimumPhp = '8.1',
        string $minimumMysql = '5.7',
        array $requiredExtensions = [],
        array $optionalFacilities = [],
        ?\DateTimeImmutable $issuedAt = null,
        ?\DateTimeImmutable $expiresAt = null,
        ?string $privateKeyPassphrase = null,
        bool $overwrite = false
    ): array {
        if (!class_exists(\ZipArchive::class)) throw new \RuntimeException('ZIP 扩展不可用，无法构建 Release');
        if (!extension_loaded('openssl')) throw new \RuntimeException('OpenSSL 扩展不可用，无法签名 Release');

        $sourceRoot = $this->sourceRoot($sourceRoot);
        $outputZip = $this->absoluteOutputPath($outputZip);
        $privateKeyPath = $this->privateKeyPath($privateKeyPath, $sourceRoot);
        $this->assertOutputOutsideSource($outputZip, $sourceRoot);
        if ((file_exists($outputZip) || is_link($outputZip)) && !$overwrite) {
            throw new \RuntimeException('Release 输出文件已存在；如需覆盖请显式使用 force');
        }

        $files = $this->collectFiles($sourceRoot);
        $this->assertRunnableRelease($files);
        $catalogJson = json_encode(
            $this->handlers->catalog(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ) . "\n";

        $fileHashes = ['handler-catalog.json' => hash('sha256', $catalogJson)];
        $migrationChecksums = [];
        $bytes = strlen($catalogJson);
        foreach ($files as $relative => $absolute) {
            $size = filesize($absolute);
            if ($size === false) throw new \RuntimeException('无法读取 Release 文件大小: ' . $relative);
            $bytes += $size;
            if ($size > $this->limits->fileBytes() || $bytes > $this->limits->unpackedBytes()) {
                throw new \RuntimeException('Release 文件或总解压体积超出配置限制');
            }
            $hash = hash_file('sha256', $absolute);
            if ($hash === false) throw new \RuntimeException('无法计算 Release 文件 Hash: ' . $relative);
            $fileHashes[$relative] = $hash;
            if (preg_match('#^database/migrations/([0-9]{14}(?:_[a-z0-9][a-z0-9_-]{0,63})?)\.sql$#', $relative, $match) === 1) {
                $migrationChecksums[$match[1]] = $hash;
            }
        }
        ksort($fileHashes, SORT_STRING);
        ksort($migrationChecksums, SORT_STRING);
        if ($migrationChecksums === []) throw new \RuntimeException('Release 必须包含至少一个数据库迁移');
        $targetSchema = (string) array_key_last($migrationChecksums);
        $sourceSchemaMax ??= $targetSchema;

        $issuedAt = ($issuedAt ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('UTC'));
        $expiresAt = ($expiresAt ?? $issuedAt->modify('+7 days'))->setTimezone(new \DateTimeZone('UTC'));
        $manifestData = [
            'schema_version' => 1,
            'release_id' => $releaseId,
            'version' => $version,
            'build' => $build,
            'channel' => $channel,
            'sequence' => $sequence,
            'issued_at' => $issuedAt->format('Y-m-d\TH:i:s\Z'),
            'expires_at' => $expiresAt->format('Y-m-d\TH:i:s\Z'),
            'minimum_php' => $minimumPhp,
            'minimum_mysql' => $minimumMysql,
            'required_extensions' => array_values($requiredExtensions),
            'optional_facilities' => array_values($optionalFacilities),
            'source_schema_range' => ['min' => $sourceSchemaMin, 'max' => $sourceSchemaMax],
            'target_schema' => $targetSchema,
            'migration_checksums' => $migrationChecksums,
            'handler_catalog_hash' => HandlerCatalogHasher::hashArray($this->handlers->catalog()),
            'file_hashes' => $fileHashes,
            'signature_key_id' => $signatureKeyId,
            'signature' => base64_encode('unsigned'),
        ];
        $unsigned = ReleaseManifest::fromArray($manifestData);
        $privateKey = $this->loadPrivateKey($privateKeyPath, $privateKeyPassphrase);
        if (!openssl_sign($unsigned->canonicalPayload(), $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('OpenSSL 无法签名 Release Manifest');
        }
        $manifestData['signature'] = base64_encode($signature);
        $manifest = ReleaseManifest::fromArray($manifestData);
        $manifestJson = json_encode(
            $manifest->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ) . "\n";
        $bytes += strlen($manifestJson);

        $parent = dirname($outputZip);
        if (!is_dir($parent) && !mkdir($parent, 0750, true) && !is_dir($parent)) {
            throw new \RuntimeException('无法创建 Release 输出目录');
        }
        if (is_link($parent)) throw new \RuntimeException('Release 输出目录不能是 symlink');
        $temporary = $outputZip . '.tmp-' . bin2hex(random_bytes(8));
        try {
            $this->writeArchive($temporary, $files, $catalogJson, $manifestJson);
            $zipSize = filesize($temporary);
            if ($zipSize === false || $zipSize < 1 || $zipSize > $this->limits->zipBytes()) {
                throw new \RuntimeException('Release ZIP 大小超出配置限制');
            }
            if ((file_exists($outputZip) || is_link($outputZip)) && !@unlink($outputZip)) {
                throw new \RuntimeException('无法替换既有 Release 输出文件');
            }
            if (!rename($temporary, $outputZip)) throw new \RuntimeException('无法原子发布 Release ZIP');
        } finally {
            if (is_file($temporary)) @unlink($temporary);
        }

        $checksum = hash_file('sha256', $outputZip);
        if ($checksum === false) throw new \RuntimeException('无法计算 Release ZIP Checksum');
        return [
            'path' => $outputZip,
            'checksum' => $checksum,
            'release_id' => $manifest->releaseId(),
            'sequence' => $manifest->sequence(),
            'file_count' => count($fileHashes),
            'bytes' => $bytes,
        ];
    }

    private function sourceRoot(string $path): string
    {
        if ($path === '' || is_link($path) || !is_dir($path)) throw new \InvalidArgumentException('Release source root 不存在或不安全');
        $real = realpath($path);
        if ($real === false) throw new \InvalidArgumentException('Release source root 无法解析');
        return rtrim($real, '/\\');
    }

    private function absoluteOutputPath(string $path): string
    {
        if ($path === '' || str_contains($path, "\0") || !$this->isAbsolute($path)) {
            throw new \InvalidArgumentException('Release 输出路径必须是绝对路径');
        }
        return str_replace('/', DIRECTORY_SEPARATOR, $this->canonicalAbsolutePath($path));
    }

    private function privateKeyPath(string $path, string $sourceRoot): string
    {
        if ($path === '' || str_contains($path, "\0") || is_link($path) || !is_file($path) || !is_readable($path)) {
            throw new \InvalidArgumentException('Release 私钥文件不存在或不安全');
        }
        $real = realpath($path);
        if ($real === false) throw new \InvalidArgumentException('Release 私钥路径无法解析');
        if ($this->isWithin($real, $sourceRoot)) throw new \InvalidArgumentException('Release 私钥必须位于待打包代码目录之外');
        return $real;
    }

    /** @return array<string, string> */
    private function collectFiles(string $root): array
    {
        $files = [];
        $folded = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $absolute = $item->getPathname();
            $relative = str_replace('\\', '/', substr($absolute, strlen($root) + 1));
            if ($this->excluded($relative)) continue;
            if (is_link($absolute)) throw new \RuntimeException('Release source 包含 symlink: ' . $relative);
            if ($item->isDir()) continue;
            if (!$item->isFile() || !$item->isReadable()) throw new \RuntimeException('Release source 包含不可读特殊文件: ' . $relative);
            $this->assertSafeRelativePath($relative);
            $lower = strtolower($relative);
            if (isset($folded[$lower])) throw new \RuntimeException('Release source 包含大小写重复路径: ' . $relative);
            $folded[$lower] = true;
            $files[$relative] = $absolute;
            if (count($files) + 2 > $this->limits->entries()) throw new \RuntimeException('Release 文件数量超出配置限制');
        }
        ksort($files, SORT_STRING);
        return $files;
    }

    private function excluded(string $relative): bool
    {
        $segments = explode('/', $relative);
        if (in_array($segments[0], self::EXCLUDED_ROOTS, true)) return true;
        if ($relative === 'release.json' || $relative === 'handler-catalog.json' || $relative === 'install.lock') return true;
        if ($relative === 'config/rsa.php' || str_starts_with($relative, 'config/rsa/')) return true;
        if (str_starts_with($relative, 'public/storage/')) return true;
        if (str_starts_with($relative, 'themes/.releases/')) return true;
        if ($segments[0] === '.env' || str_starts_with($segments[0], '.env.')) return $segments[0] !== '.env.example';
        return false;
    }

    /** @param array<string, string> $files */
    private function assertRunnableRelease(array $files): void
    {
        foreach (['bootstrap/ReleaseBootstrap.php', 'public/index.php', 'think', 'vendor/autoload.php', 'config/app.php'] as $required) {
            if (!isset($files[$required])) throw new \RuntimeException('Release source 缺少运行必需文件: ' . $required);
        }
    }

    /** @return resource|\OpenSSLAsymmetricKey */
    private function loadPrivateKey(string $path, ?string $passphrase)
    {
        $size = filesize($path);
        if ($size === false || $size < 1 || $size > 1048576) throw new \InvalidArgumentException('Release 私钥文件大小不合法');
        $pem = file_get_contents($path);
        if ($pem === false || strlen($pem) !== $size) throw new \RuntimeException('无法完整读取 Release 私钥');
        $key = openssl_pkey_get_private($pem, $passphrase ?? '');
        if ($key === false) throw new \InvalidArgumentException('Release 私钥无法解析或口令错误');
        return $key;
    }

    /** @param array<string, string> $files */
    private function writeArchive(string $path, array $files, string $catalogJson, string $manifestJson): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::EXCL) !== true) throw new \RuntimeException('无法创建 Release ZIP');
        try {
            if (!$zip->addFromString('release.json', $manifestJson)
                || !$zip->addFromString('handler-catalog.json', $catalogJson)) {
                throw new \RuntimeException('无法写入 Release 元数据');
            }
            foreach ($files as $relative => $absolute) {
                if (!$zip->addFile($absolute, $relative)) throw new \RuntimeException('无法写入 Release 文件: ' . $relative);
                $mode = $relative === 'think' || (fileperms($absolute) & 0111) !== 0 ? 0100750 : 0100640;
                $zip->setExternalAttributesName($relative, \ZipArchive::OPSYS_UNIX, $mode << 16);
            }
        } finally {
            if (!$zip->close()) throw new \RuntimeException('无法完成 Release ZIP 写入');
        }
    }

    private function assertOutputOutsideSource(string $output, string $source): void
    {
        $parent = dirname($output);
        if ($this->samePath($parent, $source) || $this->isWithin($parent, $source)) {
            throw new \InvalidArgumentException('Release 输出必须位于待打包代码目录之外');
        }
    }

    private function assertSafeRelativePath(string $path): void
    {
        if ($path === '' || strlen($path) > 1024 || str_contains($path, "\0") || str_contains($path, '\\') || $path[0] === '/') {
            throw new \RuntimeException('Release source 路径不安全');
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || strlen($segment) > 255
                || preg_match('/[<>:"|?*]/', $segment) === 1 || preg_match('/[. ]$/', $segment) === 1) {
                throw new \RuntimeException('Release source 路径不安全: ' . $path);
            }
        }
    }

    private function isAbsolute(string $path): bool
    {
        return $path[0] === '/' || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1;
    }

    private function canonicalAbsolutePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $prefix = $path[0] === '/' ? '/' : strtoupper(substr($path, 0, 2)) . '/';
        $tail = $path[0] === '/' ? substr($path, 1) : substr($path, 3);
        $segments = [];
        foreach (explode('/', $tail) as $segment) {
            if ($segment === '' || $segment === '.') continue;
            if ($segment === '..') {
                if ($segments === []) throw new \InvalidArgumentException('Release 输出路径逃逸根目录');
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }
        if ($segments === []) throw new \InvalidArgumentException('Release 输出路径必须包含文件名');
        return $prefix . implode('/', $segments);
    }

    private function isWithin(string $candidate, string $root): bool
    {
        $candidate = $this->normalizedPath($candidate);
        $root = $this->normalizedPath($root);
        return str_starts_with($candidate, $root . '/');
    }

    private function samePath(string $left, string $right): bool
    {
        return $this->normalizedPath($left) === $this->normalizedPath($right);
    }

    private function normalizedPath(string $path): string
    {
        $path = str_replace('\\', '/', rtrim($path, '/\\'));
        return DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
    }
}
