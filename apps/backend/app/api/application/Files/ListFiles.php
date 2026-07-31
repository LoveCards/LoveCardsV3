<?php

namespace app\api\application\Files;

use app\api\ApiException;

/**
 * 列出文件用例
 *
 * 根据用户身份和权限返回文件列表。
 */
final class ListFiles
{
    private FileRepository $files;

    public function __construct(FileRepository $files)
    {
        $this->files = $files;
    }

    /**
     * 列出文件（带可见范围控制）
     */
    public function execute(array $params, int $userId, bool $canReadAll): array
    {
        return $this->files->paginate($params, $userId, $canReadAll);
    }

    /**
     * 列出用户自己的文件（严格 owner）
     */
    public function executeOwn(array $params, int $userId): array
    {
        if ($userId <= 0) {
            throw ApiException::unauthorized('请先登入');
        }
        return $this->files->paginateOwn($params, $userId);
    }
}
