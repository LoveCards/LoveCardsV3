<?php
declare(strict_types=1);

namespace app\api\middleware;

use app\api\ApiException;
use app\modules\Platform\Auth\Application\AuthenticateRequest;
use app\modules\Platform\Auth\Public\MissingCredentials;

/** Attach an authenticated context when a token is present; anonymous access remains public. */
final class OptionalJwtAuth
{
    public function __construct(private AuthenticateRequest $authenticate) {}

    public function handle($request, \Closure $next)
    {
        $token = $request->header('authorization') ?: $request->header('x-token');
        if ($token === null || trim((string) $token) === '') return $next($request);
        $token = preg_replace('/^Bearer\s+/', '', (string) $token);

        try {
            $context = $this->authenticate->execute($token);
            if (!$context->isVisitor()) $request->auth = $context;
        } catch (MissingCredentials $exception) {
            return ApiException::unauthorized($exception->getMessage())->exceptionHandle();
        } catch (\RuntimeException $exception) {
            return ApiException::unauthorized($exception->getMessage())->exceptionHandle();
        } catch (ApiException $exception) {
            return $exception->exceptionHandle();
        }

        $response = $next($request);
        if (isset($request->auth) && $request->auth->renewedToken() !== null) {
            $response->header('X-New-Token', $request->auth->renewedToken());
        }
        return $response;
    }
}
