<?php
declare(strict_types=1);

namespace app\modules\Platform\Auth\Public;

use app\modules\Platform\Auth\Infrastructure\Jwt;
use app\shared\SystemPaths\PersistentConfiguration;
use app\shared\SystemPaths\SystemPaths;
use think\facade\Config;

/** 安装器使用的签名密钥公开能力；不向调用方返回持久密钥内容。 */
final class SigningKeys
{
    public static function verifyPair(string $publicKey, string $privateKey): bool
    {
        return Jwt::verifyRsa($publicKey, $privateKey);
    }

    /** 验证实际持久密钥与 JWT 使用的配置一致；不生成或改写密钥。 */
    public static function assertReady(): void
    {
        $paths = app(SystemPaths::class);
        $root = $paths->keysRoot();
        if (is_link($root) || !is_dir($root)) {
            throw new \RuntimeException('持久签名密钥尚未就绪');
        }
        $configuration = Config::get('jwt');
        $contents = [];
        foreach (['private' => 'privateKey', 'public' => 'publicKey'] as $name => $field) {
            $path = $root . DIRECTORY_SEPARATOR . $name . '.pem';
            clearstatcache(true, $path);
            if (is_link($path) || !is_file($path) || !is_readable($path)) {
                throw new \RuntimeException('持久签名密钥文件缺失或不可读取');
            }
            $size = filesize($path);
            if ($size === false || $size < 1 || $size > 1048576) {
                throw new \RuntimeException('持久签名密钥文件大小不合法');
            }
            $selected = PersistentConfiguration::resolvePath(
                (string) ($configuration[$field] ?? ''),
                $paths->activeReleaseRoot()
            );
            if (is_link($selected) || realpath($selected) !== realpath($path)) {
                throw new \RuntimeException('JWT 配置未使用当前实例的持久签名密钥');
            }
            $value = @file_get_contents($path);
            if (!is_string($value) || strlen($value) !== $size) {
                throw new \RuntimeException('持久签名密钥无法完整读取');
            }
            $contents[$name] = $value;
        }
        if (!self::verifyPair($contents['public'], $contents['private'])) {
            throw new \RuntimeException('持久签名密钥不匹配或无法签验');
        }
    }
}
