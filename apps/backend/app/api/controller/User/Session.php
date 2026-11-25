<?php

namespace app\api\controller\User;

use app\api\ApiException;
use app\api\controller\BaseController;
use app\modules\Platform\Auth\Application\LoginUser;
use app\modules\Platform\Auth\Application\RegisterUser;
use app\modules\Platform\Auth\Application\Session as SessionService;
use app\modules\Platform\Auth\Application\UserSessions;

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
        $result = $this->registerUser->register($params, $this->session);
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
        $this->session->sendCaptcha($params['account'], (string) request()->ip());
        return $this->respond();
    }

    public function logout()
    {
        $token = request()->header('authorization') ?: request()->header('x-token', '');
        $token = preg_replace('/^Bearer\s+/', '', (string) $token);
        $this->session->logout($token);
        return $this->respond();
    }

    public function listSessions()
    {
        $auth = request()->auth;
        return $this->respond(app(UserSessions::class)->list((int) $auth->uid(), (string) $auth->sessionPublicId()));
    }

    public function revokeSession(string $sessionId)
    {
        $auth = request()->auth;
        app(UserSessions::class)->revoke((int) $auth->uid(), (string) $auth->sessionPublicId(), $sessionId, request()->ip());
        return $this->respond();
    }

    public function revokeOthers()
    {
        $auth = request()->auth;
        app(UserSessions::class)->revokeOthers((int) $auth->uid(), (string) $auth->sessionPublicId(), request()->ip());
        return $this->respond();
    }
}
