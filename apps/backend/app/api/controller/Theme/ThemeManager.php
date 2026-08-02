<?php

namespace app\api\controller\Theme;

use think\facade\Request;
use app\api\ApiException;
use app\api\ApiResponse;
use app\api\controller\BaseController;
use app\modules\Theme\Transport\Api\ThemeResource;
use app\modules\Theme\Service\ThemeManager as ThemeService;

class ThemeManager extends BaseController
{
    public function list()
    {
        return ApiResponse::createOk(ThemeResource::collection(ThemeService::list()));
    }

    public function upload()
    {
        $file = request()->file('file');
        if (!$file) {
            throw ApiException::badRequest('请上传 ZIP 文件');
        }
        if ($file->getSize() > ThemeService::maxZipBytes()) {
            throw ApiException::badRequest('ZIP 文件过大');
        }

        $info = $file->move(runtime_path() . 'theme_upload');
        if (!$info) {
            throw ApiException::badRequest('文件上传失败');
        }

        $zipPath = $info->getRealPath();
        try {
            return ApiResponse::createOk(ThemeResource::make(ThemeService::install($zipPath)));
        } finally {
            if (is_file($zipPath)) {
                @unlink($zipPath);
            }
        }
    }

    public function activate()
    {
        $name = Request::param('name', '');
        if (empty($name)) {
            throw ApiException::badRequest('缺少主题名称');
        }
        ThemeService::activate($name);
        return ApiResponse::createNoContent();
    }

    public function config()
    {
        $theme = ThemeService::active();
        if (empty($theme)) {
            throw ApiException::badRequest('未找到活跃主题');
        }

        return ApiResponse::createOk(ThemeResource::make([
            'name' => $theme['name'],
            'mode' => $theme['mode'] ?? 'spa',
            'config_schema' => $theme['config'] ?? [],
            'config_values' => ThemeService::config($theme['name']),
        ]));
    }

    public function updateConfig()
    {
        $params = Request::param();
        if (empty($params)) {
            throw ApiException::badRequest('缺少配置参数');
        }
        ThemeService::updateConfig($params);
        return ApiResponse::createNoContent();
    }

    public function freezeConfig()
    {
        ThemeService::freezeConfig();
        return ApiResponse::createNoContent();
    }

    public function delete()
    {
        $name = Request::param('name', '');
        if (empty($name)) {
            throw ApiException::badRequest('缺少主题名称');
        }
        ThemeService::delete($name);
        return ApiResponse::createNoContent();
    }
}
