<?php

namespace app\api\controller\User;

use think\facade\Request;
use think\exception\ValidateException;

use app\api\application\Auth\LoginUser;
use app\api\application\Auth\RegisterUser;
use app\api\service\User\Session as SessionService;
use app\common\service\Config as ConfigService;
use app\api\validate\Users as UsersValidate;

use app\api\ApiResponse;
use app\api\controller\BaseController;

class Session extends BaseController
{
    private $session;
    private $loginUser;
    private $registerUser;

    public function __construct(
        SessionService $session,
        LoginUser $loginUser,
        RegisterUser $registerUser
    )
    {
        parent::__construct();
        $this->session = $session;
        $this->loginUser = $loginUser;
        $this->registerUser = $registerUser;
    }

    public function check()
    {
        return ApiResponse::createOk([]);
    }

    public function login()
    {
        $account = Request::param('account');
        $password = Request::param('password');

        if (empty($account) || empty($password)) {
            return ApiResponse::createBadRequest('参数错误', [
                'account' => empty($account) ? '账号不能为空' : null,
                'password' => empty($password) ? '密码不能为空' : null,
            ]);
        }

        $accountMap = $this->session->resolveAccountType($account);

        try {
            validate(UsersValidate::class)
                ->scene('login')
                ->remove(['email', 'number'])
                ->check([
                    'phone' => $accountMap['phone'],
                    'email' => $accountMap['email'],
                    'password' => $password,
                ]);
        } catch (ValidateException $e) {
            return ApiResponse::createUnauthorized('登录失败', [$e->getError()]);
        }

        $result = $this->loginUser->execute($account, $password);

        return ApiResponse::createOk(['token' => $result['token']]);
    }

    public function register()
    {
        $account = Request::param('account');
        $password = Request::param('password');
        $code = Request::param('code');

        if (empty($account) || empty($password)) {
            return ApiResponse::createBadRequest('参数错误', [
                'account' => empty($account) ? '账号不能为空' : null,
                'password' => empty($password) ? '密码不能为空' : null,
            ]);
        }

        if (ConfigService::get('user.captcha')) {
            if (!$this->session->verifyCaptcha($account, $code)) {
                return ApiResponse::createUnauthorized('注册失败', ['验证码错误']);
            }
        }

        $username = 'USER' . strtoupper(substr(md5($account . $password . time()), 0, 5));
        $number = $this->session->generateNumber();
        $accountMap = $this->session->resolveAccountType($account);

        try {
            validate(UsersValidate::class)
                ->scene('register')
                ->check([
                    'phone' => $accountMap['phone'],
                    'email' => $accountMap['email'],
                    'username' => $username,
                    'password' => $password,
                ]);
        } catch (ValidateException $e) {
            return ApiResponse::createUnauthorized('注册失败', [$e->getError()]);
        }

        $result = $this->registerUser->execute(
            $number,
            $username,
            $accountMap['email'],
            $accountMap['phone'],
            $password
        );

        $this->session->deleteCaptcha($account);

        return ApiResponse::createOk(['token' => $result['token']]);
    }

    public function guest()
    {
        if (!ConfigService::get('core.visitor_mode')) {
            return ApiResponse::createUnauthorized('该站点未开启访客模式');
        }

        $result = $this->session->guest(request()->ip());

        return ApiResponse::createOk(['token' => $result['token']]);
    }

    public function captcha()
    {
        $params = $this->param(
            UsersValidate::class,
            UsersValidate::$all_scene['captcha'],
            request()->param()
        );

        try {
            $this->session->sendCaptcha($params['account']);
        } catch (\RuntimeException $e) {
            return ApiResponse::createBadRequest($e->getMessage());
        } catch (\app\api\ApiException $e) {
            return ApiResponse::createBadRequest($e->getMessage());
        }

        return ApiResponse::createNoContent();
    }

    public function logout()
    {
        return ApiResponse::createNoContent();
    }
}
