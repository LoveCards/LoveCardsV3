<?php
declare(strict_types=1);

namespace app\middleware;

use app\shared\Observability\LogContext;
use app\shared\Observability\LogContextScope;

final class ObservabilityContext
{
    public function handle($request, \Closure $next)
    {
        $scope = app(LogContextScope::class);
        $requestId = (string) ($request->header('x-request-id') ?: bin2hex(random_bytes(16)));
        $correlationId = (string) ($request->header('x-correlation-id') ?: $requestId);
        try {
            $scope->set(LogContext::start($requestId, $correlationId));
            $response = $next($request);
            if (method_exists($response, 'header')) {
                $response->header(['X-Request-Id' => $requestId, 'X-Correlation-Id' => $correlationId]);
            }
            return $response;
        } finally { $scope->clear(); }
    }
}
