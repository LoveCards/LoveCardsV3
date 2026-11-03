<?php

namespace app\api\controller\User;

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
        return $this->respond(ProfileService::update(request()->auth->uid(), $params, request()->ip()));
    }

    public function password()
    {
        $params = $this->validated();
        ProfileService::changePassword(request()->auth->uid(), $params['current_password'], $params['new_password'], request()->ip());
        return $this->respond();
    }

    public function email()
    {
        $params = $this->validated();
        ProfileService::changeEmail(request()->auth->uid(), $params['current_password'], $params['email'], $params['captcha'], request()->ip());
        return $this->respond();
    }

    public function emailCaptcha()
    {
        $params = $this->validated();
        return $this->respond(ProfileService::sendEmailCaptcha($params['email']));
    }
}
