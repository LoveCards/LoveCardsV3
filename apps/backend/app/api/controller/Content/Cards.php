<?php

namespace app\api\controller\Content;

use think\facade\Request;

use app\api\validate\Cards as CardsValidate;
use app\api\validate\Comments as CommentsValidate;

use app\api\service\Content\Cards as CardsService;
use app\api\service\Content\Likes as LikesService;
use app\api\service\Content\Comments as CommentsService;
use app\api\service\Content\Domain\PublicationStatus;

use app\api\ApiResponse;
use app\api\resource\ApiResource;
use app\api\resource\CardResource;
use app\api\controller\BaseController;
use app\api\controller\BatchOperateTrait;

class Cards extends BaseController
{
    use BatchOperateTrait;

    protected function getBatchService(): string
    {
        return \app\api\service\Content\Cards::class;
    }

    public function list()
    {
        $params = $this->paramIndex(Request::param());
        $auth = request()->auth ?? null;
        $result = CardsService::list($params, $auth ? $auth->capabilities() : []);
        return ApiResponse::createOk(CardResource::paginate($result));
    }

    public function search()
    {
        $params = $this->paramIndex(Request::param());
        $auth = request()->auth ?? null;
        $result = CardsService::list($params, $auth ? $auth->capabilities() : []);
        return ApiResponse::createOk(CardResource::paginate($result));
    }

    public function hotList()
    {
        $result = CardsService::hotList();
        return ApiResponse::createOk(CardResource::collection($result));
    }

    public function get($id)
    {
        $auth = request()->auth ?? null;
        $result = CardsService::get((int) $id, $auth ? $auth->capabilities() : []);
        return ApiResponse::createOk(CardResource::make($result));
    }

    public function create()
    {
        $params = $this->param(CardsValidate::class, CardsValidate::$all_scene['create'], Request::param());

        $params['user_id'] = request()->auth->uid();
        $params['post_ip'] = request()->ip();
        $params['status'] = CardsService::requiresApproval()
            ? PublicationStatus::pendingApproval()
            : PublicationStatus::visible();

        $cardId = CardsService::createCard($params);

        if (CardsService::requiresApproval()) {
            return ApiResponse::createCreated();
        }
        return ApiResponse::createOk(ApiResource::make(['id' => $cardId]));
    }

    public function update($id)
    {
        $params = $this->param(CardsValidate::class, CardsValidate::$all_scene['create'], Request::param());
        $params['id'] = (int) $id;

        $auth = request()->auth;
        CardsService::updateCard($params, $auth->uid(), $auth->capabilities());

        return ApiResponse::createNoContent();
    }

    public function delete($id)
    {
        $auth = request()->auth;
        CardsService::deleteCards([(int) $id], $auth->uid(), $auth->capabilities());
        return ApiResponse::createNoContent();
    }

    public function like($id)
    {
        $likes = LikesService::like('card', (int) $id, request()->auth->uid(), request()->ip());
        return ApiResponse::createOk(ApiResource::make(['likes' => $likes]));
    }

    public function listOwn()
    {
        $params = $this->paramIndex(Request::param());
        $result = CardsService::listOwn($params, request()->auth->uid());
        return ApiResponse::createOk(CardResource::paginate($result));
    }
}
