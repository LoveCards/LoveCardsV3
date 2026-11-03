<?php

namespace app\modules\Operations\Installer\Infrastructure;

use app\modules\Operations\Version\Service\VersionService;

class Environment
{

    protected static function EStruct($value, $status)
    {
        return [
            'value' => $value,
            'status' => $status
        ];
    }

    //验证安装环境
    public static function Check()
    {
        $requirements = VersionService::requirements();
        $data = [
            'php' => self::EStruct(
                phpversion(),
                version_compare(phpversion(), $requirements['php']['min'], '>=')
                    && version_compare(phpversion(), $requirements['php']['max'], '<')
            ),
            'pdo_mysql' => self::EStruct(-1, extension_loaded('pdo')),
            'openssl' => self::EStruct(-1, extension_loaded('openssl')),
        ];
        return $data;
    }
}
