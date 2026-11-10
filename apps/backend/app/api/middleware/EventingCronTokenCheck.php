<?php
declare(strict_types=1);

namespace app\api\middleware;

use app\api\ApiException;
use Closure;

/** 外部 URL cron 的独立凭据；只接受 HTTPS POST。 */
final class EventingCronTokenCheck
{
    public function handle($request, Closure $next)
    {
        // ThinkPHP isSsl() 会信任客户端可伪造的 X-Forwarded-Proto；只使用 Web Server 传给 FPM 的值。
        $https = strtolower((string) $request->server('HTTPS', ''));
        $scheme = strtolower((string) $request->server('REQUEST_SCHEME', ''));
        $port = (string) $request->server('SERVER_PORT', '');
        if (!in_array($https, ['on', '1'], true) && $scheme !== 'https' && $port !== '443') {
            throw ApiException::unauthorized('定时任务入口要求 HTTPS');
        }
        $configured = env('LC_EVENTING_CRON_TOKEN', '');
        $expected = is_string($configured) && $configured !== ''
            ? $configured : (string) (getenv('LC_EVENTING_CRON_TOKEN') ?: '');
        $header = (string) $request->header('authorization', '');
        if (strlen($expected) < 32 || !hash_equals('Bearer ' . $expected, $header)) {
            throw ApiException::unauthorized('定时任务凭证无效');
        }
        return $next($request);
    }
}
