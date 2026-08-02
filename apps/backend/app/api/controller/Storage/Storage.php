<?php

namespace app\api\controller\Storage;

use think\facade\Request;
use app\api\ApiResponse;
use app\api\controller\BaseController;
use app\modules\Platform\Storage\Transport\Api\StorageResource;
use app\modules\Platform\Files\Application\ChannelStats;
use app\modules\Platform\Storage\Service\ChannelTester;
use app\modules\Platform\Storage\Service\StorageAdmin;

class Storage extends BaseController
{
    private ChannelStats $channelStats;

    public function __construct(ChannelStats $channelStats)
    {
        parent::__construct();
        $this->channelStats = $channelStats;
    }

    public function meta(string $type)
    {
        $result = StorageAdmin::meta($type);
        if ($result === null) {
            return ApiResponse::createBadRequest('驱动不存在: ' . $type);
        }
        return ApiResponse::createOk(StorageResource::make($result));
    }

    public function install()
    {
        return ApiResponse::createOk(StorageResource::collection(StorageAdmin::install()));
    }

    public function types()
    {
        return ApiResponse::createOk(StorageResource::collection(StorageAdmin::types()));
    }

    public function channels()
    {
        return ApiResponse::createOk(StorageResource::collection(StorageAdmin::channels()));
    }

    public function testChannel($channel = '')
    {
        $channel = $channel ?: Request::param('channel', '');
        if (empty($channel)) {
            return ApiResponse::createBadRequest('请指定渠道');
        }

        try {
            return ApiResponse::createOk(StorageResource::make(ChannelTester::test($channel)));
        } catch (\Exception $e) {
            return ApiResponse::createOk(StorageResource::make([
                'success' => false,
                'message' => $e->getMessage(),
            ]));
        }
    }

    public function channelStats()
    {
        return ApiResponse::createOk(StorageResource::make($this->channelStats->execute()));
    }
}
