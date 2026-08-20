<?php
declare(strict_types=1);

namespace app\middleware;

use app\modules\Platform\Eventing\Application\RequestExecutor;
use app\modules\Platform\Eventing\Domain\RequestExecutionBudget;

/** Drains only request-safe deliveries after the response path; daemon work remains for the worker. */
final class EventingRequestDrain
{
    public function handle($request, \Closure $next)
    {
        try { return $next($request); }
        finally {
            try {
                if ((bool) env('LC_EVENTING_REQUEST_DRAIN', true)) {
                    app(RequestExecutor::class)->run(new RequestExecutionBudget('fpm-' . substr(bin2hex(random_bytes(8)), 0, 16)));
                }
            } catch (\Throwable $ignored) { /* never replace a successful HTTP response with queue diagnostics */ }
        }
    }
}
