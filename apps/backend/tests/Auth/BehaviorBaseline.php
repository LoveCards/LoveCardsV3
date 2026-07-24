<?php

declare(strict_types=1);

namespace {
    $testRequest = null;

    function config(string $key)
    {
        $values = [
            'system.system_roles.root' => 1,
            'system.system_roles.user' => 2,
            'system.system_roles.guest' => 3,
        ];

        return $values[$key] ?? null;
    }

    function request()
    {
        global $testRequest;

        return $testRequest;
    }

    require dirname(__DIR__, 2) . '/app/common/contract/TokenService.php';
}

namespace Tests\Auth {
    final class TokenService implements \app\common\contract\TokenService
    {
        public static $verified = ['uid' => 10];
        public static $failure = null;
        public static $signed = [];

        public function sign(array $data): string
        {
            self::$signed = $data;

            return 'signed-token-' . $data['uid'];
        }

        public function verify(string $token): array
        {
            if (self::$failure !== null) {
                throw self::$failure;
            }

            return self::$verified;
        }

        public function invalidate(string $token): void
        {
        }
    }

    final class Response
    {
        public $status;
        public $message;
        public $headers = [];

        public function __construct(int $status = 200, string $message = '')
        {
            $this->status = $status;
            $this->message = $message;
        }

        public function header(string $name, string $value): self
        {
            $this->headers[$name] = $value;

            return $this;
        }
    }

    final class User implements \ArrayAccess
    {
        public $id;
        public $status;
        public $password;
        public $roles_id;

        public function __construct(int $id, int $status, string $password, $rolesId = [])
        {
            $this->id = $id;
            $this->status = $status;
            $this->password = $password;
            $this->roles_id = $rolesId;
        }

        public function offsetExists($offset): bool
        {
            return property_exists($this, (string) $offset);
        }

        public function offsetGet($offset)
        {
            return $this->{$offset};
        }

        public function offsetSet($offset, $value): void
        {
            $this->{$offset} = $value;
        }

        public function offsetUnset($offset): void
        {
            unset($this->{$offset});
        }
    }

    final class Request
    {
        public $uid = null;
        public $user = null;
        public $rolesId = [];
        public $caps = [];
        public $newToken = null;
        private $authorization;
        private $rule;

        public function __construct(?string $authorization = null, ?Rule $rule = null)
        {
            $this->authorization = $authorization;
            $this->rule = $rule ?? new Rule();
        }

        public function header(string $name): ?string
        {
            return strtolower($name) === 'authorization' ? $this->authorization : null;
        }

        public function rule(): Rule
        {
            return $this->rule;
        }
    }

    final class Rule
    {
        private $meta;
        private $name;

        public function __construct(array $meta = [], string $name = '')
        {
            $this->meta = $meta;
            $this->name = $name;
        }

        public function getOption(string $name): ?array
        {
            return $name === 'meta' ? $this->meta : null;
        }

        public function getName(): string
        {
            return $this->name;
        }
    }
}

namespace app\api {
    final class ApiException extends \Exception
    {
        public const CODE_USER_NOT_FOUND = 1001;
        public const CODE_USER_BANNED = 1002;
        public const CODE_PASSWORD_MISMATCH = 1003;
        public const CODE_USER_ALREADY_EXISTS = 1004;
        public const CODE_LOGIN_FAILED = 1005;
        public const CODE_PERMISSION_DENIED = 1101;
        public const CODE_PARAM_INVALID = 9002;

        private $httpStatus;

        public function __construct(string $message, int $code, int $httpStatus)
        {
            $this->httpStatus = $httpStatus;
            parent::__construct($message, $code);
        }

        public static function badRequest(string $message, int $code): self
        {
            return new self($message, $code, 400);
        }

        public static function unauthorized(string $message, int $code = self::CODE_LOGIN_FAILED): self
        {
            return new self($message, $code, 401);
        }

        public static function forbidden(string $message, int $code = self::CODE_PERMISSION_DENIED): self
        {
            return new self($message, $code, 403);
        }

        public function exceptionHandle(): \Tests\Auth\Response
        {
            return new \Tests\Auth\Response($this->httpStatus, $this->getMessage());
        }
    }

    final class ApiResponse
    {
        public static function createUnauthorized(string $message): \Tests\Auth\Response
        {
            return new \Tests\Auth\Response(401, $message);
        }

        public static function createForbidden(string $message): \Tests\Auth\Response
        {
            return new \Tests\Auth\Response(403, $message);
        }
    }
}

namespace app\common\service {
    final class Config
    {
        public static $visitorMode = false;

        public static function get(string $key)
        {
            return $key === 'core.visitor_mode' ? self::$visitorMode : null;
        }
    }
}

namespace app\api\service\Rbac {
    final class RBAC
    {
        public static $capabilitiesByRoles = [];

        public static function getUserCapabilities(array $rolesId): array
        {
            $key = implode(',', $rolesId);

            return self::$capabilitiesByRoles[$key] ?? [];
        }
    }
}

namespace app\api\service\User {
    final class Users
    {
        public static $user = null;

        public static function Get(int $id)
        {
            return self::$user;
        }
    }
}

namespace app\api\model {
    final class UsersQuery
    {
        public function whereOr(string $field, string $value): self
        {
            return $this;
        }

        public function find()
        {
            return Users::$found;
        }
    }

    final class Users
    {
        public static $found = null;
        public static $created = [];
        public static $nextId = 20;

        public static function where(string $field, string $value): UsersQuery
        {
            return new UsersQuery();
        }

        public static function create(array $data): \Tests\Auth\User
        {
            self::$created = $data;

            return new \Tests\Auth\User(self::$nextId, (int) $data['status'], $data['password'], $data['roles_id']);
        }
    }
}

namespace app\api\service\Captcha {
    final class Captcha
    {
    }
}

namespace {
    use app\api\ApiException;
    use app\api\middleware\JwtAuthCheck;
    use app\api\middleware\PermissionCheck;
    use app\api\model\Users as UsersModel;
    use app\api\service\Rbac\RBAC;
    use app\api\service\User\Session;
    use app\api\service\User\Users as UsersService;
    use app\common\service\Config;
    use Tests\Auth\Request;
    use Tests\Auth\Response;
    use Tests\Auth\Rule;
    use Tests\Auth\TokenService;
    use Tests\Auth\User;

    require dirname(__DIR__, 2) . '/app/api/service/User/Session.php';
    require dirname(__DIR__, 2) . '/app/api/middleware/JwtAuthCheck.php';
    require dirname(__DIR__, 2) . '/app/api/middleware/PermissionCheck.php';

    $tests = [];

    $test = static function (string $name, \Closure $callback) use (&$tests): void {
        $tests[$name] = $callback;
    };

    $assertSame = static function ($expected, $actual, string $message = ''): void {
        if ($expected !== $actual) {
            throw new \RuntimeException(
                ($message !== '' ? $message . ': ' : '')
                . 'expected ' . var_export($expected, true)
                . ', got ' . var_export($actual, true)
            );
        }
    };

    $assertThrows = static function (\Closure $callback, int $code, string $message) use ($assertSame): void {
        try {
            $callback();
        } catch (ApiException $exception) {
            $assertSame($code, $exception->getCode());
            $assertSame($message, $exception->getMessage());

            return;
        }

        throw new \RuntimeException('Expected ApiException was not thrown');
    };

    $reset = static function (): void {
        Config::$visitorMode = false;
        TokenService::$verified = ['uid' => 10];
        TokenService::$failure = null;
        TokenService::$signed = [];
        RBAC::$capabilitiesByRoles = [];
        UsersService::$user = null;
        UsersModel::$found = null;
        UsersModel::$created = [];
        global $testRequest;
        $testRequest = null;
    };

    $test('login rejects an unknown user', static function () use ($reset, $assertThrows): void {
        $reset();
        $assertThrows(
            static function (): void {
                (new Session(new TokenService()))->login('missing@example.com', 'secret');
            },
            ApiException::CODE_USER_NOT_FOUND,
            '用户不存在'
        );
    });

    $test('login rejects a disabled user', static function () use ($reset, $assertThrows): void {
        $reset();
        UsersModel::$found = new User(10, 1, password_hash('secret', PASSWORD_DEFAULT));
        $assertThrows(
            static function (): void {
                (new Session(new TokenService()))->login('disabled@example.com', 'secret');
            },
            ApiException::CODE_USER_BANNED,
            '您的账户已被封禁或未激活'
        );
    });

    $test('login rejects a mismatched password', static function () use ($reset, $assertThrows): void {
        $reset();
        UsersModel::$found = new User(10, 0, password_hash('correct', PASSWORD_DEFAULT));
        $assertThrows(
            static function (): void {
                (new Session(new TokenService()))->login('user@example.com', 'wrong');
            },
            ApiException::CODE_PASSWORD_MISMATCH,
            '密码不匹配'
        );
    });

    $test('login signs the authenticated user id', static function () use ($reset, $assertSame): void {
        $reset();
        UsersModel::$found = new User(10, 0, password_hash('secret', PASSWORD_DEFAULT));
        $result = (new Session(new TokenService()))->login('user@example.com', 'secret');
        $assertSame('signed-token-10', $result['token']);
        $assertSame(['uid' => 10], TokenService::$signed);
    });

    $test('registration rejects an empty password', static function () use ($reset, $assertThrows): void {
        $reset();
        $assertThrows(
            static function (): void {
                (new Session(new TokenService()))->register('1000000000', 'USER1', 'user@example.com', '', '');
            },
            ApiException::CODE_PARAM_INVALID,
            '密码不得为空'
        );
    });

    $test('registration rejects an existing account', static function () use ($reset, $assertThrows): void {
        $reset();
        UsersModel::$found = new User(10, 0, 'hash');
        $assertThrows(
            static function (): void {
                (new Session(new TokenService()))->register('1000000000', 'USER1', 'user@example.com', '', 'secret');
            },
            ApiException::CODE_USER_ALREADY_EXISTS,
            '邮箱或手机号已存在'
        );
    });

    $test('registration assigns the normal user role', static function () use ($reset, $assertSame): void {
        $reset();
        $result = (new Session(new TokenService()))->register('1000000000', 'USER1', 'user@example.com', '', 'secret');
        $assertSame('signed-token-20', $result['token']);
        $assertSame([2], UsersModel::$created['roles_id']);
        $assertSame(0, UsersModel::$created['status']);
    });

    $test('guest login reuses the hourly guest account', static function () use ($reset, $assertSame): void {
        $reset();
        UsersModel::$found = new User(12, 0, password_hash('123456', PASSWORD_DEFAULT), [3]);
        $result = (new Session(new TokenService()))->guest('127.0.0.1');
        $assertSame('signed-token-12', $result['token']);
        $assertSame([], UsersModel::$created);
    });

    $test('guest login creates the guest role when absent', static function () use ($reset, $assertSame): void {
        $reset();
        (new Session(new TokenService()))->guest('127.0.0.1');
        $assertSame([3], UsersModel::$created['roles_id']);
        $assertSame(0, UsersModel::$created['status']);
    });

    $test('missing token is unauthorized when visitor mode is off', static function () use ($reset, $assertSame): void {
        $reset();
        $request = new Request();
        $response = (new JwtAuthCheck(new TokenService()))->handle($request, static function (): Response {
            return new Response();
        });
        $assertSame(401, $response->status);
        $assertSame('请先登入', $response->message);
    });

    $test('missing token becomes a guest context when visitor mode is on', static function () use ($reset, $assertSame): void {
        $reset();
        Config::$visitorMode = true;
        RBAC::$capabilitiesByRoles['3'] = ['cards.read'];
        $request = new Request();
        $response = (new JwtAuthCheck(new TokenService()))->handle($request, static function (): Response {
            return new Response();
        });
        $assertSame(200, $response->status);
        $assertSame(0, $request->uid);
        $assertSame([3], $request->rolesId);
        $assertSame(['cards.read'], $request->caps);
    });

    $test('invalid token is unauthorized when visitor mode is off', static function () use ($reset, $assertSame): void {
        $reset();
        TokenService::$failure = new \RuntimeException('签名不正确');
        $request = new Request('Bearer invalid');
        $response = (new JwtAuthCheck(new TokenService()))->handle($request, static function (): Response {
            return new Response();
        });
        $assertSame(401, $response->status);
        $assertSame('签名不正确', $response->message);
    });

    $test('valid token creates an authenticated context and emits renewal', static function () use ($reset, $assertSame): void {
        $reset();
        TokenService::$verified = ['uid' => 10, '_new_token' => 'renewed-token'];
        UsersService::$user = new User(10, 0, 'hash', '[2]');
        RBAC::$capabilitiesByRoles['2'] = ['users.read'];
        $request = new Request('Bearer valid');
        $response = (new JwtAuthCheck(new TokenService()))->handle($request, static function (): Response {
            return new Response();
        });
        $assertSame(10, $request->uid);
        $assertSame([2], $request->rolesId);
        $assertSame(['users.read'], $request->caps);
        $assertSame('renewed-token', $response->headers['X-New-Token']);
    });

    $test('missing token user is rejected', static function () use ($reset, $assertSame): void {
        $reset();
        $request = new Request('Bearer valid');
        $response = (new JwtAuthCheck(new TokenService()))->handle($request, static function (): Response {
            return new Response();
        });
        $assertSame(401, $response->status);
        $assertSame('用户不存在', $response->message);
    });

    $test('permission accepts any matching capability', static function () use ($reset, $assertSame): void {
        $reset();
        RBAC::$capabilitiesByRoles['2'] = ['users.read'];
        global $testRequest;
        $testRequest = new Request(null, new Rule(['caps' => ['users.read', 'users.read.all']], 'users.list'));
        $testRequest->rolesId = [2];
        $response = (new PermissionCheck())->handle($testRequest, static function (): Response {
            return new Response(204);
        });
        $assertSame(204, $response->status);
        $assertSame(['users.read'], $testRequest->caps);
    });

    $test('permission rejects a context without required capability', static function () use ($reset, $assertSame): void {
        $reset();
        RBAC::$capabilitiesByRoles['2'] = ['cards.read'];
        global $testRequest;
        $testRequest = new Request(null, new Rule(['caps' => ['users.read']], 'users.list'));
        $testRequest->rolesId = [2];
        $response = (new PermissionCheck())->handle($testRequest, static function (): Response {
            return new Response();
        });
        $assertSame(403, $response->status);
        $assertSame('权限不足', $response->message);
    });

    $failures = 0;
    foreach ($tests as $name => $callback) {
        try {
            $callback();
            fwrite(STDOUT, "PASS {$name}\n");
        } catch (\Throwable $exception) {
            $failures++;
            fwrite(STDERR, "FAIL {$name}: {$exception->getMessage()}\n");
        }
    }

    if ($failures > 0) {
        fwrite(STDERR, "{$failures} auth behavior baseline test(s) failed.\n");
        exit(1);
    }

    fwrite(STDOUT, count($tests) . " auth behavior baseline tests passed.\n");
}
