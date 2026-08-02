<?php

namespace app\api\infrastructure\Storage\Contract;

interface HasDirectUpload
{
    public function getDirectUploadUrl(): string;

    public function getUploadCredential(
        string $filename,
        string $mime,
        int $size,
        string $path,
        int $expire = 3600
    ): DirectUploadCredential;
}
