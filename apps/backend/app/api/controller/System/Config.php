<?php

namespace app\api\controller\System;

use think\facade\Request;

use app\api\ApiResponse;
use app\api\controller\BaseController;
use app\api\resource\ConfigResource;
use app\api\service\System\ConfigManager;

class Config extends BaseController
{
    private ConfigManager $config;

    public function __construct(?ConfigManager $config = null)
    {
        parent::__construct();
        $this->config = $config ?: new ConfigManager();
    }

    public function index()
    {
        $group = Request::param('group', '');
        $groups = empty($group) ? [] : explode(',', (string) $group);
        return ApiResponse::createOk(ConfigResource::make($this->config->get($groups)));
    }

    public function groups()
    {
        return ApiResponse::createOk(ConfigResource::make($this->config->schemaGroups()));
    }

    public function update()
    {
        $this->config->update(Request::param());
        return ApiResponse::createNoContent();
    }

    public function init()
    {
        $result = $this->config->init();
        return ApiResponse::createOk(ConfigResource::make($result));
    }

    public function register()
    {
        $group = Request::param('group', '');
        $schema = Request::param('schema', []);

        if (empty($group) || empty($schema)) {
            return ApiResponse::createBadRequest('缂哄皯 group 鎴?schema');
        }

        $result = $this->config->register($group, (array) $schema);
        return ApiResponse::createOk(ConfigResource::make($result));
    }

    public function reload()
    {
        $group = Request::param('group');
        $this->config->reload($group ?: null);
        return ApiResponse::createNoContent();
    }

    public function delete()
    {
        $group = Request::param('group', '');
        if (empty($group)) {
            return ApiResponse::createBadRequest('缺少 group');
        }
        $this->config->deleteGroup($group);
        return ApiResponse::createNoContent();
    }

    public function deleteKey()
    {
        $group = Request::param('group', '');
        $key = Request::param('key', '');
        if (empty($group) || empty($key)) {
            return ApiResponse::createBadRequest('缺少 group 或 key');
        }
        $this->config->deleteKey($group, $key);
        return ApiResponse::createNoContent();
    }
}
