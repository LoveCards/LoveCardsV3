<?php

namespace app\api\controller\Sender;

use think\facade\Request;

use app\modules\Platform\Sender\Service\SenderManager;
use app\api\ApiException;
use app\api\ApiResponse;
use app\api\controller\BaseController;
use app\modules\Platform\Sender\Transport\Api\SenderResource;

class Sender extends BaseController
{
    public function meta(string $type)
    {
        return ApiResponse::createOk(SenderResource::make(SenderManager::meta($type)));
    }

    public function install()
    {
        return ApiResponse::createOk(SenderResource::collection(SenderManager::install()));
    }

    public function types()
    {
        return ApiResponse::createOk(SenderResource::collection(SenderManager::types()));
    }

    public function channels()
    {
        return ApiResponse::createOk(SenderResource::collection(SenderManager::channels()));
    }

    public function templates()
    {
        return ApiResponse::createOk(SenderResource::collection(SenderManager::templates()));
    }

    public function testChannel()
    {
        $params = $this->validated();
        $channel = $params['channel'] ?? '';
        if (empty($channel)) {
            throw ApiException::badRequest('请指定渠道');
        }

        $to = $params['to'] ?? 'test@example.com';

        return ApiResponse::createOk(SenderResource::make(SenderManager::testChannel($channel, $to)));
    }
}
