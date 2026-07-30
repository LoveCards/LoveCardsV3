<?php

namespace app\api\application\Files;

/**
 * 获取单个文件用例
 */
final class GetFile
{
    private FileRepository $files;

    public function __construct(FileRepository $files)
    {
        $this->files = $files;
    }

    /**
     * 获取文件详情（带可见范围控制）
     */
    public function execute(int $fileId, int $userId, bool $canReadAll): ?array
    {
        return $this->files->findByIdVisible($fileId, $userId, $canReadAll);
    }
}
