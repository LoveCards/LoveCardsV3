<?php

namespace app\api\controller\Rbac;

use app\modules\Platform\Rbac\Service\RBAC;
use app\api\ApiResponse;
use app\api\controller\BaseController;
use app\modules\Platform\Rbac\Transport\Api\PermissionResource;

class Permissions extends BaseController
{
    /**
     * 获取全量能力列表（capability 字符串 + 描述）
     */
    public function all()
    {
        $allCaps = \app\modules\Platform\Rbac\Domain\CapabilityCatalog::canonical();
        $result = [];
        foreach ($allCaps as $cap => $_desc) {
            foreach (\app\modules\Platform\Rbac\Domain\CapabilityCatalog::scopesFor($cap) as $scope) {
                $result[] = [
                    'capability' => $cap,
                    'scope' => $scope,
                    'description' => \app\modules\Platform\Rbac\Domain\CapabilityCatalog::descriptionFor($cap, $scope),
                ];
            }
        }
        return ApiResponse::createOk(PermissionResource::collection($result));
    }

    /**
     * 分页搜索能力列表
     */
    public function list()
    {
        $allCaps = \app\modules\Platform\Rbac\Domain\CapabilityCatalog::canonical();
        $items = [];
        foreach ($allCaps as $cap => $_desc) {
            foreach (\app\modules\Platform\Rbac\Domain\CapabilityCatalog::scopesFor($cap) as $scope) {
                $items[] = [
                    'capability' => $cap,
                    'scope' => $scope,
                    'description' => \app\modules\Platform\Rbac\Domain\CapabilityCatalog::descriptionFor($cap, $scope),
                ];
            }
        }

        $params = $this->validated();
        $search = $params['search_value'] ?? '';
        $page = $params['page'] ?? 1;
        $listRows = $params['list_rows'] ?? 15;

        if (!empty($search)) {
            $items = array_filter($items, function ($item) use ($search) {
                return stripos($item['capability'], $search) !== false
                    || stripos($item['description'], $search) !== false;
            });
            $items = array_values($items);
        }

        $total = count($items);
        $lastPage = max(1, ceil($total / $listRows));
        $page = min($page, $lastPage);
        $data = array_slice($items, ($page - 1) * $listRows, $listRows);

        return ApiResponse::createOk(PermissionResource::paginate([
            'current_page' => $page,
            'last_page'    => $lastPage,
            'data'         => $data,
            'total'        => $total,
        ]));
    }
}
