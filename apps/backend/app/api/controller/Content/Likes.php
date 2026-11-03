<?php

namespace app\api\controller\Content;

use think\facade\Request;
use app\modules\Content\Likes\Service\Likes as LikesService;
use app\api\ApiResponse;
use app\api\controller\BaseController;
use app\modules\Content\Likes\Transport\Api\LikeResource;

class Likes extends BaseController
{
    public function list()
    {
        $params = $this->validated();
        $type = $params['type'] ?? null;
        $result = LikesService::getUserLikes(request()->auth->uid(), $type);
        return ApiResponse::createOk(LikeResource::collection($result));
    }

    public function unlike($id)
    {
        $params = $this->validated();
        $type = $params['type'] ?? 'card';
        LikesService::unlike($type, (int) $id, request()->auth->uid());
        return ApiResponse::createNoContent();
    }
}
