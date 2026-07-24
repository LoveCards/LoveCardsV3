<?php

namespace app\api\middleware;

use app\api\application\Auth\AuthContext;
use app\common\service\Config as ConfigService;
use app\api\service\User\Users as UsersService;
use app\api\service\Rbac\RBAC;
use app\api\ApiResponse;
use app\common\contract\TokenService;

class JwtAuthCheck
{
    private $tokens;

    public function __construct(TokenService $tokens)
    {
        $this->tokens = $tokens;
    }

    public function handle($request, \Closure $next)
    {
        $token = $request->header('authorization');

        if ($token != null) {
            $token = preg_replace('/^Bearer\s+/', '', $token);
            try {
                $data = $this->tokens->verify($token);
                $uid = $data['uid'];
                $user = UsersService::Get($uid);

                if (!$user || !$user->id) {
                    throw \app\api\ApiException::unauthorized('用户不存在', \app\api\ApiException::CODE_USER_NOT_FOUND);
                }

                $roleIds = is_array($user->roles_id) ? $user->roles_id : (json_decode($user->roles_id, true) ?: []);
                $this->attachContext($request, AuthContext::authenticated(
                    (int) $uid,
                    $user,
                    $roleIds,
                    RBAC::getUserCapabilities($roleIds),
                    $data['_new_token'] ?? null
                ));
            } catch (\RuntimeException $e) {
                $apiEx = \app\api\ApiException::unauthorized($e->getMessage());
                if (!ConfigService::get('core.visitor_mode')) {
                    return $apiEx->exceptionHandle();
                }
                $this->setVisitor($request);
            } catch (\app\api\ApiException $e) {
                if (!ConfigService::get('core.visitor_mode')) {
                    return $e->exceptionHandle();
                }
                $this->setVisitor($request);
            }
        } else {
            if (!ConfigService::get('core.visitor_mode')) {
                return ApiResponse::createUnauthorized('请先登入');
            }
            $this->setVisitor($request);
        }

        $response = $next($request);

        if ($request->auth->renewedToken() !== null) {
            $response->header('X-New-Token', $request->auth->renewedToken());
        }

        return $response;
    }

    private function setVisitor($request): void
    {
        $roleIds = [config('system.system_roles.guest')];
        $this->attachContext($request, AuthContext::visitor(
            $roleIds,
            RBAC::getUserCapabilities($roleIds)
        ));
    }

    /**
     * 旧请求字段在调用者迁移到 request()->auth 后删除。
     */
    private function attachContext($request, AuthContext $context): void
    {
        $request->auth = $context;
        $request->uid = $context->uid();
        $request->user = $context->user();
        $request->rolesId = $context->roleIds();
        $request->caps = $context->capabilities();
        $request->newToken = $context->renewedToken();
    }
}
