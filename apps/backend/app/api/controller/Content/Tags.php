<?php

namespace app\api\controller\Content;

use think\facade\Request;

use app\api\service\Content\Tags as TagsService;
use app\api\validate\Tags as TagsValidate;

use app\api\ApiResponse;
use app\api\controller\BaseController;
use app\api\controller\BatchOperateTrait;

class Tags extends BaseController
{
    use BatchOperateTrait;

    protected function getBatchService(): string
    {
        return \app\api\service\Content\Tags::class;
    }

    public function listAll()
    {
        $params = $this->paramIndex(Request::param());
        $caps = request()->auth->capabilities();
        $result = TagsService::listAll($params, $caps);
        return ApiResponse::createOk($result);
    }

    public function list()
    {
        $params = [
            'where' => ['status' => 0]
        ];
        $result = TagsService::noPaginateIndex($params);
        return ApiResponse::createOk($result);
    }

    public function get($id)
    {
        $result = TagsService::get((int) $id);
        return ApiResponse::createOk($result);
    }

    public function create()
    {
        $params = $this->param(TagsValidate::class, TagsValidate::$all_scene['create'], Request::param());
        $params['user_id'] = request()->auth->uid();
        TagsService::createTag($params);
        return ApiResponse::createNoContent();
    }

    public function update($id)
    {
        $params = $this->param(TagsValidate::class, TagsValidate::$all_scene['allUpdate'], Request::param());
        $params['id'] = (int) $id;

        $auth = request()->auth;
        TagsService::updateTag($params, $auth->uid(), $auth->capabilities());

        return ApiResponse::createNoContent();
    }

    public function delete($id)
    {
        $auth = request()->auth;
        TagsService::deleteTags([(int) $id], $auth->uid(), $auth->capabilities());
        return ApiResponse::createNoContent();
    }
}
