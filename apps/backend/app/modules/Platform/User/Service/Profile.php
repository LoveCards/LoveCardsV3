<?php

namespace app\modules\Platform\User\Service;

use app\modules\Platform\User\Infrastructure\Persistence\Model\Users as UsersModel;
use app\modules\Platform\Rbac\Infrastructure\Persistence\Model\Roles as RolesModel;
use app\modules\Platform\Rbac\Infrastructure\Persistence\Model\UserRoles;
use app\modules\Platform\Rbac\Public\Authorization;
use app\api\ApiException;
use app\modules\Platform\Captcha\Public\Captcha;
use app\modules\Platform\Auth\Contract\SessionRegistry;
use think\facade\Db;

class Profile
{
    public static function get(int $uid): array
    {
        $user = UsersModel::where('id', $uid)
            ->withoutField(['password', 'deleted_at'])
            ->findOrEmpty();

        if ($user->isEmpty()) {
            throw ApiException::notFound('用户不存在', ApiException::CODE_USER_NOT_FOUND);
        }

        $userData = $user->toArray();
        $rolesId = UserRoles::where('user_id', $uid)->column('role_id') ?: [];
        $userData['roles_id'] = array_map('intval', $rolesId);

        $userData['roles'] = RolesModel::whereIn('id', $rolesId)
            ->whereNull('deleted_at')
            ->field('id,name,slug')
            ->select()
            ->toArray();

        $grants = Authorization::grants($rolesId);
        $userData['grants'] = $grants->toArray();
        $userData['capabilities'] = $grants->capabilityNames();

        return $userData;
    }

    public static function update(int $uid, array $data): void
    {
        $passwordChanged = isset($data['password']) && $data['password'] !== '';
        if ($passwordChanged) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            $data['security_version'] = Db::raw('security_version + 1');
            $data['credentials_changed_at'] = date('Y-m-d H:i:s');
        } else {
            unset($data['password']);
        }

        Db::startTrans();
        try {
            UsersModel::update($data, ['id' => $uid]);
            if ($passwordChanged) {
                app(SessionRegistry::class)->revokeAllForUser($uid, 'credentials_changed', self::now());
            }
            Db::commit();
        } catch (\Throwable $exception) {
            Db::rollback();
            throw $exception;
        }
    }

    public static function changePassword(int $uid, string $password): void
    {
        if ($password === '') {
            throw ApiException::badRequest('密码不可为空', ApiException::CODE_PARAM_INVALID);
        }

        Db::startTrans();
        try {
            $updated = UsersModel::where('id', $uid)->whereNull('deleted_at')->update([
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'security_version' => Db::raw('security_version + 1'),
                'credentials_changed_at' => date('Y-m-d H:i:s'),
            ]);
            if ($updated !== 1) throw ApiException::notFound('用户不存在', ApiException::CODE_USER_NOT_FOUND);
            app(SessionRegistry::class)->revokeAllForUser($uid, 'password_changed', self::now());
            Db::commit();
        } catch (\Throwable $exception) {
            Db::rollback();
            throw $exception;
        }
    }

    public static function changeEmail(int $uid, string $email, string $captcha): void
    {
        if (!Captcha::verify('code', ['key' => $email, 'code' => $captcha, 'scene' => 'Info_BindEmailCaptcha'])) {
            throw ApiException::badRequest('验证码错误', ApiException::CODE_PARAM_INVALID);
        }

        UsersModel::update(['email' => $email], ['id' => $uid]);
    }

    public static function sendEmailCaptcha(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ApiException::badRequest('邮箱格式错误', ApiException::CODE_PARAM_INVALID);
        }

        Captcha::generate('code', ['to' => $email, 'scene' => 'Info_BindEmailCaptcha', 'ttl' => 300]);
    }

    private static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
