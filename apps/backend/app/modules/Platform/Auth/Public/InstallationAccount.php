<?php
declare(strict_types=1);

namespace app\modules\Platform\Auth\Public;

use app\modules\Platform\Auth\Infrastructure\RootAccountBootstrap;

/** 安装终结时设置初始 root 凭据；种子中的不可登录占位值不能作为密码使用。 */
final class InstallationAccount
{
    public static function setRootPassword(string $password): void
    {
        if (!mb_check_encoding($password, 'UTF-8') || mb_strlen($password, 'UTF-8') < 12
            || mb_strlen($password, 'UTF-8') > 128 || str_contains($password, "\0")) {
            throw new \InvalidArgumentException('初始管理员密码须为 12 至 128 个字符');
        }

        RootAccountBootstrap::setPassword($password);
    }
}
