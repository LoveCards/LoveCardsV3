<?php

namespace app\api\middleware;

use app\modules\Platform\Auth\Public\AuthContext;
use app\modules\Platform\Auth\Application\AuthenticateRequest;
use app\modules\Platform\Auth\Public\MissingCredentials;
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
        // Apache/FastCGI deployments may strip Authorization before PHP sees it.
        // The SDK also sends X-Token for this compatibility path.
        $token = $request->header('authorization') ?: $request->header('x-token');

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
