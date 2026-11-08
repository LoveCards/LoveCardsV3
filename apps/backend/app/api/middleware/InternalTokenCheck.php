<?php
declare(strict_types=1);

namespace app\api\middleware;

use app\api\ApiException;
use Closure;

/**
 * backend↔Gateway 内部通道鉴权：共享密钥 Bearer（LC_RUNTIME_INTERNAL_TOKEN，契约 §4.2）。
 * 密钥未配置时一律拒绝，避免误开无鉴权内部端点。
 */
final class InternalTokenCheck
{
    public function handle($request, Closure $next)
    {
        $expected = (string) env('LC_RUNTIME_INTERNAL_TOKEN', '');
        $header = (string) $request->header('authorization', '');
        if ($expected === '' || strlen($expected) < 16 || !hash_equals('Bearer ' . $expected, $header)) {
            throw ApiException::unauthorized('内部调用凭证无效');
        }
        return $next($request);
    }
}
