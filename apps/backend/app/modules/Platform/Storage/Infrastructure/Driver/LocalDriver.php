<?php

namespace app\modules\Platform\Storage\Infrastructure\Driver;

use think\file\UploadedFile;
use app\modules\Platform\Storage\Contract\LocalFileProvider;
use app\modules\Platform\Storage\Contract\StorageResult;
use app\shared\SystemPaths\LocalStoragePath;
use app\shared\SystemPaths\SystemPaths;

class LocalDriver extends AbstractDriver implements LocalFileProvider
{
    public function __construct(
        string $slug,
        array $config,
        private ?string $applicationRoot = null,
        private ?SystemPaths $systemPaths = null
    ) {
        parent::__construct($slug, $config);
    }

    public function getType(): string
    {
        return 'local';
    }

    public static function meta(): array
    {
        return [
            'type' => 'local',
            'name' => '本地存储',
            'icon' => 'mdi-harddisk',
            'fields' => [
                ['key' => 'root', 'label' => '存储根目录', 'type' => 'text'],
                ['key' => 'url_prefix', 'label' => 'URL前缀', 'type' => 'text'],
                ['key' => 'allow_mime_types', 'label' => '允许的MIME类型', 'type' => 'text'],
                ['key' => 'max_file_size', 'label' => '最大文件大小(字节)', 'type' => 'number'],
                ['key' => 'path_template', 'label' => '路径模板', 'type' => 'text'],
            ],
        ];
    }

    public function upload(UploadedFile $file, string $path): StorageResult
    {
        $this->validateFile($file);
        $mime = $this->detectMime($file->getPathname());
        $size = $file->getSize();
        $originalName = $file->getOriginalName();

        $savePath = str_replace('\\', '/', $path);
        $destination = LocalStoragePath::prepareDestination($this->storageRoot(), $savePath);
        $file->move(dirname($destination), basename($destination));
        if (!is_file($destination) || is_link($destination)) {
            throw new \RuntimeException('本地存储写入失败');
        }

        $url = $this->getUrl($savePath);

        return new StorageResult([
            'id' => 0,
            'url' => $url,
            'path' => $savePath,
            'driver_path' => $savePath,
            'size' => $size,
            'mime_type' => $mime,
            'original_name' => $originalName,
            'channel_slug' => $this->channelSlug,
        ]);
    }

    public function delete(string $driverPath): bool
    {
        if (!LocalStoragePath::safeRelative($driverPath)) return false;
        $fullPath = $this->resolveLocalFile($driverPath);
        if ($fullPath === null) {
            $candidate = rtrim($this->storageRoot(), '/\\') . DIRECTORY_SEPARATOR
                . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $driverPath);
            return !file_exists($candidate) && !is_link($candidate);
        }
        return unlink($fullPath);
    }

    public function getUrl(string $driverPath): string
    {
        if (!LocalStoragePath::safeRelative($driverPath)) {
            throw new \RuntimeException('本地存储路径不安全');
        }
        $urlPrefix = trim((string) ($this->config['url_prefix'] ?? '/storage'));
        if ($urlPrefix === '') throw new \RuntimeException('本地存储 URL 前缀不能为空');
        $urlPrefix = '/' . trim($urlPrefix, '/');
        if (preg_match('#^/[A-Za-z0-9/_-]+$#', $urlPrefix) !== 1) {
            throw new \RuntimeException('本地存储 URL 前缀不合法');
        }
        $encoded = implode('/', array_map('rawurlencode', explode('/', str_replace('\\', '/', $driverPath))));
        return $urlPrefix . '/' . $encoded;
    }

    public function resolveLocalFile(string $driverPath): ?string
    {
        return LocalStoragePath::resolveExisting($this->storageRoot(), $driverPath);
    }

    private function storageRoot(): string
    {
        $applicationRoot = rtrim($this->applicationRoot ?? app()->getRootPath(), '/\\');
        return LocalStoragePath::root($this->systemPaths, $applicationRoot, $this->config);
    }
}
