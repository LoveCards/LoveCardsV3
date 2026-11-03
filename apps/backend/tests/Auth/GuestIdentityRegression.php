<?php
declare(strict_types=1);

// 访客身份回归（T1 认证加固）：
// 1) 访客账号必须是服务端随机一次性身份，同一出口 IP 的先后访客不得共享账号；
// 2) 访客密码哈希不得可被固定口令 123456 验证（历史缺陷）；
// 3) 持访客角色的账号经口令登录必须被 LoginUser 拒绝（中和存量访客行）；
// 4) 普通账号口令登录不受影响。

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use app\api\ApiException;
use app\modules\Platform\Auth\Application\LoginUser;
use app\modules\Platform\Auth\Application\Session;
use app\modules\Platform\Auth\Contract\TokenService;
use app\modules\Platform\Auth\Contract\UserRepository;
use app\modules\Platform\Auth\Public\AuthUser;

$app = new \think\App(dirname(__DIR__, 2));
$app->setRuntimePath(sys_get_temp_dir() . '/lc-guest-regression-runtime/');
$app->initialize();

$guestRoleId = (int) config('system.system_roles.guest');
$normalRoleId = (int) config('system.system_roles.user');

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$tokens = new class implements TokenService {
    public function sign(array $data): string
    {
        return 'token-for-' . (int) ($data['uid'] ?? 0);
    }
    public function verify(string $token): array
    {
        return [];
    }
    public function invalidate(string $token): void
    {
    }
};

$directory = new class implements UserRepository {
    public array $created = [];
    public function findById(int $id): ?AuthUser
    {
        foreach ($this->created as $entry) {
            if ($entry['user']->id() === $id) {
                return $entry['user'];
            }
        }
        return null;
    }
    public function findByAccount(string $account): ?AuthUser
    {
        foreach ($this->created as $entry) {
            if ($entry['email'] === $account || $entry['number'] === $account || $entry['username'] === $account) {
                return $entry['user'];
            }
        }
        return null;
    }
    public function contactExists(string $email, string $phone): bool
    {
        return false;
    }
    public function create(string $number, string $username, string $email, string $phone, string $passwordHash, array $roleIds, int $status): AuthUser
    {
        $user = new AuthUser(count($this->created) + 101, $status, $passwordHash, $roleIds);
        $this->created[] = ['user' => $user, 'email' => $email, 'number' => $number, 'username' => $username];
        return $user;
    }
};

$session = new Session($tokens, $directory, new LoginUser($tokens, $directory));

$legacyHash = password_hash('123456', PASSWORD_DEFAULT);

$roleStore = static function (string $hash, int $roleId): UserRepository {
    return new class($hash, $roleId) implements UserRepository {
        public function __construct(private string $hash, private int $roleId)
        {
        }
        public function findById(int $id): ?AuthUser
        {
            return null;
        }
        public function findByAccount(string $account): ?AuthUser
        {
            return new AuthUser(9001, 0, $this->hash, [$this->roleId]);
        }
        public function contactExists(string $email, string $phone): bool
        {
            return false;
        }
        public function create(string $number, string $username, string $email, string $phone, string $passwordHash, array $roleIds, int $status): AuthUser
        {
            throw new RuntimeException('unused in this scenario');
        }
    };
};

$tests = [];
$tests['guest mints stay random per visit and never reuse a shared account'] = static function () use ($session, $directory, $guestRoleId, $assert): void {
    $first = $session->guest('203.0.113.7');
    $second = $session->guest('203.0.113.7');

    $assert(($first['token'] ?? '') !== '' && ($second['token'] ?? '') !== '', 'guest sessions must still hand out a token');
    $assert(count($directory->created) === 2, 'two guest visits must not share a single account');
    $emails = array_column($directory->created, 'email');
    $assert($emails[0] !== $emails[1], 'guest accounts must not be derivable from caller context');
    foreach ($directory->created as $entry) {
        $assert(!password_verify('123456', $entry['user']->passwordHash()), 'guest password must not be the static 123456');
        $assert(in_array($guestRoleId, array_map('intval', $entry['user']->roleIds()), true), 'guest accounts must carry the guest system role');
    }
};
$tests['guest role accounts are refused for password login even with the right password'] = static function () use ($tokens, $roleStore, $legacyHash, $guestRoleId, $assert): void {
    try {
        (new LoginUser($tokens, $roleStore($legacyHash, $guestRoleId)))->execute('legacy-guest@g.com', '123456');
        throw new RuntimeException('password login on a guest-role account must be refused even with the right password');
    } catch (ApiException $exception) {
        $assert($exception->getHttpStatus() === 403, 'guest password login must be refused with a forbidden status');
    }
};
$tests['regular accounts still authenticate with their password'] = static function () use ($tokens, $roleStore, $legacyHash, $normalRoleId, $assert): void {
    $loggedIn = (new LoginUser($tokens, $roleStore($legacyHash, $normalRoleId)))->execute('regular-member@g.com', '123456');
    $assert(($loggedIn['token'] ?? '') !== '', 'regular accounts must still authenticate with their password');
};

$failures = 0;
foreach ($tests as $name => $run) {
    try {
        $run();
    } catch (Throwable $exception) {
        $failures++;
        fwrite(STDERR, "FAIL {$name}: {$exception->getMessage()}\n");
    }
}
if ($failures > 0) {
    exit(1);
}
fwrite(STDOUT, count($tests) . " guest identity tests passed.\n");
