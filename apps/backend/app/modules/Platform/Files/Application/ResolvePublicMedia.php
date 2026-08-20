<?php
declare(strict_types=1);

namespace app\modules\Platform\Files\Application;

use app\modules\Platform\Files\Contract\FileRepository;
use app\modules\Platform\Files\Contract\LocalStorageReader;
use app\modules\Platform\Files\Contract\StorageDriver;

final class ResolvePublicMedia
{
    public function __construct(private FileRepository $files, private StorageDriver $storage) {}

    /** @return array{url:?string,local_path:?string,mime_type:string,etag:string}|null */
    public function execute(string $hash): ?array
    {
        $hash = strtolower(trim($hash));
        if (!preg_match('/^[a-f0-9]{16,64}$/', $hash)) return null;
        $file = $this->files->findByHash($hash, 0, false);
        if ($file === null) return null;
        $channel = trim((string) ($file['channel_slug'] ?? ''));
        $path = trim((string) ($file['driver_path'] ?? $file['file_path'] ?? ''));
        if ($channel === '' || $path === '') return null;

        if ($this->storage instanceof LocalStorageReader) {
            $local = $this->storage->resolveLocalFile($channel, $path);
            if ($local['managed']) {
                if (!is_string($local['path']) || $local['path'] === '') return null;
                return [
                    'url' => null,
                    'local_path' => $local['path'],
                    'mime_type' => (string) ($file['mime_type'] ?? 'application/octet-stream'),
                    'etag' => '"' . hash('sha256', $hash . '|' . (string) filesize($local['path']) . '|' . (string) filemtime($local['path'])) . '"',
                ];
            }
        }

        $url = trim((string) ($file['file_url'] ?? ''));
        if ($url === '' || preg_match('#^/media/[a-f0-9]{16,64}$#', $url) === 1) {
            $url = $this->storage->getUrl($channel, $path);
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $url) === 1) return null;
        $isLocalPath = str_starts_with($url, '/') && !str_starts_with($url, '//');
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $isHttpUrl = in_array($scheme, ['http', 'https'], true)
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
        if (!$isLocalPath && !$isHttpUrl) return null;
        return [
            'url' => $url,
            'local_path' => null,
            'mime_type' => (string) ($file['mime_type'] ?? 'application/octet-stream'),
            'etag' => '"' . hash('sha256', $hash . '|' . $url) . '"',
        ];
    }
}
