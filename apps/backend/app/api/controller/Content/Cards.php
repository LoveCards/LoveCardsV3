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

    public function mediaAccess($id)
    {
        $auth = request()->auth;
        return $this->respond(\app\modules\Content\Cards\Service\CardMedia::access((int) $id, $auth->uid(), (string) $auth->sessionPublicId()))->header(['Cache-Control' => 'private, no-store']);
    }

    protected function getBatchService(): string
    {
        return CardsService::class;
    }

    public function list()
    {
        $params = $this->validated();
        $auth = request()->auth ?? null;
        $result = CardsService::list($params, $auth ? $auth->capabilities() : [], $auth ? (int) $auth->uid() : 0);
        return $this->respond($result);
    }

    public function listFollowing()
    {
        $params = $this->validated();
        return $this->respond(CardsService::listFollowing($params, (int) request()->auth->uid()));
    }

    public function listAll()
    {
        $params = $this->validated();
        $result = CardsService::listAll($params, request()->auth->capabilities());
        return $this->respond($result);
    }

    public function search()
    {
        $params = $this->validated();
        $auth = request()->auth ?? null;
        $result = CardsService::list($params, $auth ? $auth->capabilities() : [], $auth ? (int) $auth->uid() : 0);
        return $this->respond($result);
    }

    public function listPublicByUser(string $handle)
    {
        $params = $this->validated();
        $auth = request()->auth ?? null;
        return $this->respond(CardsService::listPublicByUser($handle, $params, $auth ? (int) $auth->uid() : 0));
    }

    public function listPublicByUid(string $uid)
    {
        $params = $this->validated();
        $auth = request()->auth ?? null;
        return $this->respond(CardsService::listPublicByUid($uid, $params, $auth ? (int) $auth->uid() : 0));
    }

    public function hotList()
    {
        $auth = request()->auth ?? null;
        $result = CardsService::hotList($auth ? (int) $auth->uid() : 0);
        return $this->respond($result);
    }

    public function get($id)
    {
        $auth = request()->auth ?? null;
        $result = CardsService::get((int) $id, $auth ? $auth->capabilities() : [], $auth ? (int) $auth->uid() : 0);
        return $this->respond($result);
    }

    public function getManaged($id)
    {
        $result = CardsService::get((int) $id, request()->auth->capabilities());
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

        $cardId = CardsService::createCard($params, request()->auth->grants());

        return $this->respond([
            'id' => (int) $cardId,
            'status' => (int) $params['status'],
            'visibility' => (string) ($params['visibility'] ?? 'public'),
        ]);
    }

    public function update($id)
    {
        $params = $this->validated(null, ['id' => (int) $id]);
        $auth = request()->auth;
        CardsService::updateCard($params, $auth->uid(), $auth->grants());

        return $this->respond();
    }

    public function updateManaged($id)
    {
        $params = $this->validated(null, ['id' => (int) $id]);
        $auth = request()->auth;
        CardsService::updateCard($params, $auth->uid(), $auth->grants(), (string) request()->ip(), true);

        return $this->respond();
    }

    public function delete($id)
    {
        $auth = request()->auth;
        CardsService::deleteCards([(int) $id], $auth->uid(), $auth->grants());
        return $this->respond();
    }

    public function deleteManaged($id)
    {
        $auth = request()->auth;
        CardsService::deleteCards(
            [(int) $id],
            $auth->uid(),
            $auth->grants(),
            (string) request()->ip(),
            true
        );
        return $this->respond();
    }

    public function like($id)
    {
        $likes = LikesService::like('card', (int) $id, request()->auth->uid(), request()->ip());
        return $this->respond(['likes' => $likes]);
    }

    public function unlike($id)
    {
        LikesService::unlike('card', (int) $id, request()->auth->uid());
        return $this->respond();
    }

    public function listOwn()
    {
        $params = $this->validated();
        $result = CardsService::listOwn($params, request()->auth->uid());
        return $this->respond($result);
    }

    public function getOwn($id)
    {
        return $this->respond(CardsService::getOwn((int) $id, request()->auth->uid()));
    }
}
