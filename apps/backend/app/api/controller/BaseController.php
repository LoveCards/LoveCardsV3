<?php

namespace app\api\controller;

use think\exception\ValidateException;
use app\api\ApiException;
use app\api\ApiResponse;

use app\shared\Transport\Validation\RuleRegistry;
use app\shared\Transport\Validation\InputNormalizer;
use app\shared\Transport\Validation\PaginationInput;
use think\facade\Request;
use think\Response;

class BaseController
{
    function __construct()
    {
        $ruleRegistry = new RuleRegistry;
        $ruleRegistry->maker();
    }

    /**
     * Validate input using the contract bound to the current route.
     * The route owns the binding; field rules remain in the Validate class.
     */
    protected function validated(?array $requestParam = null, array $routeParams = []): array
    {
        $contract = $this->currentContract();
        $request = $contract['request'] ?? null;
        $params = array_merge($requestParam ?? Request::param(), $routeParams);

        if ($request === null) {
            return $params;
        }

        $validator = $request['validate'] ?? null;
        $sceneName = $request['scene'] ?? null;
        if (!is_string($validator) || $validator === '' || !is_string($sceneName) || $sceneName === '') {
            throw new \LogicException('Invalid API request contract on current route');
        }

        $scenes = property_exists($validator, 'all_scene') ? $validator::$all_scene : [];
        if (!isset($scenes[$sceneName]) || !is_array($scenes[$sceneName])) {
            throw new \LogicException("Unknown validation scene: {$validator}::{$sceneName}");
        }

        $params = InputNormalizer::paramsJsonToArray('search_keys', $params);
        $params = $this->param($validator, $scenes[$sceneName], $params);

        // An empty list search is the absence of a filter, not a search for
        // an empty value. Keep this invariant at the API boundary so clients
        // do not need to coordinate the same special case independently.
        if ($validator === PaginationInput::class && array_key_exists('search_value', $params)) {
            $searchValue = $params['search_value'];
            if ($searchValue === null || trim((string) $searchValue) === '') {
                unset($params['search_value'], $params['search_like']);
            }
        }

        return $params;
    }

    /**
     * Apply the current route's response contract and standard response code.
     */
    protected function respond($data = null, string $message = ''): Response
    {
        $response = $this->currentContract()['response'] ?? null;
        if (!is_array($response)) {
            throw new \LogicException('Missing API response contract on current route');
        }

        $status = (int) ($response['status'] ?? ApiResponse::HTTP_OK);
        if ($status === ApiResponse::HTTP_NO_CONTENT || ($response['shape'] ?? null) === 'void') {
            return ApiResponse::createNoContent();
        }

        $resource = $response['resource'] ?? null;
        $shape = $response['shape'] ?? 'item';
        if (!is_string($resource) || !class_exists($resource)) {
            throw new \LogicException('Invalid API response resource contract');
        }

        if ($shape === 'paginated') {
            $data = $resource::paginate((array) $data);
        } elseif ($shape === 'collection') {
            $data = $resource::collection((array) $data);
        } else {
            $data = $resource::make($data);
        }

        return match ($status) {
            ApiResponse::HTTP_CREATED => ApiResponse::createCreated($data, $message),
            ApiResponse::HTTP_ACCEPTED => ApiResponse::createAccepted($data, $message),
            default => ApiResponse::createOk($data, $message),
        };
    }

    private function currentContract(): array
    {
        $rule = request()->rule();
        $contract = $rule && method_exists($rule, 'getOption')
            ? $rule->getOption('contract')
            : null;

        if (!is_array($contract)) {
            throw new \LogicException('Current API route has no executable contract');
        }

        return $contract;
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
