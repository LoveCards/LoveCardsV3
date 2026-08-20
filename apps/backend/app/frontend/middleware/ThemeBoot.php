<?php
namespace app\frontend\middleware;

use think\Response;
use think\Request;
use app\modules\Theme\Domain\ThemeManifest;

class ThemeBoot
{
    public function handle(Request $request, \Closure $next)
    {
        try { $pathinfo = ThemeManifest::normalizePath('/' . ltrim($request->pathinfo(), '/')); }
        catch (\Throwable $e) { return $next($request); }
        if (!in_array(strtoupper($request->method()), ['GET', 'HEAD'], true)) return $next($request);
        if (strpos($pathinfo, '/_lc/theme-assets/') === 0) {
            try { return app(\app\frontend\controller\Theme::class)->asset(); }
            catch (\Throwable $e) { return Response::create('<h1>Internal Server Error</h1>', 'html', 500); }
        }
        if (ThemeManifest::isReservedPath($pathinfo)) return $next($request);
        try {
            $controller = app(\app\frontend\controller\Theme::class);
            return $controller->index();
        } catch (\Throwable $e) {
            \think\facade\Log::error('[ThemeBoot] ' . $e->getMessage());
            return Response::create('<h1>Internal Server Error</h1>', 'html', 500);
        }
    }
}
