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
        UsersService::updateAny($id, $params, request()->auth->roleIds());
        return ApiResponse::createNoContent();
    }

    public function assignRoles($id)
    {
        $params = $this->validated(null, ['id' => (int) $id]);
        $roles = is_string($params['roles_id']) ? json_decode($params['roles_id'], true) : $params['roles_id'];
        if (!is_array($roles)) throw ApiException::badRequest('角色列表格式错误');
        UsersService::updateAny((int) $id, ['roles_id' => $roles], request()->auth->roleIds());
        return ApiResponse::createNoContent();
    }

    public function delete($id)
    {
        UsersService::deleteAny((int) $id);
        return ApiResponse::createNoContent();
    }
}
