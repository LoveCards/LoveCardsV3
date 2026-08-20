<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Infrastructure;

final class ReleaseArchiveLimits
{
    public function __construct(
        private int $zipBytes = 536870912,
        private int $entries = 20000,
        private int $fileBytes = 134217728,
        private int $unpackedBytes = 1073741824,
        private int $manifestBytes = 4194304
    ) {
        foreach ([$zipBytes, $entries, $fileBytes, $unpackedBytes, $manifestBytes] as $value) {
            if ($value < 1) throw new \InvalidArgumentException('Release Archive 限制必须为正数');
        }
        if ($fileBytes > $unpackedBytes || $manifestBytes > $fileBytes) {
            throw new \InvalidArgumentException('Release Archive 限制顺序不合法');
        }
    }

    public static function configured(): self
    {
        return new self(
            (int) config('lifecycle.maximum_release_zip_bytes', 536870912),
            (int) config('lifecycle.maximum_release_entries', 20000),
            (int) config('lifecycle.maximum_release_file_bytes', 134217728),
            (int) config('lifecycle.maximum_release_unpacked_bytes', 1073741824),
            (int) config('lifecycle.maximum_release_manifest_bytes', 4194304)
        );
    }

    public function zipBytes(): int { return $this->zipBytes; }
    public function entries(): int { return $this->entries; }
    public function fileBytes(): int { return $this->fileBytes; }
    public function unpackedBytes(): int { return $this->unpackedBytes; }
    public function manifestBytes(): int { return $this->manifestBytes; }
}
