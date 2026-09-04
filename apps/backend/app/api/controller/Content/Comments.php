<?php

namespace app\api\controller\Content;

use app\modules\Content\Comments\Service\Comments as CommentsService;
use app\modules\Content\Likes\Service\Likes as LikesService;

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
        $result = CommentsService::listCard((int) $id, $params);
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

    public function getManaged($id)
    {
        return $this->respond(CommentsService::get((int) $id, true));
    }

    public function like($id)
    {
        $likes = LikesService::like('comment', (int) $id, request()->auth->uid(), request()->ip());
        return $this->respond(['likes' => $likes]);
    }

    public function unlike($id)
    {
        LikesService::unlike('comment', (int) $id, request()->auth->uid());
        return $this->respond();
    }

    public function moderate($id)
    {
        $params = $this->validated(null, ['id' => (int) $id]);
        $auth = request()->auth;
        CommentsService::moderateComment($params, $auth->uid(), $auth->grants(), (string) request()->ip(), true);

        return $this->respond();
    }

    public function delete($id)
    {
        $auth = request()->auth;
        CommentsService::deleteComments([(int) $id], $auth->uid(), $auth->grants());
        return $this->respond();
    }

    public function pin($id)
    {
        $params = $this->validated(null, ['id' => (int) $id]);
        $auth = request()->auth;
        CommentsService::pinComment(
            (int) $params['id'],
            (int) $params['comment_id'],
            $auth->uid(),
            $auth->grants(),
            (string) request()->ip()
        );
        return $this->respond();
    }

    public function unpin($id)
    {
        $params = $this->validated(null, ['id' => (int) $id]);
        $auth = request()->auth;
        CommentsService::unpinComment(
            (int) $params['id'],
            $auth->uid(),
            $auth->grants(),
            (string) request()->ip()
        );
        return $this->respond();
    }

    public function pinOwn($id)
    {
        $params = $this->validated(null, ['id' => (int) $id]);
        $auth = request()->auth;
        CommentsService::pinOwnComment(
            (int) $params['id'],
            (int) $params['comment_id'],
            $auth->uid(),
            $auth->grants()
        );
        return $this->respond();
    }

    public function unpinOwn($id)
    {
        $auth = request()->auth;
        CommentsService::unpinOwnComment((int) $id, $auth->uid(), $auth->grants());
        return $this->respond();
    }

    public function deleteManaged($id)
    {
        $auth = request()->auth;
        CommentsService::deleteComments(
            [(int) $id],
            $auth->uid(),
            $auth->grants(),
            (string) request()->ip(),
            true
        );
        return $this->respond();
    }

    public function listOwn()
    {
        $params = $this->validated();
        $result = CommentsService::newList($params, request()->auth->uid());
        return $this->respond($result);
    }
}
