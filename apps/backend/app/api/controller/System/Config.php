<?php

namespace app\api\controller\System;

use app\api\ApiResponse;
use app\api\ApiException;
use app\api\controller\BaseController;
use app\modules\Platform\Config\Transport\Api\ConfigResource;
use app\modules\Platform\Config\Service\ConfigManager;
use app\modules\Platform\Config\Public\RedisConnectionSettings;
use app\modules\Platform\Config\Transport\Api\InternalRedisConfigResource;
use app\modules\Platform\Config\Transport\Api\RedisConnectionTestResource;

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
        return ApiResponse::createOk(ConfigResource::make($this->config->get($groups, request()->auth->grants())));
    }

    public function groups()
    {
        return ApiResponse::createOk(ConfigResource::make($this->config->schemaGroups(request()->auth->grants())));
    }

    public function testRedis()
    {
        return ApiResponse::createOk(RedisConnectionTestResource::make($this->config->testRedis(request()->auth->grants())));
    }

    /** InternalTokenCheck 在路由层鉴权；响应禁止缓存。 */
    public function internalRedisConfig()
    {
        try {
            $settings = RedisConnectionSettings::current();
        } catch (\Throwable $ignored) {
            throw ApiException::capabilityUnavailable('Redis 系统配置不可用');
        }
        return ApiResponse::createOk(InternalRedisConfigResource::make($settings))
            ->header(['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
    }

    public function update()
    {
        $snapshot = $this->config->update($this->validated(), request()->auth->grants());
        return ApiResponse::createOk(ConfigResource::make($snapshot));
    }

    public function init()
    {
        $result = $this->config->init(request()->auth->grants());
        // 上传限额场景键首次播种时，把旧全局键的现存自定义值物化到新键（幂等）
        \app\modules\Platform\Files\Application\UploadLimits::materialize($result['upload']['seeded'] ?? []);
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

        $result = $this->config->register($group, (array) $schema, request()->auth->grants());
        return ApiResponse::createOk(ConfigResource::make($result));
    }

    public function reload()
    {
        $params = $this->validated();
        $group = $params['group'] ?? null;
        $this->config->reload($group ?: null, request()->auth->grants());
        return ApiResponse::createNoContent();
    }

    public function delete()
    {
        $params = $this->validated();
        $group = $params['group'] ?? '';
        if (empty($group)) {
            return ApiResponse::createBadRequest('缺少 group');
        }
        $this->config->deleteGroup($group, request()->auth->grants());
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
        $this->config->deleteKey($group, $key, request()->auth->grants());
        return ApiResponse::createNoContent();
    }
}
