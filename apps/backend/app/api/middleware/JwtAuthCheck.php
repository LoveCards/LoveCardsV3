<?php

namespace app\api\middleware;

use app\api\application\Auth\AuthContext;
use app\api\application\Auth\AuthenticateRequest;
use app\api\application\Auth\MissingCredentials;
use app\api\ApiResponse;

class JwtAuthCheck
{
    private $authenticate;

    public function __construct(AuthenticateRequest $authenticate)
    {
        $this->authenticate = $authenticate;
    }

    public function handle($request, \Closure $next)
    {
        $token = $request->header('authorization');

        if ($token !== null) {
            $token = preg_replace('/^Bearer\s+/', '', $token);
        }

        try {
            $this->attachContext($request, $this->authenticate->execute($token));
        } catch (MissingCredentials $exception) {
            return ApiResponse::createUnauthorized($exception->getMessage());
        } catch (\RuntimeException $exception) {
            return \app\api\ApiException::unauthorized($exception->getMessage())->exceptionHandle();
        } catch (\app\api\ApiException $exception) {
            return $exception->exceptionHandle();
        }

        $response = $next($request);

        if ($request->auth->renewedToken() !== null) {
            $response->header('X-New-Token', $request->auth->renewedToken());
        }

        return $response;
    }

    private function attachContext($request, AuthContext $context): void
    {
        $request->auth = $context;
    }
}
