<?php

namespace app\api\controller\Captcha;

use app\modules\Platform\Captcha\Service\CaptchaManager;
use app\api\ApiResponse;
use app\api\controller\BaseController;
use app\modules\Platform\Captcha\Transport\Api\CaptchaResource;

class Captcha extends BaseController
{
    public function types()
    {
        return ApiResponse::createOk(CaptchaResource::collection(CaptchaManager::types()));
    }

    public function drivers()
    {
        return ApiResponse::createOk(CaptchaResource::collection(CaptchaManager::drivers()));
    }

    public function meta(string $slug)
    {
        return ApiResponse::createOk(CaptchaResource::make(CaptchaManager::meta($slug)));
    }

    public function install()
    {
        return ApiResponse::createOk(CaptchaResource::collection(CaptchaManager::install()));
    }

    public function config()
    {
        return ApiResponse::createOk(CaptchaResource::make(CaptchaManager::config()));
    }
}
