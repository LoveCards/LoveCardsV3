<?php

namespace app\api\controller\Rbac;

use think\facade\Request;

use app\modules\Platform\Rbac\Service\Roles as RolesService;
use app\modules\Platform\Rbac\Transport\Api\Roles as RolesValidate;

use app\api\ApiResponse;
use app\api\controller\BaseController;
use app\shared\Transport\Http\ApiResource;
use app\modules\Platform\Rbac\Transport\Api\RoleResource;

class Roles extends BaseController
{
    public function list()
    {
        $params = $this->paramIndex(Request::param());
        $result = RolesService::Index($params);
        return ApiResponse::createOk(RoleResource::paginate($result));
    }

    public function get($id)
    {
        $result = RolesService::Get((int) $id);
        return ApiResponse::createOk(RoleResource::make($result));
    }

    public function create()
    {
        $params = $this->param(RolesValidate::class, RolesValidate::$all_scene['create'], Request::param());
        $id = RolesService::createRole($params);
        return ApiResponse::createOk(ApiResource::make(['id' => $id]));
    }

    public function update($id)
    {
        $params = $this->param(RolesValidate::class, RolesValidate::$all_scene['update'], Request::param());
        $id = (int) $id;
        unset($params['id']);
        RolesService::updateRole($id, $params);
        return ApiResponse::createNoContent();
    }

    public function delete($id)
    {
        RolesService::deleteRoles((int) $id);
        return ApiResponse::createNoContent();
    }

    public function assignCapabilities($id)
    {
        $params = $this->param(RolesValidate::class, RolesValidate::$all_scene['assignCapabilities'], Request::param());
        $roleId = (int) $id;
        $caps = is_string($params['capabilities'])
            ? json_decode($params['capabilities'], true)
            : $params['capabilities'];
        if (!is_array($caps)) {
            throw \app\api\ApiException::badRequest('能力列表格式错误');
        }
        RolesService::assignCapabilities($roleId, $caps);
        return ApiResponse::createNoContent();
    }

    public function getRoleCapabilities($id)
    {
        $result = RolesService::getRoleCapabilities((int) $id);
        return ApiResponse::createOk(ApiResource::make($result));
    }

    public function reseed()
    {
        $result = RolesService::reseed();
        return ApiResponse::createOk(ApiResource::make($result));
    }
}
