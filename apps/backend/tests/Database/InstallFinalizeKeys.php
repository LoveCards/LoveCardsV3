<?php
declare(strict_types=1);

// 用真实安装 Host、密钥文件和原子安装锁验证终结流程；数据库前置条件使用替身。
namespace app\modules\Operations\SystemLifecycle\Application {
    final class SystemLifecycleCoordinator {
        public static bool $complete = true;
        public function assertInstalledSchemaComplete(string $directory): void {
            if (!self::$complete) throw new \RuntimeException('fixture schema incomplete');
        }
    }
}
namespace app\modules\Platform\Config\Public {
    final class Config { public static function init(): array { return []; } }
}
namespace app\modules\Platform\Auth\Public {
    final class InstallationAccount {
        public static int $calls = 0;
        public static function setRootPassword(string $password): void {
            if (strlen($password) < 12) throw new \InvalidArgumentException('password too short');
            self::$calls++;
        }
    }
}
namespace app\modules\Platform\Rbac\Service {
    final class Roles { public static function seedSystemCapabilities(): void {} }
}
namespace app\modules\Platform\Files\Application {
    final class UploadLimits { public static function materialize(array $seeded): void {} }
}
namespace {
    $root = dirname(__DIR__, 2);
    require $root . '/vendor/autoload.php';
    require $root . '/vendor/topthink/framework/src/helper.php';
    if (!extension_loaded('openssl')) throw new \RuntimeException('Install finalization test requires the OpenSSL extension');
    $application = new \think\App($root);
    \think\Container::setInstance($application);
    $temporary = sys_get_temp_dir() . '/lc-install-finalize-' . bin2hex(random_bytes(6));
    $paths = new \app\shared\SystemPaths\SystemPaths($temporary, $root);
    $application->instance(\app\shared\SystemPaths\SystemPaths::class, $paths);
    mkdir($paths->keysRoot(), 0700, true);
    $privatePath = $paths->keysRoot() . '/private.pem';
    $publicPath = $paths->keysRoot() . '/public.pem';
    $lockPath = $paths->stateRoot() . '/install.lock';
    \think\facade\Config::set(['privateKey' => $privatePath, 'publicKey' => $publicPath, 'alg' => 'RS256'], 'jwt');
    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new \RuntimeException($message);
    };
    $host = new \app\system\controller\Install();
    \think\facade\Request::setMethod('POST');
    \think\facade\Request::withServer(['REQUEST_METHOD' => 'POST']);
    \think\facade\Request::withPost(['admin_password' => 'fixture-strong-password']);
    $rejected = static function (string $label) use ($host, $lockPath, $assert): void {
        $response = $host->PostInstallLock();
        $assert($response->getCode() === 500, $label . ': finalize must fail');
        $assert(!is_file($lockPath), $label . ': installation lock must not be written');
    };
    $keyPair = static function (): array {
        $config = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        // Windows 的发行版 OpenSSL 配置不一定在默认搜索路径。
        $bundled = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';
        if (is_file($bundled)) $config['config'] = $bundled;
        $key = openssl_pkey_new($config);
        if ($key === false || !openssl_pkey_export($key, $private, null, $config)) {
            throw new \RuntimeException('test RSA generation failed');
        }
        return [$private, openssl_pkey_get_details($key)['key']];
    };
    try {
        $rejected('missing keys');
        [$private, $public] = $keyPair();
        file_put_contents($privatePath, $private);
        $rejected('interrupted key pair write');
        file_put_contents($publicPath, 'invalid public key');
        $rejected('malformed public key');
        [, $otherPublic] = $keyPair();
        file_put_contents($publicPath, $otherPublic);
        $rejected('mismatched key pair');
        file_put_contents($publicPath, $public);
        file_put_contents($privatePath, 'invalid private key');
        $rejected('malformed private key');
        file_put_contents($privatePath, str_repeat('x', 1048577));
        $rejected('oversized private key');
        file_put_contents($privatePath, $private);
        \app\modules\Operations\SystemLifecycle\Application\SystemLifecycleCoordinator::$complete = false;
        $rejected('incomplete schema with valid keys');
        \app\modules\Operations\SystemLifecycle\Application\SystemLifecycleCoordinator::$complete = true;
        \think\facade\Config::set(['publicKey' => $paths->keysRoot() . '/unselected.pem'], 'jwt');
        $rejected('configured key differs from persisted key');
        \think\facade\Config::set(['publicKey' => $publicPath], 'jwt');
        mkdir($lockPath, 0700, true);
        $rejected('lock destination unavailable');
        rmdir($lockPath);
        $before = [hash_file('sha256', $privatePath), hash_file('sha256', $publicPath)];
        $response = $host->PostInstallLock();
        $assert($response->getCode() === 200 && is_file($lockPath), 'valid pair must finish installation');
        $assert(\app\modules\Platform\Auth\Public\InstallationAccount::$calls >= 1, 'root password must be provisioned before installation lock');
        $lockHash = hash_file('sha256', $lockPath);
        $response = $host->PostInstallLock();
        $assert($response->getCode() === 500, 'completed installation must remain protected');
        $assert(hash_file('sha256', $lockPath) === $lockHash, 'repeated finalize mutated installation lock');
        $assert([hash_file('sha256', $privatePath), hash_file('sha256', $publicPath)] === $before, 'finalize regenerated keys');
        echo "PASS installation finalization rejects absent, partial, malformed, mismatched and unselected keys; repair is retryable\n";
    } finally {
        foreach ([$lockPath, $privatePath, $publicPath, $paths->stateRoot()] as $file) if (is_file($file)) unlink($file);
        if (is_dir($lockPath)) rmdir($lockPath);
        foreach ([$paths->stateRoot(), $paths->keysRoot(), $paths->sharedRoot(), $temporary] as $directory) {
            if (is_dir($directory)) rmdir($directory);
        }
    }
}
