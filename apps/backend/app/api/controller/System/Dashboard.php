<?php

namespace app\api\controller\System;

use app\modules\Operations\Dashboard\Service\Dashboard as DashboardService;
use app\api\ApiResponse;
use app\api\controller\BaseController;
use app\modules\Operations\Dashboard\Transport\Api\DashboardResource;

class Dashboard extends BaseController
{
    public function index()
    {
        $result = DashboardService::index();
        return ApiResponse::createOk(DashboardResource::make($result));
    }
}
