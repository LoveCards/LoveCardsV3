<?php

namespace app\api\controller\Storage;

use think\facade\Request;
use app\api\ApiResponse;
use app\api\ApiException;
use app\api\application\Files\UploadFile;
use app\api\application\Files\ListFiles;
use app\api\application\Files\GetFile;
use app\api\application\Files\BatchOperateFiles;
use app\api\application\Files\DirectUpload;
use app\api\validate\Files as FilesValidate;
use app\api\controller\BaseController;

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
        if (empty($file)) {
            throw ApiException::badRequest('请提交文件');
        }

        $userId = request()->auth->uid();

        $scene = Request::param('scene', 'direct');
        $refType = Request::param('ref_type', null);
        $refId = Request::param('ref_id', null);
        $isPublic = (int) Request::param('is_public', 0);

        $validateData = ['scene' => $scene, 'is_public' => $isPublic];
        if ($refType !== null) $validateData['ref_type'] = $refType;
        if ($refId !== null) $validateData['ref_id'] = $refId;

        $validate = new FilesValidate();
        if (!$validate->check($validateData)) {
            throw ApiException::badRequest($validate->getError());
        }

        $result = $this->uploadFile->execute($file, $userId, $scene, $refType, $refId, $isPublic);
        return ApiResponse::createOk($result);
    }

    public function list()
    {
        $params = $this->paramIndex(Request::param());

        $params['show_deleted'] = Request::param('show_deleted', 0);
        $params['status'] = Request::param('status', null);
        $params['upload_status'] = Request::param('upload_status', null);
        $params['scene'] = Request::param('scene', null);

        $userId = request()->auth->uid();
        $canReadAll = request()->auth->hasAnyCapability(['files.read.all']);

        $result = $this->listFiles->execute($params, $userId, $canReadAll);
        return ApiResponse::createOk($result);
    }

    public function listOwn()
    {
        $userId = request()->auth->uid();
        if ($userId <= 0) {
            throw ApiException::unauthorized('请先登入');
        }
        $params = $this->paramIndex(Request::param());
        $params['status'] = Request::param('status', null);
        $params['upload_status'] = Request::param('upload_status', null);
        $params['scene'] = Request::param('scene', null);
        $params['ref_type'] = Request::param('ref_type', null);
        $params['ref_id'] = Request::param('ref_id', null);

        $result = $this->listFiles->executeOwn($params, $userId);
        return ApiResponse::createOk($result);
    }

    public function get($id = 0)
    {
        $fileId = (int) ($id ?: Request::param('id', 0));
        $userId = request()->auth->uid();
        $canReadAll = request()->auth->hasAnyCapability(['files.read.all']);

        $file = $this->getFile->execute($fileId, $userId, $canReadAll);
        if (!$file) {
            throw ApiException::notFound('文件不存在');
        }
        return ApiResponse::createOk($file);
    }

    public function batch()
    {
        $idsParam = Request::param('ids', '[]');
        $ids = is_string($idsParam) ? json_decode($idsParam, true) : $idsParam;
        $method = Request::param('method', '');

        $validate = new FilesValidate();
        if (!$validate->check(['method' => $method])) {
            throw ApiException::badRequest($validate->getError());
        }

        if (empty($ids) || empty($method)) {
            throw ApiException::badRequest('参数不完整');
        }

        $auth = request()->auth;
        $this->batchOperate->execute($method, $ids, $auth->uid(), $auth->capabilities());
        return ApiResponse::createNoContent();
    }

    public function direct()
    {
        $userId = request()->auth->uid();

        $filename = Request::param('filename', '');
        $size = (int) Request::param('size', 0);
        $mime = Request::param('mime', '');

        $result = $this->directUpload->createPending($filename, $mime, $size, $userId);
        return ApiResponse::createOk($result);
    }

    public function confirm($id = 0)
    {
        $recordId = (int) ($id ?: Request::param('record_id', 0));
        $userId = request()->auth->uid();

        $result = $this->directUpload->confirm($recordId, $userId);
        if (!$result) {
            throw ApiException::notFound('确认失败');
        }
        return ApiResponse::createNoContent();
    }

    public function cleanup()
    {
        $limit = (int) Request::param('limit', 100);
        $cleaned = $this->directUpload->cleanup($limit);
        return ApiResponse::createOk(['cleaned' => count($cleaned)]);
    }

    public function allDelete($id)
    {
        $auth = request()->auth;
        $this->batchOperate->execute('hard_delete', [(int) $id], $auth->uid(), $auth->capabilities());
        return ApiResponse::createNoContent();
    }
}
