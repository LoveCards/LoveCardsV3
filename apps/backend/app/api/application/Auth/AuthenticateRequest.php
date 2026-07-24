<?php

namespace app\api\application\Auth;

use app\api\ApiException;
use app\common\contract\TokenService;

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

        try {
            $data = $this->tokens->verify($token);
            $user = $this->users->findById((int) $data['uid']);

            if ($user === null) {
                throw ApiException::unauthorized(
                    '用户不存在',
                    ApiException::CODE_USER_NOT_FOUND
                );
            }

            $roleIds = $user->roleIds();

            return AuthContext::authenticated(
                $user->id(),
                $user,
                $roleIds,
                $this->capabilities->forRoles($roleIds),
                $data['_new_token'] ?? null
            );
        } catch (\RuntimeException $exception) {
            if (!$this->visitors->isEnabled()) {
                throw $exception;
            }

            return $this->visitorContext();
        } catch (ApiException $exception) {
            if (!$this->visitors->isEnabled()) {
                throw $exception;
            }

            return $this->visitorContext();
        }
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
