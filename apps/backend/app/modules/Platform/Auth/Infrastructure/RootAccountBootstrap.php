<?php
declare(strict_types=1);

namespace app\modules\Platform\Auth\Infrastructure;

use think\facade\Db;

/** 按安装种子唯一身份写入初始管理员凭据。 */
final class RootAccountBootstrap
{
    public static function setPassword(string $password): void
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash) || $hash === '') {
            throw new \RuntimeException('初始管理员密码生成失败');
        }

        if (Db::table('user_roles')->where('user_id', 1)->where('role_id', 1)->count() !== 1) {
            throw new \RuntimeException('初始管理员未获得 root 角色，无法完成安装');
        }

        $updated = Db::table('users')
            ->where('id', 1)
            ->where('email', 'admin@lovecards.cn')
            ->where('status', 0)
            ->whereNull('deleted_at')
            ->update([
                'password' => $hash,
                'credentials_changed_at' => date('Y-m-d H:i:s'),
                'security_version' => Db::raw('security_version + 1'),
            ]);
        if ($updated !== 1) {
            throw new \RuntimeException('初始管理员账号缺失，无法完成安装');
        }
    }
}
