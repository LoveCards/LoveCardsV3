<?php

namespace app\api\controller\Content;

use app\modules\Content\Comments\Service\Comments as CommentsService;

use app\api\controller\BaseController;
use app\api\controller\BatchOperateTrait;

class Comments extends BaseController
{
    use BatchOperateTrait;

    protected function getBatchService(): string
    {
        return CommentsService::class;
    }

    public function cardList($id)
    {
        $params = $this->validated();
        $params['where'] = ['aid' => 1, 'pid' => $id];
        $result = CommentsService::listAll($params);
        return $this->respond($result);
    }

    public function list()
    {
        $params = $this->validated();
        $caps = request()->auth->capabilities();
        $result = CommentsService::listAll($params, $caps);
        return $this->respond($result);
    }

    public function create($id)
    {
        $params = $this->validated(null, ['id' => (int) $id]);
        $params['user_id'] = request()->auth->uid();
        $params['post_ip'] = request()->ip();
        $params['status'] = CommentsService::requiresApproval() ? 3 : 0;

        $result = CommentsService::createComment($params);

        return $this->respond([
            'id' => (int) $result['data']->id,
            'status' => (int) $result['data']->status,
        ]);
    }

    public function get($id)
    {
        $result = CommentsService::get((int) $id);
        return $this->respond($result);
    }

    public function update($id)
    {
        $params = $this->validated(null, ['id' => (int) $id]);

        $auth = request()->auth;
        CommentsService::updateComment($params, $auth->uid(), $auth->capabilities());

        return $this->respond();
    }

    public function delete($id)
    {
        $auth = request()->auth;
        CommentsService::deleteComments([(int) $id], $auth->uid(), $auth->capabilities());
        return $this->respond();
    }

    public function listOwn()
    {
        $params = $this->validated();
        $result = CommentsService::newList($params, request()->auth->uid());
        return $this->respond($result);
    }
}
