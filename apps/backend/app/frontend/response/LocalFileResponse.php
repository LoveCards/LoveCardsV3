<?php
declare(strict_types=1);

namespace app\frontend\response;

use think\Response;

/** Streams a previously authorized local media file without exposing its physical path. */
final class LocalFileResponse extends Response
{
    private int $offset = 0;
    private int $length = 0;

    public function __construct(
        private string $path,
        string $mimeType,
        string $etag,
        private bool $headOnly = false,
        ?string $rangeHeader = null
    ) {
        if (!is_file($path) || is_link($path)) throw new \RuntimeException('本地媒体文件不可读取');
        if (preg_match('#^[a-z0-9][a-z0-9.+-]*/[a-z0-9][a-z0-9.+_-]*$#i', $mimeType) !== 1) {
            $mimeType = 'application/octet-stream';
        }
        $size = filesize($path);
        if ($size === false) throw new \RuntimeException('无法读取本地媒体文件大小');
        $range = $this->parseRange($rangeHeader, (int) $size);
        if ($range === false) {
            $this->init('', 416);
            $this->header([
                'Content-Range' => 'bytes */' . $size,
                'Content-Length' => '0',
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'public, max-age=300',
                'ETag' => $etag,
                'X-Content-Type-Options' => 'nosniff',
            ]);
            return;
        }
        [$this->offset, $end] = $range ?? [0, max(0, (int) $size - 1)];
        $this->length = $size === 0 ? 0 : $end - $this->offset + 1;
        $partial = $range !== null;
        $this->init('', $partial ? 206 : 200);
        $headers = [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) $this->length,
            'Content-Disposition' => 'inline',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=300',
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
        ];
        if ($partial) $headers['Content-Range'] = "bytes {$this->offset}-{$end}/{$size}";
        $this->header($headers);
    }

    protected function output($data)
    {
        return '';
    }

    protected function sendData(string $data): void
    {
        if ($this->headOnly || $this->length === 0 || $this->getCode() === 416) return;
        $handle = fopen($this->path, 'rb');
        if ($handle === false) throw new \RuntimeException('无法打开本地媒体文件');
        try {
            if ($this->offset > 0 && fseek($handle, $this->offset) !== 0) throw new \RuntimeException('无法定位本地媒体范围');
            $remaining = $this->length;
            while ($remaining > 0 && !feof($handle)) {
                $chunk = fread($handle, min(1048576, $remaining));
                if ($chunk === false) throw new \RuntimeException('读取本地媒体文件失败');
                echo $chunk;
                $remaining -= strlen($chunk);
            }
        } finally {
            fclose($handle);
        }
    }

    /** @return array{int,int}|null|false */
    private function parseRange(?string $header, int $size): array|null|false
    {
        $header = trim((string) $header);
        if ($header === '') return null;
        if ($size < 1 || preg_match('/^bytes=(\d*)-(\d*)$/', $header, $matches) !== 1) return false;
        if ($matches[1] === '' && $matches[2] === '') return false;
        if ($matches[1] === '') {
            $suffix = (int) $matches[2];
            if ($suffix < 1) return false;
            return [max(0, $size - $suffix), $size - 1];
        }
        $start = (int) $matches[1];
        if ($start >= $size) return false;
        $end = $matches[2] === '' ? $size - 1 : min((int) $matches[2], $size - 1);
        if ($end < $start) return false;
        return [$start, $end];
    }
}
