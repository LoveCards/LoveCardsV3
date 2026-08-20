<?php

namespace app\api\controller\Storage;

use app\api\ApiResponse;
use app\api\ApiException;
use app\modules\Platform\Files\Application\UploadFile;
use app\modules\Platform\Files\Application\ListFiles;
use app\modules\Platform\Files\Application\GetFile;
use app\modules\Platform\Files\Application\BatchOperateFiles;
use app\modules\Platform\Files\Application\DirectUpload;
use app\api\controller\BaseController;
use app\shared\Transport\Http\ApiResource;
use app\modules\Platform\Files\Transport\Api\DirectUploadResource;
use app\modules\Platform\Files\Transport\Api\FileResource;

class Upload extends BaseController
{
    private UploadFile $uploadFile;
    private ListFiles $listFiles;
    private GetFile $getFile;
    private BatchOperateFiles $batchOperate;
    private DirectUpload $directUpload;

    public function __construct(
        UploadFile $uploadFile,
        ListFiles $listFiles,
        GetFile $getFile,
        BatchOperateFiles $batchOperate,
        DirectUpload $directUpload
    ) {
        parent::__construct();
        $this->uploadFile = $uploadFile;
        $this->listFiles = $listFiles;
        $this->getFile = $getFile;
        $this->batchOperate = $batchOperate;
        $this->directUpload = $directUpload;
    }

    public function upload()
    {
        $file = request()->file('file');
        $params = $this->validated(null, ['file' => $file]);
        $userId = request()->auth->uid();

        $scene = (string) ($params['scene'] ?? 'direct');
        $refType = isset($params['ref_type']) ? (string) $params['ref_type'] : null;
        $refId = isset($params['ref_id']) ? (int) $params['ref_id'] : null;
        $isPublic = (int) ($params['is_public'] ?? 0);

        $result = $this->uploadFile->execute($file, $userId, $scene, $refType, $refId, $isPublic);
        return ApiResponse::createOk(FileResource::make($result));
    }

    public function list()
    {
        $params = $this->validated();

        $userId = request()->auth->uid();
        $canReadAll = request()->auth->grants()->allows('files.read', \app\shared\Authorization\Scope::ALL);

        $result = $this->listFiles->execute($params, $userId, $canReadAll);
        return ApiResponse::createOk(FileResource::paginate($result));
    }

    public function listOwn()
    {
        $userId = request()->auth->uid();
        if ($userId <= 0) {
            throw ApiException::unauthorized('请先登入');
        }
        $params = $this->validated();

        $result = $this->listFiles->executeOwn($params, $userId);
        return ApiResponse::createOk(FileResource::paginate($result));
    }

    public function get($id = 0)
    {
        $fileId = (int) $id;
        $userId = request()->auth->uid();
        $canReadAll = request()->auth->grants()->allows('files.read', \app\shared\Authorization\Scope::ALL);

        $file = $this->getFile->execute($fileId, $userId, $canReadAll);
        if (!$file) {
            throw ApiException::notFound('文件不存在');
        }
        return ApiResponse::createOk(FileResource::make($file));
    }

    public function batch()
    {
        $params = $this->validated();
        $idsParam = $params['ids'];
        $ids = is_string($idsParam) ? json_decode($idsParam, true) : $idsParam;
        $method = (string) $params['method'];

        if (empty($ids) || empty($method)) {
            throw ApiException::badRequest('参数不完整');
        }

        $auth = request()->auth;
        $this->batchOperate->execute($method, $ids, $auth->uid(), $auth->grants(), (string) request()->ip());
        return ApiResponse::createNoContent();
    }

    public function direct()
    {
        $userId = request()->auth->uid();

        $params = $this->validated();
        $filename = (string) $params['filename'];
        $size = (int) $params['size'];
        $mime = (string) $params['mime'];

        $result = $this->directUpload->createPending($filename, $mime, $size, $userId);
        return ApiResponse::createOk(DirectUploadResource::make($result));
    }

    public function confirm($id = 0)
    {
        $recordId = (int) $id;
        $userId = request()->auth->uid();

        $result = $this->directUpload->confirm($recordId, $userId);
        if (!$result) {
            throw ApiException::notFound('确认失败');
        }
        return ApiResponse::createNoContent();
    }

    public function cleanup()
    {
        $params = $this->validated();
        $limit = (int) ($params['limit'] ?? 100);
        $cleaned = $this->directUpload->cleanup($limit);
        return ApiResponse::createOk(ApiResource::make(['cleaned' => count($cleaned)]));
    }

    public function delete($id)
    {
        $auth = request()->auth;
        $this->batchOperate->execute('trash', [(int) $id], $auth->uid(), $auth->grants(), (string) request()->ip());
        return ApiResponse::createNoContent();
    }

    public function purge($id)
    {
        $auth = request()->auth;
        $this->batchOperate->execute('hard_delete', [(int) $id], $auth->uid(), $auth->grants(), (string) request()->ip());
        return ApiResponse::createNoContent();
    }
}
