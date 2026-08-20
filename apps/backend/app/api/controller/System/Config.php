<?php

namespace app\api\controller\System;

use app\api\ApiResponse;
use app\api\controller\BaseController;
use app\modules\Platform\Config\Transport\Api\ConfigResource;
use app\modules\Platform\Config\Service\ConfigManager;

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
        $params = $this->validated();
        $group = $params['group'] ?? '';
        $groups = empty($group) ? [] : explode(',', (string) $group);
        return ApiResponse::createOk(ConfigResource::make($this->config->get($groups)));
    }

    public function groups()
    {
        return ApiResponse::createOk(ConfigResource::make($this->config->schemaGroups()));
    }

    public function update()
    {
        $this->config->update($this->validated());
        return ApiResponse::createNoContent();
    }

    public function init()
    {
        $result = $this->config->init();
        return ApiResponse::createOk(ConfigResource::make($result));
    }

    public function register()
    {
        $params = $this->validated();
        $group = $params['group'] ?? '';
        $schema = $params['schema'] ?? [];
        if (is_string($schema)) $schema = json_decode($schema, true) ?: [];

        if (empty($group) || empty($schema)) {
            return ApiResponse::createBadRequest('缂哄皯 group 鎴?schema');
        }

        $result = $this->config->register($group, (array) $schema);
        return ApiResponse::createOk(ConfigResource::make($result));
    }

    public function reload()
    {
        $params = $this->validated();
        $group = $params['group'] ?? null;
        $this->config->reload($group ?: null);
        return ApiResponse::createNoContent();
    }

    public function delete()
    {
        $params = $this->validated();
        $group = $params['group'] ?? '';
        if (empty($group)) {
            return ApiResponse::createBadRequest('缺少 group');
        }
        $this->config->deleteGroup($group);
        return ApiResponse::createNoContent();
    }

    public function deleteKey()
    {
        $params = $this->validated();
        $group = $params['group'] ?? '';
        $key = $params['key'] ?? '';
        if (empty($group) || empty($key)) {
            return ApiResponse::createBadRequest('缺少 group 或 key');
        }
        $this->config->deleteKey($group, $key);
        return ApiResponse::createNoContent();
    }
}
