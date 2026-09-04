<?php

namespace app\modules\Platform\User\Service;

use app\modules\Platform\User\Infrastructure\Persistence\Model\Users as UsersModel;
use app\modules\Platform\Rbac\Infrastructure\Persistence\Model\Roles as RolesModel;
use app\modules\Platform\Rbac\Infrastructure\Persistence\Model\UserRoles;
use app\modules\Platform\Rbac\Public\Authorization;
use app\api\ApiException;
use app\modules\Platform\Captcha\Public\Captcha;
use app\modules\Platform\Auth\Contract\SessionRegistry;
use app\modules\Platform\Notifications\Contract\SystemNoticeIssuer;
use app\modules\Platform\Notifications\Domain\SystemNotice;
use app\shared\Observability\AuditLogger;
use app\modules\Platform\User\Public\ProfileCover;
use app\modules\Platform\User\Public\UserProfileStats;
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
        $userData['cover_url'] = ProfileCover::url($uid);
        $userData['stats'] = ['received_likes' => UserProfileStats::receivedLikes($uid)];
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

    public static function receivedLikes(int $uid): int
    {
        return UserProfileStats::receivedLikes($uid);
    }

    public static function update(int $uid, array $data, ?string $ipAddress = null): array
    {
        $coverSpecified = array_key_exists('cover_file_id', $data);
        $coverFileId = $data['cover_file_id'] ?? null;
        if ($coverSpecified && $coverFileId !== null && (int) $coverFileId <= 0) {
            throw ApiException::validation(['cover_file_id' => ['封面图片不可用。']]);
        }
        $allowed = array_intersect_key($data, array_flip(['avatar', 'username', 'bio', 'location', 'gender', 'birth_date']));
        foreach (['avatar', 'username', 'bio', 'location', 'gender', 'birth_date'] as $field) {
            if (!array_key_exists($field, $allowed) || $allowed[$field] === null) continue;
            $allowed[$field] = trim((string) $allowed[$field]);
        }
        foreach (['bio' => 160, 'location' => 64] as $field => $limit) {
            if (!array_key_exists($field, $allowed)) continue;
            if ($allowed[$field] === null || $allowed[$field] === '') {
                $allowed[$field] = null;
                continue;
            }
            if (mb_strlen((string) $allowed[$field], 'UTF-8') > $limit) {
                throw ApiException::validation([$field => [($field === 'bio' ? '个人简介' : '所在地') . "最多 {$limit} 个字符。"]]);
            }
        }
        if (isset($allowed['location']) && preg_match('/[\r\n]/u', (string) $allowed['location'])) {
            throw ApiException::validation(['location' => ['所在地必须为单行文本。']]);
        }
        if (array_key_exists('gender', $allowed) && $allowed['gender'] !== null && !in_array($allowed['gender'], ['male', 'female', 'non_binary', 'prefer_not_to_say'], true)) {
            throw ApiException::validation(['gender' => ['性别选项无效。']]);
        }
        if (array_key_exists('birth_date', $allowed) && $allowed['birth_date'] !== null) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $allowed['birth_date']);
            $errors = \DateTimeImmutable::getLastErrors();
            if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format('Y-m-d') !== $allowed['birth_date']) {
                throw ApiException::validation(['birth_date' => ['出生日期格式无效。']]);
            }
            $today = new \DateTimeImmutable('today');
            if ($date < new \DateTimeImmutable('1900-01-01') || $date > $today) {
                throw ApiException::validation(['birth_date' => ['出生日期必须在 1900-01-01 至今天之间。']]);
            }
        }
        if ($allowed === [] && !$coverSpecified) return self::get($uid);

        Db::startTrans();
        try {
            $exists = UsersModel::where('id', $uid)->whereNull('deleted_at')->lock(true)->value('id');
            if ($exists === null) throw ApiException::notFound('用户不存在', ApiException::CODE_USER_NOT_FOUND);
            if ($allowed !== []) UsersModel::where('id', $uid)->whereNull('deleted_at')->update($allowed);
            if ($coverSpecified) ProfileCover::replace($uid, $coverFileId === null ? null : (int) $coverFileId);
            app(AuditLogger::class)->record($uid, 'user.profile.updated', 'user', (string) $uid, [
                'fields' => array_merge(array_keys($allowed), $coverSpecified ? ['cover_file_id'] : []), '_ip' => $ipAddress,
            ]);
            Db::commit();
        } catch (\Throwable $exception) {
            Db::rollback();
            throw $exception;
        }
        return self::get($uid);
    }

    public static function changePassword(int $uid, string $currentPassword, string $newPassword, ?string $ipAddress = null): void
    {
        self::assertCurrentPassword($uid, $currentPassword);

        Db::startTrans();
        try {
            $updated = UsersModel::where('id', $uid)->whereNull('deleted_at')->update([
                'password' => password_hash($newPassword, PASSWORD_DEFAULT),
                'security_version' => Db::raw('security_version + 1'),
                'credentials_changed_at' => date('Y-m-d H:i:s'),
            ]);
            if ($updated !== 1) throw ApiException::notFound('用户不存在', ApiException::CODE_USER_NOT_FOUND);
            app(SessionRegistry::class)->revokeAllForUser($uid, 'password_changed', self::now());
            self::issueSecurityNotice(
                $uid,
                'user.password.changed:' . self::securityVersion($uid),
                '你的账号密码已修改，所有登录会话已退出。若非本人操作，请尽快联系站点管理员。',
                'critical'
            );
            app(AuditLogger::class)->record($uid, 'user.password.changed', 'user', (string) $uid, ['_ip' => $ipAddress]);
            Db::commit();
        } catch (\Throwable $exception) {
            Db::rollback();
            throw $exception;
        }
    }

    public static function changeEmail(int $uid, string $currentPassword, string $email, string $captcha, ?string $ipAddress = null): void
    {
        self::assertCurrentPassword($uid, $currentPassword);
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ApiException::validation(['email' => ['邮箱格式不正确。']], '字段校验失败', 'email_invalid');
        }
        $duplicate = UsersModel::where('email', $email)->where('id', '<>', $uid)->whereNull('deleted_at')->value('id');
        if ($duplicate !== null) {
            throw ApiException::validation(['email' => ['该邮箱已被使用。']], '字段校验失败', 'email_invalid');
        }
        $verification = Captcha::verifyDetailed('code', ['key' => $email, 'code' => $captcha, 'scene' => 'Info_BindEmailCaptcha']);
        if ($verification !== 'valid') {
            $reason = $verification === 'expired' ? 'captcha_expired' : 'captcha_invalid';
            $message = $verification === 'expired' ? '验证码已过期。' : '验证码错误。';
            throw ApiException::validation(['captcha' => [$message]], '字段校验失败', $reason);
        }

        Db::startTrans();
        try {
            $updated = UsersModel::where('id', $uid)->whereNull('deleted_at')->update([
                'email' => $email,
                'security_version' => Db::raw('security_version + 1'),
                'credentials_changed_at' => date('Y-m-d H:i:s'),
            ]);
            if ($updated !== 1) throw ApiException::notFound('用户不存在', ApiException::CODE_USER_NOT_FOUND);
            app(SessionRegistry::class)->revokeAllForUser($uid, 'email_changed', self::now());
            self::issueSecurityNotice(
                $uid,
                'user.email.changed:' . self::securityVersion($uid),
                '你的账号邮箱已修改，所有登录会话已退出。若非本人操作，请尽快联系站点管理员。',
                'critical'
            );
            app(AuditLogger::class)->record($uid, 'user.email.changed', 'user', (string) $uid, ['_ip' => $ipAddress]);
            Db::commit();
        } catch (\Throwable $exception) {
            Db::rollback();
            throw $exception;
        }
    }

    /** @return array{sent:true,expires_in:int} */
    public static function sendEmailCaptcha(string $email): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ApiException::validation(['email' => ['邮箱格式不正确。']], '字段校验失败', 'email_invalid');
        }

        $result = Captcha::generate('code', ['to' => $email, 'scene' => 'Info_BindEmailCaptcha', 'ttl' => 300]);
        if (!($result['status'] ?? false)) {
            $message = (string) ($result['msg'] ?? '验证码发送失败');
            if (str_contains($message, '未过期')) {
                throw ApiException::tooManyRequests('验证码发送过于频繁', 'captcha_rate_limited', 300);
            }
            throw ApiException::validation(['email' => ['验证码发送失败。']], '字段校验失败', 'captcha_delivery_failed');
        }
        return ['sent' => true, 'expires_in' => 300];
    }

    private static function assertCurrentPassword(int $uid, string $currentPassword): void
    {
        $hash = UsersModel::where('id', $uid)->whereNull('deleted_at')->value('password');
        if (!is_string($hash) || $hash === '') {
            throw ApiException::notFound('用户不存在', ApiException::CODE_USER_NOT_FOUND);
        }
        if (!password_verify($currentPassword, $hash)) {
            throw ApiException::validation(
                ['current_password' => ['当前密码不正确。']],
                '字段校验失败',
                'current_password_invalid'
            );
        }
    }

    private static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    private static function securityVersion(int $uid): int
    {
        return (int) UsersModel::where('id', $uid)->value('security_version');
    }

    private static function issueSecurityNotice(int $uid, string $key, string $message, string $severity): void
    {
        app(SystemNoticeIssuer::class)->issue($uid, $key, SystemNotice::SECURITY, $message, '/settings', $severity);
    }
}
