<?php

namespace app\modules\Platform\Auth\Application;

use app\api\ApiException;
use app\modules\Platform\Auth\Contract\TokenService;
use app\modules\Platform\Auth\Contract\UserRepository;
use app\modules\Platform\Auth\Contract\CapabilityProvider;
use app\modules\Platform\Auth\Public\VisitorPolicy;
use app\modules\Platform\Auth\Public\AuthContext;
use app\modules\Platform\Auth\Public\AuthUser;
use app\modules\Platform\Auth\Public\MissingCredentials;
use app\modules\Platform\User\Public\UserStatus;

final class AuthenticateRequest
{
    private $tokens;
    private $users;
    private $visitors;
    private $capabilities;

    public function __construct(
        TokenService $tokens,
        UserRepository $users,
        VisitorPolicy $visitors,
        CapabilityProvider $capabilities
    ) {
        $this->tokens = $tokens;
        $this->users = $users;
        $this->visitors = $visitors;
        $this->capabilities = $capabilities;
    }

    public function execute(?string $token): AuthContext
    {
        if ($token === null || $token === '') {
            if (!$this->visitors->isEnabled()) {
                throw new MissingCredentials('请先登入');
            }

            return $this->visitorContext();
        }

        $data = $this->tokens->verify($token);
        $user = $this->users->findById((int) $data['uid']);

        if ($user === null) {
            throw ApiException::unauthorized(
                '用户不存在',
                ApiException::CODE_USER_NOT_FOUND
            );
        }

        if (!UserStatus::canAuthenticate($user->status())) {
            throw ApiException::forbidden(
                '您的账户已被封禁或未激活',
                ApiException::CODE_USER_BANNED
            );
        }

        $roleIds = $user->roleIds();

        return AuthContext::authenticated(
            $user->id(),
            $user,
            $roleIds,
            $this->capabilities->forRoles($roleIds),
            $data['_new_token'] ?? null,
            $data['_session_public_id'] ?? null
        );
    }

    private function visitorContext(): AuthContext
    {
        $roleIds = $this->visitors->roleIds();

        return AuthContext::visitor(
            $roleIds,
            $this->capabilities->forRoles($roleIds)
        );
    }
}
