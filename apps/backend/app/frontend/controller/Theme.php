<?php
namespace app\frontend\controller;

use app\modules\Theme\Public\Theme as ThemeRuntime;
use think\Response;

class Theme
{
    public function index(): Response
    {
        $uri = request()->pathinfo() ?? '';
        return ThemeRuntime::boot($uri);
    }

    /**
     * 服务主题资产（已废弃，由 Theme::serveAsset() 处理）
     * @deprecated 2.1.0 资产服务已内置到 Theme::boot() 中
     */
    public function asset(string $path): Response
    {
        $uri = '/' . ltrim($path, '/');
        return ThemeRuntime::boot($uri);
    }
}
