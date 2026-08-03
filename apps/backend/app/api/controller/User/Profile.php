<?php

namespace app\api\controller\User;

use app\api\ApiException;
use app\api\controller\BaseController;
use app\modules\Platform\User\Service\Profile as ProfileService;

class Profile extends BaseController
{
    public function get()
    {
        return $this->respond(ProfileService::get(request()->auth->uid()));
    }

    public function update()
    {
        $params = $this->validated();
        if (!$params) return $this->respond();
        ProfileService::update(request()->auth->uid(), $params);
        return $this->respond();
    }

    public function password()
    {
        $params = $this->validated();
        try {
            ProfileService::changePassword(request()->auth->uid(), $params['password']);
        } catch (ApiException $e) {
            throw ApiException::badRequest('编辑失败', [$e->getMessage()]);
        }
        return $this->respond();
    }

    public function email()
    {
        $params = $this->validated();
        try {
            ProfileService::changeEmail(request()->auth->uid(), $params['email'], $params['captcha'] ?? '');
        } catch (ApiException $e) {
            throw ApiException::badRequest('编辑失败', [$e->getMessage()]);
        }
        return $this->respond();
    }

    public function emailCaptcha()
    {
        $params = $this->validated();
        try {
            ProfileService::sendEmailCaptcha($params['email']);
        } catch (\RuntimeException $e) {
            throw ApiException::badRequest($e->getMessage());
        } catch (ApiException $e) {
            throw ApiException::badRequest($e->getMessage());
        }
        return $this->respond(['expiresIn' => 300]);
    }
}
