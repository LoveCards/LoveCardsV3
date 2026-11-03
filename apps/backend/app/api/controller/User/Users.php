<?php

namespace app\api\controller\User;

use app\api\ApiException;
use app\api\ApiResponse;
use app\api\controller\BaseController;
use app\api\controller\BatchOperateTrait;
use app\modules\Platform\User\Service\Users as UsersService;
use app\modules\Platform\User\Transport\Api\UserResource;

final class Users extends BaseController
{
    use BatchOperateTrait;

    protected function getBatchService(): string { return UsersService::class; }

    /** Return server-filtered candidates for the authenticated @ mention helper. */
    public function searchMentionCandidates()
    {
        $params = $this->validated();
        $result = UsersService::searchMentionCandidates($params, request()->auth->uid());
        return $this->respond($result);
    }

    public function batch()
    {
        $params = $this->validated();
        $auth = request()->auth;
        UsersService::batchOperate(
            $params['method'],
            $params['ids'],
            $auth->uid(),
            $auth->grants(),
            $auth->roleIds(),
            (string) request()->ip()
        );
        return $this->respond();
    }

    public function list()
    {
        $params = $this->validated();
        return ApiResponse::createOk(UserResource::paginate(
            UsersService::listAll($params, request()->auth->capabilities())
        ));
    }

    public function get($id)
    {
        return ApiResponse::createOk(UserResource::make(UsersService::Get((int) $id)));
    }

    public function update($id)
    {
        $id = (int) $id;
        $params = $this->validated(null, ['id' => $id]);
        unset($params['id']);
        $auth = request()->auth;
        UsersService::updateAny($id, $params, $auth->uid(), $auth->roleIds(), $auth->grants(), (string) request()->ip());
        return ApiResponse::createNoContent();
    }

    public function assignRoles($id)
    {
        $params = $this->validated(null, ['id' => (int) $id]);
        $roles = is_string($params['roles_id']) ? json_decode($params['roles_id'], true) : $params['roles_id'];
        if (!is_array($roles)) throw ApiException::badRequest('角色列表格式错误');
        $auth = request()->auth;
        UsersService::updateAny((int) $id, ['roles_id' => $roles], $auth->uid(), $auth->roleIds(), $auth->grants(), (string) request()->ip());
        return ApiResponse::createNoContent();
    }

    public function delete($id)
    {
        $auth = request()->auth;
        UsersService::deleteAny((int) $id, $auth->uid(), $auth->roleIds(), $auth->grants(), (string) request()->ip());
        return ApiResponse::createNoContent();
    }
}
