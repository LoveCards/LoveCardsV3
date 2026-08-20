<?php

use think\facade\Route;
use app\api\middleware\JwtAuthCheck;
use app\api\middleware\PermissionCheck;
use app\modules\Theme\Transport\Api\Admin as AdminValidate;
use app\modules\Theme\Transport\Api\Upload as UploadValidate;

Route::get('theme/config', 'Theme.ThemeManager/config')
    ->name('theme.publicConfig')
    ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Theme\\Transport\\Api\\ThemeConfigResource', 'shape' => 'item', 'status' => 200]])
    ->setOption('meta', ['name' => 'Public theme config', 'group' => 'Theme', 'access' => ['auth' => 'public']]);

Route::group('admin/theme', function () {
    Route::get('list', 'Theme.ThemeManager/list')->name('theme.list')
        ->setOption('contract', ['request' => null, 'response' => ['resource' => 'app\\modules\\Theme\\Transport\\Api\\ThemeResource', 'shape' => 'collection', 'status' => 200]])
        ->setOption('meta', ['name' => 'List themes', 'group' => 'Theme', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'theme.read', 'scope' => 'global']]]]);
    Route::post('upload', 'Theme.ThemeManager/upload')->name('theme.upload')
        ->setOption('contract', ['request' => ['validate' => UploadValidate::class, 'scene' => 'default', 'contentType' => 'multipart/form-data'], 'response' => ['resource' => 'app\\modules\\Theme\\Transport\\Api\\ThemeResource', 'shape' => 'item', 'status' => 200]])
        ->setOption('meta', ['name' => 'Upload theme', 'group' => 'Theme', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'theme.upload', 'scope' => 'global']]]]);
    Route::post('activate', 'Theme.ThemeManager/activate')->name('theme.activate')
        ->setOption('contract', ['request' => ['validate' => AdminValidate::class, 'scene' => 'activate'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => 'Activate theme', 'group' => 'Theme', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'theme.activate', 'scope' => 'global']]]]);
    Route::put('config', 'Theme.ThemeManager/updateConfig')->name('theme.updateConfig')
        ->setOption('contract', ['request' => ['validate' => AdminValidate::class, 'scene' => 'updateConfig'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => 'Update theme config', 'group' => 'Theme', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'theme.update', 'scope' => 'global']]]]);
    Route::delete('delete', 'Theme.ThemeManager/delete')->name('theme.delete')
        ->setOption('contract', ['request' => ['validate' => AdminValidate::class, 'scene' => 'delete'], 'response' => ['resource' => null, 'shape' => 'void', 'status' => 204]])
        ->setOption('meta', ['name' => 'Delete theme', 'group' => 'Theme', 'access' => ['auth' => 'required', 'allOf' => [['capability' => 'admin.access', 'scope' => 'global'], ['capability' => 'theme.delete', 'scope' => 'global']]]]);
})->middleware(JwtAuthCheck::class)->middleware(PermissionCheck::class);
