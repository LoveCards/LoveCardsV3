<?php

namespace app\modules\Theme\Infrastructure;

use app\modules\Theme\Domain\ThemeManifest;

final class SecureThemeInstaller
{
    public const MAX_ZIP_BYTES = 33554432;
    private const MAX_ENTRIES = 1000;
    private const MAX_FILE_BYTES = 16777216;
    private const MAX_TOTAL_BYTES = 67108864;

    public function install(string $zipPath): array
    {
        if (!is_file($zipPath) || (int)filesize($zipPath) > self::MAX_ZIP_BYTES) throw new \RuntimeException('ZIP 文件过大或不可读');
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) throw new \RuntimeException('无法打开 ZIP 文件');
        $staging = null;
        try {
            if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_ENTRIES) throw new \RuntimeException('ZIP 文件条目数量超出限制');
            $total = 0;
            $seen = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string)$zip->getNameIndex($i);
                $this->assertEntry($zip, $i, $name, $seen);
                $stat = $zip->statIndex($i);
                $size = (int)($stat['size'] ?? 0);
                if ($size > self::MAX_FILE_BYTES || ($total += $size) > self::MAX_TOTAL_BYTES) throw new \RuntimeException('ZIP 解压体积超出限制');
            }

            $root = FilesystemThemeCatalog::root();
            if (!is_dir($root) && !mkdir($root, 0755, true)) throw new \RuntimeException('无法创建主题根目录');
            $staging = $root . DIRECTORY_SEPARATOR . '.staging-' . bin2hex(random_bytes(8));
            if (!mkdir($staging, 0755, true) || !$zip->extractTo($staging)) throw new \RuntimeException('主题解压失败');
            $this->auditExtractedTree($staging);

            $manifest = ThemeManifest::load($staging);
            $id = (string)$manifest['id'];
            $version = (string)$manifest['version'];
            $digest = substr(hash('sha256', $this->directoryDigest($staging)), 0, 16);
            $releaseKey = $version . '-' . $digest;
            $releaseRoot = $root . DIRECTORY_SEPARATOR . '.releases' . DIRECTORY_SEPARATOR . $id;
            if (!is_dir($releaseRoot) && !mkdir($releaseRoot, 0755, true)) throw new \RuntimeException('无法创建主题 release 目录');
            $destination = $releaseRoot . DIRECTORY_SEPARATOR . $releaseKey;
            if (is_dir($destination)) {
                $this->removeDirectory($staging);
                $staging = null;
                return self::summary($manifest, $id, $releaseKey);
            }
            if (!rename($staging, $destination)) throw new \RuntimeException('主题 release 发布失败');
            $staging = null;
            return self::summary($manifest, $id, $releaseKey);
        } finally {
            $zip->close();
            if ($staging && is_dir($staging)) $this->removeDirectory($staging);
        }
    }

    private static function summary(array $manifest, string $id, string $release): array
    {
        return [
            'id' => $id,
            'name' => $manifest['name'],
            'version' => $manifest['version'],
            'mode' => $manifest['mode'],
            'release' => $release,
            'author' => $manifest['author'] ?? '',
            'description' => $manifest['description'] ?? '',
            'active' => false,
        ];
    }

    private function assertEntry(\ZipArchive $zip, int $index, string $name, array &$seen): void
    {
        if ($name === '' || strpos($name, "\0") !== false || strpos($name, '\\') !== false || $name[0] === '/' || preg_match('/^[A-Za-z]:/', $name)) throw new \RuntimeException('ZIP 包含不安全路径');
        $normalized = strtolower($name);
        if (isset($seen[$normalized])) throw new \RuntimeException('ZIP 包含重复路径');
        $seen[$normalized] = true;
        foreach (explode('/', rtrim($name, '/')) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || $segment[0] === '.' || preg_match('/[. ]$/', $segment)) throw new \RuntimeException('ZIP 包含不安全路径');
            if (preg_match('/\.(php[0-9]*|phtml|phar|cgi|pl|htaccess|config)$/i', $segment)) throw new \RuntimeException('主题不允许服务器文件');
        }
        $opsys = 0;
        $attributes = 0;
        if ($zip->getExternalAttributesIndex($index, $opsys, $attributes)) {
            $type = ($attributes >> 16) & 0xF000;
            if ($type !== 0 && $type !== 0x4000 && $type !== 0x8000) throw new \RuntimeException('ZIP 不允许符号链接或特殊文件');
        }
    }

    private function auditExtractedTree(string $dir): void
    {
        $root = realpath($dir);
        if ($root === false) throw new \RuntimeException('主题临时目录无效');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (is_link($file->getPathname()) || realpath($file->getPathname()) === false) throw new \RuntimeException('主题不允许符号链接');
            $real = realpath($file->getPathname());
            if ($real === false || strpos($real, $root . DIRECTORY_SEPARATOR) !== 0) throw new \RuntimeException('主题路径逃逸');
            if (!$file->isDir() && !$file->isFile()) throw new \RuntimeException('主题包含特殊文件');
        }
    }

    private function directoryDigest(string $dir): string
    {
        $entries = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) if ($file->isFile()) $entries[] = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1)) . ':' . hash_file('sha256', $file->getPathname());
        sort($entries);
        return implode("\n", $entries);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            if ($file->isDir() && !is_link($file->getPathname())) @rmdir($file->getPathname());
            else @unlink($file->getPathname());
        }
        @rmdir($dir);
    }
}
