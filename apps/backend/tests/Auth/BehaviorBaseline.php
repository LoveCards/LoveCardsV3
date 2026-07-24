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
    require dirname(__DIR__, 2) . '/app/api/application/Auth/AuthUser.php';
    require dirname(__DIR__, 2) . '/app/api/application/Auth/UserRepository.php';
    require dirname(__DIR__, 2) . '/app/api/application/Auth/AuthContext.php';
    require dirname(__DIR__, 2) . '/app/api/application/Auth/VisitorPolicy.php';
    require dirname(__DIR__, 2) . '/app/api/application/Auth/CapabilityProvider.php';
    require dirname(__DIR__, 2) . '/app/api/application/Auth/MissingCredentials.php';
    require dirname(__DIR__, 2) . '/app/api/application/Auth/AuthenticateRequest.php';
    require dirname(__DIR__, 2) . '/app/api/application/Auth/LoginUser.php';
    require dirname(__DIR__, 2) . '/app/api/application/Auth/RegisterUser.php';
}

namespace Tests\Auth {
    final class VisitorPolicy implements \app\api\application\Auth\VisitorPolicy
    {
        public static $enabled = false;

        public function isEnabled(): bool
        {
            return self::$enabled;
        }

        public function roleIds(): array
        {
            return [3];
        }
    }

    final class CapabilityProvider implements \app\api\application\Auth\CapabilityProvider
    {
        public static $byRoles = [];

        public function forRoles(array $roleIds): array
        {
            return self::$byRoles[implode(',', $roleIds)] ?? [];
        }
    }

    final class UserRepository implements \app\api\application\Auth\UserRepository
    {
        public static $byId = null;
        public static $byAccount = null;
        public static $contactExists = false;
        public static $created = [];

        public function findById(int $id): ?\app\api\application\Auth\AuthUser
        {
            return self::$byId;
        }

        public function findByAccount(string $account): ?\app\api\application\Auth\AuthUser
        {
            return self::$byAccount;
        }

        public function contactExists(string $email, string $phone): bool
        {
            return self::$contactExists;
        }

        public function create(
            string $number,
            string $username,
            string $email,
            string $phone,
            string $passwordHash,
            array $roleIds,
            int $status
        ): \app\api\application\Auth\AuthUser {
            self::$created = [
                'number' => $number,
                'username' => $username,
                'email' => $email,
                'phone' => $phone,
                'password_hash' => $passwordHash,
                'roles_id' => $roleIds,
                'status' => $status,
            ];

            return new \app\api\application\Auth\AuthUser(20, $status, $passwordHash, $roleIds);
        }
    }

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

    final class Request
    {
        public $auth = null;
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

namespace app\api\service\Captcha {
    final class Captcha
    {
    }
}

namespace {
    use app\api\ApiException;
    use app\api\application\Auth\AuthContext;
    use app\api\application\Auth\AuthUser;
    use app\api\application\Auth\AuthenticateRequest;
    use app\api\application\Auth\LoginUser;
    use app\api\application\Auth\RegisterUser;
    use app\api\middleware\JwtAuthCheck;
    use app\api\middleware\PermissionCheck;
    use app\api\service\User\Session;
    use Tests\Auth\CapabilityProvider;
    use Tests\Auth\Request;
    use Tests\Auth\Response;
    use Tests\Auth\Rule;
    use Tests\Auth\TokenService;
    use Tests\Auth\UserRepository;
    use Tests\Auth\VisitorPolicy;

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

    $authenticate = static function (): AuthenticateRequest {
        return new AuthenticateRequest(
            new TokenService(),
            new UserRepository(),
            new VisitorPolicy(),
            new CapabilityProvider()
        );
    };

    $reset = static function (): void {
        VisitorPolicy::$enabled = false;
        TokenService::$verified = ['uid' => 10];
        TokenService::$failure = null;
        TokenService::$signed = [];
        CapabilityProvider::$byRoles = [];
        UserRepository::$byId = null;
        UserRepository::$byAccount = null;
        UserRepository::$contactExists = false;
        UserRepository::$created = [];
        global $testRequest;
        $testRequest = null;
    };

    $test('login rejects an unknown user', static function () use ($reset, $assertThrows): void {
        $reset();
        $assertThrows(
            static function (): void {
                (new LoginUser(new TokenService(), new UserRepository()))->execute('missing@example.com', 'secret');
            },
            ApiException::CODE_USER_NOT_FOUND,
            '用户不存在'
        );
    });

    $test('login rejects a disabled user', static function () use ($reset, $assertThrows): void {
        $reset();
        UserRepository::$byAccount = new AuthUser(10, 1, password_hash('secret', PASSWORD_DEFAULT), []);
        $assertThrows(
            static function (): void {
                (new LoginUser(new TokenService(), new UserRepository()))->execute('disabled@example.com', 'secret');
            },
            ApiException::CODE_USER_BANNED,
            '您的账户已被封禁或未激活'
        );
    });

    $test('login rejects a mismatched password', static function () use ($reset, $assertThrows): void {
        $reset();
        UserRepository::$byAccount = new AuthUser(10, 0, password_hash('correct', PASSWORD_DEFAULT), []);
        $assertThrows(
            static function (): void {
                (new LoginUser(new TokenService(), new UserRepository()))->execute('user@example.com', 'wrong');
            },
            ApiException::CODE_PASSWORD_MISMATCH,
            '密码不匹配'
        );
    });

    $test('login signs the authenticated user id', static function () use ($reset, $assertSame): void {
        $reset();
        UserRepository::$byAccount = new AuthUser(10, 0, password_hash('secret', PASSWORD_DEFAULT), []);
        $result = (new LoginUser(new TokenService(), new UserRepository()))->execute('user@example.com', 'secret');
        $assertSame('signed-token-10', $result['token']);
        $assertSame(['uid' => 10], TokenService::$signed);
    });

    $test('registration rejects an empty password', static function () use ($reset, $assertThrows): void {
        $reset();
        $assertThrows(
            static function (): void {
                (new RegisterUser(new TokenService(), new UserRepository()))->execute('1000000000', 'USER1', 'user@example.com', '', '');
            },
            ApiException::CODE_PARAM_INVALID,
            '密码不得为空'
        );
    });

    $test('registration rejects an existing account', static function () use ($reset, $assertThrows): void {
        $reset();
        UserRepository::$contactExists = true;
        $assertThrows(
            static function (): void {
                (new RegisterUser(new TokenService(), new UserRepository()))->execute('1000000000', 'USER1', 'user@example.com', '', 'secret');
            },
            ApiException::CODE_USER_ALREADY_EXISTS,
            '邮箱或手机号已存在'
        );
    });

    $test('registration assigns the normal user role', static function () use ($reset, $assertSame): void {
        $reset();
        $result = (new RegisterUser(new TokenService(), new UserRepository()))->execute('1000000000', 'USER1', 'user@example.com', '', 'secret');
        $assertSame('signed-token-20', $result['token']);
        $assertSame([2], UserRepository::$created['roles_id']);
        $assertSame(0, UserRepository::$created['status']);
    });

    $test('guest login reuses the hourly guest account', static function () use ($reset, $assertSame): void {
        $reset();
        UserRepository::$byAccount = new AuthUser(12, 0, password_hash('123456', PASSWORD_DEFAULT), [3]);
        $login = new LoginUser(new TokenService(), new UserRepository());
        $result = (new Session(new TokenService(), new UserRepository(), $login))->guest('127.0.0.1');
        $assertSame('signed-token-12', $result['token']);
        $assertSame([], UserRepository::$created);
    });

    $test('guest login creates the guest role when absent', static function () use ($reset, $assertSame): void {
        $reset();
        $login = new LoginUser(new TokenService(), new UserRepository());
        (new Session(new TokenService(), new UserRepository(), $login))->guest('127.0.0.1');
        $assertSame([3], UserRepository::$created['roles_id']);
        $assertSame(0, UserRepository::$created['status']);
    });

    $test('missing token is unauthorized when visitor mode is off', static function () use ($reset, $assertSame, $authenticate): void {
        $reset();
        $request = new Request();
        $response = (new JwtAuthCheck($authenticate()))->handle($request, static function (): Response {
            return new Response();
        });
        $assertSame(401, $response->status);
        $assertSame('请先登入', $response->message);
    });

    $test('missing token becomes a guest context when visitor mode is on', static function () use ($reset, $assertSame, $authenticate): void {
        $reset();
        VisitorPolicy::$enabled = true;
        CapabilityProvider::$byRoles['3'] = ['cards.read'];
        $request = new Request();
        $response = (new JwtAuthCheck($authenticate()))->handle($request, static function (): Response {
            return new Response();
        });
        $assertSame(200, $response->status);
        $assertSame(0, $request->uid);
        $assertSame([3], $request->rolesId);
        $assertSame(['cards.read'], $request->caps);
        $assertSame(true, $request->auth->isVisitor());
    });

    $test('invalid token is unauthorized when visitor mode is off', static function () use ($reset, $assertSame, $authenticate): void {
        $reset();
        TokenService::$failure = new \RuntimeException('签名不正确');
        $request = new Request('Bearer invalid');
        $response = (new JwtAuthCheck($authenticate()))->handle($request, static function (): Response {
            return new Response();
        });
        $assertSame(401, $response->status);
        $assertSame('签名不正确', $response->message);
    });

    $test('invalid token becomes a guest context when visitor mode is on', static function () use ($reset, $assertSame, $authenticate): void {
        $reset();
        VisitorPolicy::$enabled = true;
        CapabilityProvider::$byRoles['3'] = ['cards.read'];
        TokenService::$failure = new \RuntimeException('签名不正确');
        $request = new Request('Bearer invalid');
        $response = (new JwtAuthCheck($authenticate()))->handle($request, static function (): Response {
            return new Response();
        });
        $assertSame(200, $response->status);
        $assertSame(true, $request->auth->isVisitor());
        $assertSame(['cards.read'], $request->auth->capabilities());
    });

    $test('valid token creates an authenticated context and emits renewal', static function () use ($reset, $assertSame, $authenticate): void {
        $reset();
        TokenService::$verified = ['uid' => 10, '_new_token' => 'renewed-token'];
        UserRepository::$byId = new AuthUser(10, 0, 'hash', [2]);
        CapabilityProvider::$byRoles['2'] = ['users.read'];
        $request = new Request('Bearer valid');
        $response = (new JwtAuthCheck($authenticate()))->handle($request, static function (): Response {
            return new Response();
        });
        $assertSame(10, $request->uid);
        $assertSame([2], $request->rolesId);
        $assertSame(['users.read'], $request->caps);
        $assertSame('renewed-token', $response->headers['X-New-Token']);
        $assertSame(false, $request->auth->isVisitor());
    });

    $test('missing token user is rejected', static function () use ($reset, $assertSame, $authenticate): void {
        $reset();
        $request = new Request('Bearer valid');
        $response = (new JwtAuthCheck($authenticate()))->handle($request, static function (): Response {
            return new Response();
        });
        $assertSame(401, $response->status);
        $assertSame('用户不存在', $response->message);
    });

    $test('permission accepts any matching capability', static function () use ($reset, $assertSame): void {
        $reset();
        global $testRequest;
        $testRequest = new Request(null, new Rule(['caps' => ['users.read', 'users.read.all']], 'users.list'));
        $testRequest->rolesId = [2];
        $testRequest->auth = AuthContext::authenticated(10, null, [2], ['users.read']);
        $response = (new PermissionCheck())->handle($testRequest, static function (): Response {
            return new Response(204);
        });
        $assertSame(204, $response->status);
    });

    $test('permission rejects a context without required capability', static function () use ($reset, $assertSame): void {
        $reset();
        global $testRequest;
        $testRequest = new Request(null, new Rule(['caps' => ['users.read']], 'users.list'));
        $testRequest->rolesId = [2];
        $testRequest->auth = AuthContext::authenticated(10, null, [2], ['cards.read']);
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
