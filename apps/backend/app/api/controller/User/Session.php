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
        return $this->respond(request()->auth);
    }

    public function policy()
    {
        return $this->respond($this->session->policy());
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
        if ($this->session->humanVerificationEnabled() && !$this->session->verifyHumanVerification($params)) {
            throw ApiException::unauthorized('人机验证失败');
        }
        $account = $params['account'];
        if ($this->session->registrationEmailVerificationRequired() && !$this->session->captchaEnabled()) {
            throw ApiException::badRequest('注册验证码服务未启用', ApiException::CODE_PARAM_INVALID);
        }
        if ($this->session->registrationEmailVerificationRequired() && !$this->session->verifyCaptcha($account, $params['code'] ?? '')) {
            throw ApiException::unauthorized('注册失败', ['验证码错误']);
        }

        $accountMap = $this->session->resolveAccountType($account);
        $number = $accountMap['number'] !== '' ? $accountMap['number'] : $this->session->generateNumber();
        $username = $accountMap['number'] !== ''
            ? $account
            : 'USER' . strtoupper(substr(md5($account . $params['password'] . time()), 0, 5));
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
        $token = request()->header('authorization') ?: request()->header('x-token', '');
        $token = preg_replace('/^Bearer\s+/', '', (string) $token);
        $this->session->logout($token);
        return $this->respond();
    }
}
