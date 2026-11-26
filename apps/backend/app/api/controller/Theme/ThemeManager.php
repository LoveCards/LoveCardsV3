<?php

namespace app\api\controller\Theme;

use app\api\ApiException;
use app\api\ApiResponse;
use app\api\controller\BaseController;
use app\modules\Theme\Transport\Api\ThemeResource;
use app\modules\Theme\Transport\Api\ThemeConfigResource;
use app\modules\Theme\Public\Theme as ThemeService;
use app\modules\Theme\Application\ThemeOperationException;

class ThemeManager extends BaseController
{
    public function list()
    {
        return ApiResponse::createOk(ThemeResource::collection(ThemeService::list()));
    }

    public function upload()
    {
        $file = request()->file('file');
        $params = $this->validated(null, ['file' => $file]);
        $file = $params['file'];
        if ($file->getSize() > ThemeService::maxZipBytes()) throw ApiException::badRequest('Theme archive is too large');
        $info = $file->move(runtime_path() . 'theme_upload');
        if (!$info) throw ApiException::badRequest('Theme upload failed');
        $zipPath = $info->getRealPath();
        try {
            return ApiResponse::createOk(ThemeResource::make(ThemeService::install($zipPath, (int) request()->auth->uid())));
        } catch (ThemeOperationException $e) {
            throw $this->operationError($e);
        } catch (\Throwable $e) {
            throw ApiException::badRequest($e->getMessage());
        } finally {
            if (is_file($zipPath)) @unlink($zipPath);
        }
    }

    public function activate()
    {
        $params = $this->validated();
        try { ThemeService::activate((string)$params['id'], (string)$params['release'], (int) request()->auth->uid(), (string) request()->host()); }
        catch (ThemeOperationException $e) { throw $this->operationError($e); }
        catch (\Throwable $e) { throw ApiException::badRequest($e->getMessage()); }
        return ApiResponse::createNoContent();
    }

    public function config()
    {
        $theme = ThemeService::active();
        if (empty($theme['theme'])) throw ApiException::badRequest('Active theme not found');
        return ApiResponse::createOk(ThemeConfigResource::make($theme));
    }

    public function updateConfig()
    {
        $params = $this->validated();
        $values = is_string($params['values']) ? json_decode($params['values'], true) : $params['values'];
        if (!is_array($values)) throw ApiException::badRequest('Theme values must be an object');
        try { ThemeService::updateConfig((string)$params['id'], (string)$params['release'], $values, (int) request()->auth->uid(), (string) request()->host()); }
        catch (ThemeOperationException $e) { throw $this->operationError($e); }
        catch (\Throwable $e) { throw ApiException::badRequest($e->getMessage()); }
        return ApiResponse::createNoContent();
    }

    public function delete()
    {
        $params = $this->validated();
        try { ThemeService::delete((string)$params['id'], (string)$params['release'], (int) request()->auth->uid()); }
        catch (ThemeOperationException $e) { throw $this->operationError($e); }
        catch (\Throwable $e) { throw ApiException::badRequest($e->getMessage()); }
        return ApiResponse::createNoContent();
    }

    private function operationError(ThemeOperationException $error): ApiException
    {
        return $error->reasonCode === 'theme_operation_incomplete'
            ? ApiException::badRequest($error->getMessage(), ApiException::CODE_PARAM_INVALID, $error->details(), $error)
            : ApiException::error($error->getMessage(), ApiException::CODE_SYSTEM_ERROR, $error->details(), $error);
    }
}
