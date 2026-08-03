<?php

namespace app\api\controller\Content;

use app\modules\Content\Cards\Service\Cards as CardsService;
use app\modules\Content\Likes\Service\Likes as LikesService;
use app\modules\Content\Comments\Service\Comments as CommentsService;
use app\modules\Content\Cards\Domain\PublicationStatus;

use app\api\controller\BaseController;
use app\api\controller\BatchOperateTrait;

class Cards extends BaseController
{
    use BatchOperateTrait;

    protected function getBatchService(): string
    {
        return CardsService::class;
    }

    public function list()
    {
        $params = $this->validated();
        $auth = request()->auth ?? null;
        $result = CardsService::list($params, $auth ? $auth->capabilities() : []);
        return $this->respond($result);
    }

    public function search()
    {
        $params = $this->validated();
        $auth = request()->auth ?? null;
        $result = CardsService::list($params, $auth ? $auth->capabilities() : []);
        return $this->respond($result);
    }

    public function hotList()
    {
        $result = CardsService::hotList();
        return $this->respond($result);
    }

    public function get($id)
    {
        $auth = request()->auth ?? null;
        $result = CardsService::get((int) $id, $auth ? $auth->capabilities() : []);
        return $this->respond($result);
    }

    public function create()
    {
        $params = $this->validated();

        $params['user_id'] = request()->auth->uid();
        $params['post_ip'] = request()->ip();
        $params['status'] = CardsService::requiresApproval()
            ? PublicationStatus::pendingApproval()
            : PublicationStatus::visible();

        $cardId = CardsService::createCard($params);

        return $this->respond([
            'id' => (int) $cardId,
            'status' => (int) $params['status'],
        ]);
    }

    public function update($id)
    {
        $params = $this->validated(null, ['id' => (int) $id]);

        $auth = request()->auth;
        CardsService::updateCard($params, $auth->uid(), $auth->capabilities());

        return $this->respond();
    }

    public function delete($id)
    {
        $auth = request()->auth;
        CardsService::deleteCards([(int) $id], $auth->uid(), $auth->capabilities());
        return $this->respond();
    }

    public function like($id)
    {
        $likes = LikesService::like('card', (int) $id, request()->auth->uid(), request()->ip());
        return $this->respond(['likes' => $likes]);
    }

    public function listOwn()
    {
        $params = $this->validated();
        $result = CardsService::listOwn($params, request()->auth->uid());
        return $this->respond($result);
    }
}
