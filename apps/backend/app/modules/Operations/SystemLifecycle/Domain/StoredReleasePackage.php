<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Domain;

final class StoredReleasePackage
{
    public function __construct(
        private string $path,
        private string $checksum,
        private int $sizeBytes
    ) {
        if ($path === '' || strpos($path, "\0") !== false || !self::isAbsolute($path)) {
            throw new \InvalidArgumentException('Release Package 路径不合法');
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $checksum) || $sizeBytes < 1) {
            throw new \InvalidArgumentException('Release Package 校验信息不合法');
        }
    }

    /** @param array<string, mixed> $data */
    public static function restore(array $data): self
    {
        return new self(
            (string) ($data['path'] ?? ''),
            (string) ($data['checksum'] ?? ''),
            (int) ($data['size_bytes'] ?? 0)
        );
    }

    public function path(): string { return $this->path; }
    public function checksum(): string { return $this->checksum; }
    public function sizeBytes(): int { return $this->sizeBytes; }

    /** @return array{path:string,checksum:string,size_bytes:int} */
    public function toArray(): array
    {
        return ['path' => $this->path, 'checksum' => $this->checksum, 'size_bytes' => $this->sizeBytes];
    }

    private static function isAbsolute(string $path): bool
    {
        if ($path[0] === '/') return true;
        if (strlen($path) >= 3 && ctype_alpha($path[0]) && $path[1] === ':') {
            return $path[2] === '\\' || $path[2] === '/';
        }
        return str_starts_with($path, '\\\\');
    }
}
