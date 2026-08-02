<?php

namespace app\modules\Platform\Storage\Infrastructure\Driver;

use think\file\UploadedFile;
use app\modules\Platform\Storage\Contract\StorageResult;

interface DriverInterface
{
    public function getType(): string;

    public function upload(UploadedFile $file, string $path): StorageResult;

    public function delete(string $driverPath): bool;

    public function getUrl(string $driverPath): string;

    public static function meta(): array;
}
