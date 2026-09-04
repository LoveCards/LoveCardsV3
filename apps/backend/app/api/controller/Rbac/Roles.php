<?php

namespace app\api\controller\Rbac;

use app\modules\Platform\Rbac\Service\Roles as RolesService;

use app\api\ApiResponse;
use app\api\controller\BaseController;
use app\shared\Transport\Http\ApiResource;
use app\modules\Platform\Rbac\Transport\Api\RoleResource;
use app\modules\Platform\Rbac\Transport\Api\GrantResource;
use app\api\controller\BatchOperateTrait;

class Roles extends BaseController
{
    use BatchOperateTrait;

    protected function getBatchService(): string
    {
        return RolesService::class;
    }

    public function batch()
    {
        $params = $this->validated();
        $auth = request()->auth;
        RolesService::batchOperate(
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
        $result = RolesService::Index($params, request()->auth->capabilities());
        return ApiResponse::createOk(RoleResource::paginate($result));
    }

    public function get($id)
    {
        $result = RolesService::Get((int) $id);
        return ApiResponse::createOk(RoleResource::make($result));
    }

    public function create()
    {
        $params = $this->validated();
        $id = RolesService::createRole($params, request()->auth->uid(), (string) request()->ip());
        return ApiResponse::createOk(ApiResource::make(['id' => $id]));
    }

    public function update($id)
    {
        $id = (int) $id;
        $params = $this->validated(null, ['id' => $id]);
        unset($params['id']);
        $auth = request()->auth;
        RolesService::updateRole($id, $params, $auth->uid(), $auth->roleIds(), $auth->grants(), (string) request()->ip());
        return ApiResponse::createNoContent();
    }

    public function delete($id)
    {
        $auth = request()->auth;
        RolesService::deleteRoles((int) $id, $auth->uid(), $auth->roleIds(), $auth->grants(), (string) request()->ip());
        return ApiResponse::createNoContent();
    }

    public function assignGrants($id)
    {
        $roleId = (int) $id;
        $params = $this->validated(null, ['id' => $roleId]);
        $grants = is_string($params['grants'])
            ? json_decode($params['grants'], true)
            : $params['grants'];
        if (!is_array($grants)) {
            throw \app\api\ApiException::badRequest('能力列表格式错误');
        }
        $auth = request()->auth;
        RolesService::assignCapabilities($roleId, $grants, $auth->uid(), $auth->roleIds(), $auth->grants(), (string) request()->ip());
        return ApiResponse::createNoContent();
    }

    public function getRoleGrants($id)
    {
        $result = RolesService::getRoleCapabilities((int) $id);
        return ApiResponse::createOk(GrantResource::collection($result));
    }

    public function reseed()
    {
        $auth = request()->auth;
        $result = RolesService::reseed(
            $auth->uid(),
            $auth->roleIds(),
            $auth->grants(),
            (string) request()->ip()
        );
        return ApiResponse::createOk(ApiResource::make($result));
    }
}
