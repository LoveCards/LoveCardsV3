<?php

namespace app\api\controller\User;

use app\api\ApiException;
use app\api\controller\BaseController;
use app\modules\Platform\Auth\Application\LoginUser;
use app\modules\Platform\Auth\Application\RegisterUser;
use app\modules\Platform\Auth\Application\Session as SessionService;

class Session extends BaseController
{
    private $session;
    private $loginUser;
    private $registerUser;

    public function __construct(SessionService $session, LoginUser $loginUser, RegisterUser $registerUser)
    {
        parent::__construct();
        $this->session = $session;
        $this->loginUser = $loginUser;
        $this->registerUser = $registerUser;
    }

    public function check()
    {
        return $this->respond([]);
    }

    public function login()
    {
        $params = $this->validated();
        $result = $this->loginUser->execute($params['account'], $params['password']);
        return $this->respond($result);
    }

    public function register()
    {
        $params = $this->validated();
        $account = $params['account'];
        if ($this->session->captchaEnabled() && !$this->session->verifyCaptcha($account, $params['code'] ?? '')) {
            throw ApiException::unauthorized('注册失败', ['验证码错误']);
        }

        $username = 'USER' . strtoupper(substr(md5($account . $params['password'] . time()), 0, 5));
        $number = $this->session->generateNumber();
        $accountMap = $this->session->resolveAccountType($account);
        $result = $this->registerUser->execute(
            $number,
            $username,
            $accountMap['email'],
            $accountMap['phone'],
            $params['password']
        );
        $this->session->deleteCaptcha($account);
        return $this->respond($result);
    }

    public function guest()
    {
        if (!$this->session->visitorModeEnabled()) {
            throw ApiException::unauthorized('该站点未开启访客模式');
        }
        return $this->respond($this->session->guest(request()->ip()));
    }

    public function captcha()
    {
        $params = $this->validated();
        try {
            $this->session->sendCaptcha($params['account']);
        } catch (\RuntimeException $e) {
            throw ApiException::badRequest($e->getMessage());
        }
        return $this->respond();
    }

    public function logout()
    {
        return $this->respond();
    }
}
