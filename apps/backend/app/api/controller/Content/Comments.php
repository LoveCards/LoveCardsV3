<?php

namespace app\api\controller\Content;

use think\facade\Request;

use app\modules\Content\Comments\Transport\Api\Comments as CommentsValidate;
use app\modules\Content\Comments\Service\Comments as CommentsService;

use app\api\ApiResponse;
use app\modules\Content\Comments\Transport\Api\CommentResource;
use app\api\controller\BaseController;
use app\api\controller\BatchOperateTrait;

class Comments extends BaseController
{
    use BatchOperateTrait;

    protected function getBatchService(): string
    {
        return \app\modules\Content\Comments\Service::class;
    }

    public function cardList($id)
    {
        $params = $this->paramIndex(Request::param());
        $params['where'] = ['aid' => 1, 'pid' => $id];
        $result = CommentsService::listAll($params);
        return ApiResponse::createOk(CommentResource::paginate($result));
    }

    public function list()
    {
        $params = $this->paramIndex(Request::param());
        $caps = request()->auth->capabilities();
        $result = CommentsService::listAll($params, $caps);
        return ApiResponse::createOk(CommentResource::paginate($result));
    }

    public function create($id)
    {
        $params = $this->param(CommentsValidate::class, CommentsValidate::$all_scene['create'], Request::param());
        $params['id'] = $id;
        $params['user_id'] = request()->auth->uid();
        $params['post_ip'] = request()->ip();
        $params['status'] = CommentsService::requiresApproval() ? 3 : 0;

        $result = CommentsService::createComment($params);

        return ApiResponse::createCreated(ApiResource::make([
            'id' => (int) $result['data']->id,
            'status' => (int) $result['data']->status,
        ]));
    }

    public function get($id)
    {
        $result = CommentsService::get((int) $id);
        return ApiResponse::createOk(CommentResource::make($result));
    }

    public function update($id)
    {
        $params = $this->param(CommentsValidate::class, CommentsValidate::$all_scene['create'], Request::param());
        $params['id'] = (int) $id;

        $auth = request()->auth;
        CommentsService::updateComment($params, $auth->uid(), $auth->capabilities());

        return ApiResponse::createNoContent();
    }

    public function delete($id)
    {
        $auth = request()->auth;
        CommentsService::deleteComments([(int) $id], $auth->uid(), $auth->capabilities());
        return ApiResponse::createNoContent();
    }

    public function listOwn()
    {
        $params = $this->paramIndex(Request::param());
        $result = CommentsService::newList($params, request()->auth->uid());
        return ApiResponse::createOk(CommentResource::paginate($result));
    }
}
