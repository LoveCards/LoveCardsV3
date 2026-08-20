<?php
declare(strict_types=1);

namespace app\shared\SystemPaths;

/** Read-only bridge for environment, persistent installer state and legacy defaults. */
final class PersistentConfiguration
{
    /** @return array{hostname?:string,database?:string,username?:string,password?:string,hostport?:string,charset?:string} */
    public static function database(string $activeReleaseRoot): array
    {
        $root = self::paths($activeReleaseRoot)->configRoot();
        if (is_link($root)) throw new \RuntimeException('持久数据库配置目录不能是 symlink');
        $path = $root . DIRECTORY_SEPARATOR . 'database.json';
        if (!file_exists($path) && !is_link($path)) return [];
        if (is_link($path) || !is_file($path)) throw new \RuntimeException('持久数据库配置不是安全的普通文件');
        $size = filesize($path);
        if ($size === false || $size < 2 || $size > 65536) throw new \RuntimeException('持久数据库配置大小不合法');
        try {
            $document = json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('持久数据库配置不是合法 JSON', 0, $exception);
        }
        if (!is_array($document) || ($document['schema_version'] ?? null) !== 1
            || ($document['driver'] ?? null) !== 'mysql' || !is_array($document['connection'] ?? null)) {
            throw new \RuntimeException('持久数据库配置协议不受支持');
        }
        $connection = $document['connection'];
        foreach (['hostname', 'database', 'username', 'password', 'hostport', 'charset'] as $field) {
            if (!array_key_exists($field, $connection) || !is_string($connection[$field])) {
                throw new \RuntimeException('持久数据库配置字段不完整');
            }
        }
        if ($connection['hostname'] === '' || $connection['database'] === '' || $connection['username'] === ''
            || strlen($connection['hostname']) > 255 || strlen($connection['database']) > 128 || strlen($connection['username']) > 128
            || preg_match('/[\x00-\x1F\x7F]/', $connection['hostname']) === 1
            || preg_match('/[\x00-\x1F\x7F]/', $connection['database']) === 1
            || preg_match('/[\x00-\x1F\x7F]/', $connection['username']) === 1
            || strlen($connection['password']) > 4096 || str_contains($connection['password'], "\0")
            || preg_match('/^[0-9]{1,5}$/', $connection['hostport']) !== 1
            || (int) $connection['hostport'] < 1 || (int) $connection['hostport'] > 65535
            || $connection['charset'] !== 'utf8mb4') {
            throw new \RuntimeException('持久数据库配置值不合法');
        }
        return $connection;
    }

    /** @return array{private:string,public:string} */
    public static function jwtKeyDefaults(string $activeReleaseRoot): array
    {
        $root = self::paths($activeReleaseRoot)->keysRoot();
        if (is_link($root)) throw new \RuntimeException('持久密钥目录不能是 symlink');
        $private = $root . DIRECTORY_SEPARATOR . 'private.pem';
        $public = $root . DIRECTORY_SEPARATOR . 'public.pem';
        if (!is_link($private) && !is_link($public) && is_file($private) && is_file($public)) {
            return ['private' => $private, 'public' => $public];
        }
        return ['private' => 'config/rsa/private.pem', 'public' => 'config/rsa/public.pem'];
    }

    public static function paths(string $activeReleaseRoot): SystemPaths
    {
        $resolved = getenv('LC_INSTANCE_ROOT_RESOLVED');
        $configured = getenv('LC_INSTANCE_ROOT');
        $instanceRoot = is_string($resolved) && trim($resolved) !== ''
            ? trim($resolved)
            : (is_string($configured) && trim($configured) !== ''
                ? trim($configured)
                : dirname(rtrim($activeReleaseRoot, '/\\')) . DIRECTORY_SEPARATOR . '.lovecards-instance');
        return new SystemPaths($instanceRoot, $activeReleaseRoot);
    }

    public static function resolvePath(string $configuredPath, string $activeReleaseRoot): string
    {
        $configuredPath = trim($configuredPath);
        if ($configuredPath === '' || str_contains($configuredPath, "\0")) {
            throw new \RuntimeException('配置路径不合法');
        }
        if ($configuredPath[0] === '/' || preg_match('/^[A-Za-z]:[\\\\\/]/', $configuredPath) === 1
            || str_starts_with($configuredPath, '\\\\')) {
            return $configuredPath;
        }
        $segments = preg_split('#[\\\\/]#', $configuredPath) ?: [];
        if (in_array('..', $segments, true)) throw new \RuntimeException('相对配置路径不能越出 active Release');
        return rtrim($activeReleaseRoot, '/\\') . DIRECTORY_SEPARATOR
            . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $configuredPath);
    }
}
