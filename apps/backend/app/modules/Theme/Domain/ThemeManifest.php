<?php

namespace app\modules\Theme\Domain;

final class ThemeManifest
{
    private const ID_PATTERN = '/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,63}$/';
    private const SEMVER_PATTERN = '/^(0|[1-9][0-9]*)\\.(0|[1-9][0-9]*)\\.(0|[1-9][0-9]*)(?:-(?:0|[1-9A-Za-z-][0-9A-Za-z-]*)(?:\\.(?:0|[1-9A-Za-z-][0-9A-Za-z-]*))*)?(?:\\+(?:0|[1-9A-Za-z-][0-9A-Za-z-]*)(?:\\.(?:0|[1-9A-Za-z-][0-9A-Za-z-]*))*)?$/';

    public static function load(string $themePath): array
    {
        $themePath = realpath($themePath) ?: $themePath;
        $manifestPath = rtrim($themePath, '/\\') . DIRECTORY_SEPARATOR . 'theme.json';
        if (!is_file($manifestPath)) throw new \RuntimeException('主题缺少 theme.json');
        try {
            $data = json_decode((string)file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            throw new \RuntimeException('theme.json 不是有效 JSON', 0, $e);
        }
        if (!is_array($data)) throw new \RuntimeException('theme.json 必须是对象');
        self::validate($data, $themePath);
        $data['_path'] = $themePath;
        return $data;
    }

    public static function validate(array $manifest, string $themePath): void
    {
        if (($manifest['schemaVersion'] ?? null) !== 1) throw new \RuntimeException('不支持的主题协议版本');
        $id = (string)($manifest['id'] ?? '');
        if (!preg_match(self::ID_PATTERN, $id)) throw new \RuntimeException('主题 id 不合法');
        if (!is_string($manifest['name'] ?? null) || trim($manifest['name']) === '' || mb_strlen($manifest['name']) > 120) throw new \RuntimeException('主题 name 不合法');
        $version = $manifest['version'] ?? null;
        if (!is_string($version) || !self::isSemver($version)) throw new \RuntimeException('主题版本必须是 SemVer');
        if (($manifest['mode'] ?? null) !== 'spa') throw new \RuntimeException('主题 mode 只能是 spa');
        foreach (['engine', 'settings', 'routes', 'notFound'] as $legacy) if (array_key_exists($legacy, $manifest)) throw new \RuntimeException('主题 Manifest 不再支持字段: ' . $legacy);
        $entry = self::validateRelativePath($manifest['entry'] ?? null, 'entry');
        if (strtolower(pathinfo($entry, PATHINFO_EXTENSION)) !== 'html') throw new \RuntimeException('主题 entry 必须是 HTML 文件');
        $entryPath = self::resolveFile($themePath, $entry, '主题 entry 文件不存在');
        self::validateHtml((string)file_get_contents($entryPath));
        if (isset($manifest['author']) && (!is_string($manifest['author']) || mb_strlen($manifest['author']) > 120)) throw new \RuntimeException('主题 author 不合法');
        if (isset($manifest['description']) && (!is_string($manifest['description']) || mb_strlen($manifest['description']) > 500)) throw new \RuntimeException('主题 description 不合法');
        self::validateRequires($manifest['requires'] ?? []);
        self::validateCompatibility($manifest['compatibility'] ?? []);
        self::validateConfig($manifest['config'] ?? []);
    }

    public static function isSemver(string $version): bool { return preg_match(self::SEMVER_PATTERN, $version) === 1; }

    public static function validateRelativePath($value, string $field = 'path'): string
    {
        if (!is_string($value) || $value === '' || strpos($value, "\0") !== false || strpos($value, '\\') !== false) throw new \RuntimeException('主题 ' . $field . ' 路径不合法');
        if ($value[0] === '/' || preg_match('/^[A-Za-z]:/', $value)) throw new \RuntimeException('主题 ' . $field . ' 不允许绝对路径');
        foreach (explode('/', $value) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || preg_match('/[<>:"|?*]/', $segment) || preg_match('/[. ]$/', $segment)) throw new \RuntimeException('主题 ' . $field . ' 路径不合法');
        }
        return $value;
    }

    public static function resolveEntry(array $manifest): string
    {
        return self::resolveFile((string)$manifest['_path'], (string)$manifest['entry'], '主题 entry 文件不存在');
    }

    public static function assetRoot(array $manifest): string
    {
        $entry = self::validateRelativePath((string)$manifest['entry'], 'entry');
        $directory = dirname($entry);
        return $directory === '.' ? (string)$manifest['_path'] : self::resolveDirectory((string)$manifest['_path'], $directory);
    }

    public static function normalizePath(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: '/';
        if (strpos($path, "\0") !== false) throw new \RuntimeException('主题路径不合法');
        $path = '/' . trim(rawurldecode($path), '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public static function isReservedPath(string $path): bool
    {
        $path = self::normalizePath($path);
        foreach (['/api', '/admin', '/system', '/storage', '/media', '/_lc'] as $prefix) if ($path === $prefix || strpos($path, $prefix . '/') === 0) return true;
        return false;
    }

    private static function resolveFile(string $themePath, string $relative, string $message): string
    {
        $root = realpath($themePath);
        $file = realpath(rtrim($themePath, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if ($root === false || $file === false || strpos($file, $root . DIRECTORY_SEPARATOR) !== 0 || !is_file($file)) throw new \RuntimeException($message);
        return $file;
    }

    private static function resolveDirectory(string $themePath, string $relative): string
    {
        $root = realpath($themePath);
        $directory = realpath(rtrim($themePath, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative));
        if ($root === false || $directory === false || strpos($directory, $root . DIRECTORY_SEPARATOR) !== 0 || !is_dir($directory)) throw new \RuntimeException('主题 entry 目录不存在');
        return $directory;
    }

    private static function validateRequires($requires): void
    {
        if (!is_array($requires)) throw new \RuntimeException('主题 requires 必须是对象');
        if (isset($requires['sdk']) && (!is_string($requires['sdk']) || !preg_match('/^[0-9A-Za-z.*+^<>=~| -]+$/', $requires['sdk']))) throw new \RuntimeException('主题 requires.sdk 不合法');
    }

    private static function validateCompatibility($compatibility): void
    {
        if (!is_array($compatibility)) throw new \RuntimeException('主题 compatibility 必须是对象');
        foreach (['min', 'max'] as $key) {
            if (!isset($compatibility[$key])) continue;
            $value = (string)$compatibility[$key];
            if (!self::isSemver($value) && !preg_match('/^(0|[1-9][0-9]*)\\.x$/', $value)) throw new \RuntimeException('主题 compatibility.' . $key . ' 不合法');
        }
    }

    private static function validateConfig($config): void
    {
        if (!is_array($config)) throw new \RuntimeException('主题 config 必须是对象');
        foreach ($config as $key => $definition) {
            if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/', (string)$key) || !is_array($definition)) throw new \RuntimeException('主题配置项定义错误: ' . $key);
            $type = $definition['type'] ?? null;
            if (!in_array($type, ['string', 'boolean', 'integer', 'number'], true) || !array_key_exists('default', $definition)) throw new \RuntimeException('主题配置项定义错误: ' . $key);
            if ($type === 'string' && !is_string($definition['default'])) throw new \RuntimeException('主题配置默认值类型错误: ' . $key);
            if ($type === 'boolean' && !is_bool($definition['default'])) throw new \RuntimeException('主题配置默认值类型错误: ' . $key);
            if ($type === 'integer' && (!is_int($definition['default']) || is_bool($definition['default']))) throw new \RuntimeException('主题配置默认值类型错误: ' . $key);
            if ($type === 'number' && ((!is_int($definition['default']) && !is_float($definition['default'])) || is_bool($definition['default']))) throw new \RuntimeException('主题配置默认值类型错误: ' . $key);
            if (isset($definition['format']) && !in_array($definition['format'], ['color', 'uri', 'email'], true)) throw new \RuntimeException('主题配置格式不支持: ' . $key);
            if (isset($definition['maxLength']) && (!is_int($definition['maxLength']) || $definition['maxLength'] < 0)) throw new \RuntimeException('主题配置 maxLength 不合法: ' . $key);
        }
    }

    private static function validateHtml(string $html): void
    {
        if (preg_match('/<script(?![^>]*\\bsrc\\s*=)[^>]*>/i', $html) || preg_match('/\\son[a-z]+\\s*=/i', $html)) throw new \RuntimeException('主题 HTML 不允许内联脚本或内联事件');
        if (preg_match('/(?:src|href)\\s*=\\s*["\']https?:\\/\\//i', $html)) throw new \RuntimeException('主题 HTML 不允许远程脚本或样式');
        if (preg_match('/<base\\b/i', $html)) throw new \RuntimeException('主题 HTML 不允许自定义 base');
    }
}
