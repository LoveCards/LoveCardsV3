<?php

namespace app\api\controller\Content;

use app\modules\Content\Tags\Service\Tags as TagsService;
use app\api\controller\BaseController;
use app\api\controller\BatchOperateTrait;

class Tags extends BaseController
{
    use BatchOperateTrait;

    protected function getBatchService(): string
    {
        return TagsService::class;
    }

    public function listAll()
    {
        $params = $this->validated();
        $caps = request()->auth->capabilities();
        $result = TagsService::listAll($params, $caps);
        return $this->respond($result);
    }

    public function listOwn()
    {
        $params = $this->validated();
        $result = TagsService::listOwn($params, request()->auth->uid());
        return $this->respond($result);
    }

    public function list()
    {
        $params = [
            'where' => ['status' => 0]
        ];
        $result = TagsService::noPaginateIndex($params);
        return $this->respond($result);
    }

    public function get($id)
    {
        // 公开详情：仅已发布标签可见，待审核/封禁/隐藏标签对外等同不存在。
        $result = TagsService::get((int) $id, true);
        return $this->respond($result);
    }

    public function getManaged($id)
    {
        $result = TagsService::get((int) $id);
        return $this->respond($result);
    }

    public function policy()
    {
        return $this->respond(TagsService::creationPolicy());
    }

    public function create()
    {
        $params = $this->validated();
        $params['user_id'] = request()->auth->uid();
        return $this->respond(TagsService::createTag($params, request()->auth->grants()));
    }

    public function update($id)
    {
        $params = $this->validated(null, ['id' => (int) $id]);
        $auth = request()->auth;
        TagsService::updateTag($params, $auth->uid(), $auth->grants());

        return $this->respond();
    }

    public function updateManaged($id)
    {
        $params = $this->validated(null, ['id' => (int) $id]);
        $auth = request()->auth;
        TagsService::updateTag($params, $auth->uid(), $auth->grants(), (string) request()->ip(), true);

        return $this->respond();
    }

    public function delete($id)
    {
        $auth = request()->auth;
        TagsService::deleteTags([(int) $id], $auth->uid(), $auth->grants());
        return $this->respond();
    }

    public function deleteManaged($id)
    {
        $auth = request()->auth;
        TagsService::deleteTags(
            [(int) $id],
            $auth->uid(),
            $auth->grants(),
            (string) request()->ip(),
            true
        );
        return $this->respond();
    }
}
