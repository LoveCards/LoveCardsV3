<?php

declare(strict_types=1);

namespace think\facade
{
    final class Db
    {
        public static int $commits = 0;
        public static int $rollbacks = 0;
        public static function startTrans(): void {}
        public static function commit(): void { self::$commits++; }
        public static function rollback(): void { self::$rollbacks++; }
        public static function raw(string $expression): string { return $expression; }
    }
}

namespace app\modules\Platform\Captcha\Public
{
    final class Captcha
    {
        public static bool $valid = true;
        public static function verifyDetailed(...$arguments): string { return self::$valid ? 'valid' : 'invalid'; }
        public static function generate(...$arguments): array { return ['status' => true]; }
    }
}

namespace app\modules\Platform\User\Infrastructure\Persistence\Model
{
    final class UserQuery
    {
        public function where(...$arguments): self { return $this; }
        public function whereNull(...$arguments): self { return $this; }
        public function update(array $data): int
        {
            if (!Users::$exists) return 0;
            foreach ($data as $field => $value) {
                if ($field === 'security_version' && $value === 'security_version + 1') {
                    Users::$row[$field]++;
                } else {
                    Users::$row[$field] = $value;
                }
            }
            return 1;
        }
        public function value(string $field): mixed { return Users::$row[$field] ?? null; }
    }

    final class Users
    {
        public static bool $exists = true;
        public static array $row = ['security_version' => 4, 'email' => 'old@example.com', 'password' => ''];
        public static function where(...$arguments): UserQuery { return new UserQuery(); }
        public static function update(array $data, array $where): int { return (new UserQuery())->update($data); }
    }

    final class Roles {}
}

namespace app\modules\Platform\Rbac\Infrastructure\Persistence\Model
{
    final class Roles {}
    final class UserRoles {}
}

namespace app\modules\Platform\Rbac\Public
{
    final class Authorization {}
}

namespace app\api
{
    final class ApiException extends \RuntimeException
    {
        public const CODE_USER_NOT_FOUND = 1001;
        public const CODE_PARAM_INVALID = 9002;
        public static function badRequest(string $message, int $code = 400): self { return new self($message, $code); }
        public static function notFound(string $message, int $code = 404): self { return new self($message, $code); }
        public static function validation(array $errors, string $message = '', ?string $reasonCode = null): self { return new self($message, 422); }
        public static function tooManyRequests(string $message, string $reasonCode, int $retryAfter): self { return new self($message, 429); }
    }
}

namespace app\modules\Platform\Notifications\Domain
{
    final class SystemNotice { public const SECURITY = 'system.security'; }
}

namespace
{
    use app\modules\Platform\Auth\Contract\SessionRegistry;
    use app\modules\Platform\Notifications\Contract\SystemNoticeIssuer;
    use app\modules\Platform\User\Infrastructure\Persistence\Model\Users;
    use app\modules\Platform\User\Service\Profile;
    use app\modules\Platform\Captcha\Public\Captcha;

    final class ProfileSecurityState
    {
        public static object $sessions;
        public static object $notices;
        public static object $audit;
    }

    function app(string $class): object
    {
        if ($class === SessionRegistry::class) return ProfileSecurityState::$sessions;
        if ($class === SystemNoticeIssuer::class) return ProfileSecurityState::$notices;
        if ($class === \app\shared\Observability\AuditLogger::class) return ProfileSecurityState::$audit;
        throw new RuntimeException('Unexpected container lookup: ' . $class);
    }

    require dirname(__DIR__, 2) . '/app/modules/Platform/Auth/Contract/SessionRegistry.php';
    require dirname(__DIR__, 2) . '/app/modules/Platform/Notifications/Contract/SystemNoticeIssuer.php';
    require dirname(__DIR__, 2) . '/app/modules/Platform/User/Service/Profile.php';

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };
    $reset = static function (): void {
        Users::$exists = true;
        Users::$row = ['security_version' => 4, 'email' => 'old@example.com', 'password' => password_hash('current-password', PASSWORD_DEFAULT)];
        Captcha::$valid = true;
        \think\facade\Db::$commits = 0;
        \think\facade\Db::$rollbacks = 0;
        ProfileSecurityState::$sessions = new class {
            public array $records = [];
            public function revokeAllForUser(...$arguments): int { $this->records[] = $arguments; return 2; }
        };
        ProfileSecurityState::$notices = new class {
            public array $records = [];
            public function issue(...$arguments): object { $this->records[] = $arguments; return new stdClass(); }
        };
        ProfileSecurityState::$audit = new class {
            public array $records = [];
            public function record(...$arguments): void { $this->records[] = $arguments; }
        };
    };

    $reset();
    Profile::changePassword(7, 'current-password', 'new-password');
    $assert(Users::$row['security_version'] === 5, 'password change must increment security version');
    $assert(count(ProfileSecurityState::$sessions->records) === 1, 'password change must revoke sessions');
    $assert(ProfileSecurityState::$notices->records[0][1] === 'user.password.changed:5', 'password notice must use security version');
    $assert(ProfileSecurityState::$notices->records[0][2] === 'system.security', 'password change must use security type');

    $reset();
    Profile::changeEmail(7, 'current-password', 'new@example.com', '123456');
    $assert(Users::$row['email'] === 'new@example.com' && Users::$row['security_version'] === 5, 'email change must update contact and security version');
    $assert(count(ProfileSecurityState::$sessions->records) === 1, 'email change must revoke sessions');
    $assert(ProfileSecurityState::$notices->records[0][1] === 'user.email.changed:5', 'email notice must use security version');

    $reset();
    Captcha::$valid = false;
    $rejected = false;
    try { Profile::changeEmail(7, 'current-password', 'new@example.com', 'wrong'); } catch (\app\api\ApiException) { $rejected = true; }
    $assert($rejected, 'invalid email captcha must be rejected');
    $assert(ProfileSecurityState::$notices->records === [] && ProfileSecurityState::$sessions->records === [], 'rejected email change must have no side effects');

    fwrite(STDOUT, "PASS profile security notification baseline\n");
}
