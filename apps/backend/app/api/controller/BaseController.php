<?php

namespace app\api\controller;

use think\exception\ValidateException;
use app\api\ApiException;
use app\api\ApiResponse;

use app\shared\Transport\Validation\RuleRegistry;
use app\shared\Transport\Validation\InputNormalizer;
use app\shared\Transport\Validation\PaginationInput;

class BaseController
{
    function __construct()
    {
        $ruleRegistry = new RuleRegistry;
        $ruleRegistry->maker();
    }

    /**
     * 获取并验证参数（通用业务参数）
     *
     * @param string $ValidateClass 验证器类名
     * @param string $scene 验证场景名
     * @param array $requestParam 请求参数（空则从 Request 获取）
     * @return array 验证通过的参数
     * @throws ApiException 验证失败时抛出
     */
    protected function param(string $ValidateClass, array $scene, array $requestParam = []): array
    {
        $result = InputNormalizer::sceneFilter($requestParam, $scene);

        try {
            $params = InputNormalizer::sceneMessage($result, $ValidateClass);
            validate($ValidateClass)
                ->batch(true)
                ->check($params);
        } catch (ValidateException $e) {
            throw ApiException::badRequest('参数错误', ApiException::CODE_PARAM_INVALID, $e->getError());
        }

        return $params;
    }


    /**
     * 获取并验证列表/分页参数
     *
     * @param array $requestParam 请求参数（空则从 Request 获取）
     * @return array 验证通过的参数
     * @throws ApiException 验证失败时抛出
     */
    protected function paramIndex(array $requestParam = []): array
    {
        $params = InputNormalizer::sceneFilter($requestParam, PaginationInput::$all_scene['Defult']);
        $params = InputNormalizer::paramsJsonToArray('search_keys', $params['pass']);

        try {
            validate(PaginationInput::class)
                ->batch(true)
                ->check($params);
        } catch (ValidateException $e) {
            throw ApiException::badRequest('参数错误', ApiException::CODE_PARAM_INVALID, $e->getError());
        }

        return $params;
    }

    /**
     * 静态版列表参数获取（兼容 Service 层直接调用）
     *
     * @param array $requestParam
     * @return array
     * @throws ApiException
     */
    public static function paramCommon(array $requestParam = []): array
    {
        $params = InputNormalizer::sceneFilter($requestParam, PaginationInput::$all_scene['Defult']);
        $params = InputNormalizer::paramsJsonToArray('search_keys', $params['pass']);

        try {
            validate(PaginationInput::class)
                ->batch(true)
                ->check($params);
        } catch (ValidateException $e) {
            throw ApiException::badRequest('参数错误', ApiException::CODE_PARAM_INVALID, $e->getError());
        }

        return $params;
    }
}
