<?php

namespace app\modules\Theme\Runtime;

use app\modules\Theme\Domain\Hostname;
use app\modules\Theme\Domain\ThemeManifest;
use app\modules\Theme\Infrastructure\FilesystemThemeCatalog;
use app\modules\Theme\Infrastructure\ThemeBindingRepository;
use think\Request;
use think\Response;

final class ThemeGateway
{
    private const ASSET_PREFIX = '/_lc/theme-assets/';
    private const MIME = [
        'js' => 'application/javascript; charset=utf-8',
        'mjs' => 'application/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'map' => 'application/json; charset=utf-8',
        'wasm' => 'application/wasm',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'avif' => 'image/avif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mov' => 'video/quicktime',
        'm4v' => 'video/x-m4v',
        'mp3' => 'audio/mpeg',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
    ];

    public static function page(Request $request): Response
    {
        $method = strtoupper($request->method());
        if (!in_array($method, ['GET', 'HEAD'], true)) return Response::create('Method Not Allowed', 'html', 405)->header(['Allow' => 'GET, HEAD']);
        $binding = self::binding($request);
        if ($binding === null) return self::error('Host 未绑定主题', 421, $method === 'HEAD');
        $theme = self::theme($binding);
        if ($theme === null) return self::error('主题 Release 不存在或不可用', 503, $method === 'HEAD');

        try { $entry = ThemeManifest::resolveEntry($theme); }
        catch (\Throwable $e) { return self::error('主题 entry 不可用', 503, $method === 'HEAD'); }
        $release = (string)$binding['release_key'];
        $assetsBase = self::ASSET_PREFIX . rawurlencode((string)$binding['theme_id']) . '/' . rawurlencode($release) . '/';
        $config = self::config($theme, $binding['config_overrides'] ?? []);
        $bootstrap = [
            'apiUrl' => '/api',
            'theme' => (string)$binding['theme_id'],
            'mode' => 'spa',
            'release' => $release,
            'assetsBase' => $assetsBase,
            'config' => $config,
        ];
        try { $json = json_encode($bootstrap, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR); }
        catch (\Throwable $e) { return self::error('主题配置无法序列化', 500, $method === 'HEAD'); }
        $html = file_get_contents($entry);
        if ($html === false) return self::error('主题 entry 无法读取', 503, $method === 'HEAD');
        $entryRelative = (string)$theme['entry'];
        $entryDirectory = dirname($entryRelative);
        $basePath = $assetsBase . ($entryDirectory === '.' ? '' : trim(str_replace('\\', '/', $entryDirectory), '/') . '/');
        $baseTag = '<base href="' . htmlspecialchars($basePath, ENT_QUOTES, 'UTF-8') . '">';
        $bootstrapTag = '<script id="lc-bootstrap" type="application/json">' . $json . '</script>';
        $html = self::injectHead($html, $baseTag . $bootstrapTag);
        $response = Response::create($html, 'html', 200)->header([
            'Content-Type' => 'text/html; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; connect-src 'self'; img-src 'self' data: blob:; font-src 'self' data:; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'",
        ]);
        if ($method === 'HEAD') $response->content('');
        return $response;
    }

    public static function asset(Request $request): Response
    {
        if (!in_array(strtoupper($request->method()), ['GET', 'HEAD'], true)) return Response::create('Method Not Allowed', 'html', 405)->header(['Allow' => 'GET, HEAD']);
        $path = '/' . ltrim((string)$request->pathinfo(), '/');
        if (strpos($path, self::ASSET_PREFIX) !== 0) return self::error('主题资源不存在', 404, strtoupper($request->method()) === 'HEAD');
        $relative = substr($path, strlen(self::ASSET_PREFIX));
        $parts = explode('/', $relative, 3);
        if (count($parts) !== 3 || $parts[0] === '' || $parts[1] === '' || $parts[2] === '') return self::error('主题资源不存在', 404, strtoupper($request->method()) === 'HEAD');
        $themeId = rawurldecode($parts[0]);
        $release = rawurldecode($parts[1]);
        $binding = self::binding($request);
        if ($binding === null || $binding['theme_id'] !== $themeId || (string)$binding['release_key'] !== $release) return self::error('主题资源不存在', 404, strtoupper($request->method()) === 'HEAD');
        $theme = self::theme($binding);
        if ($theme === null) return self::error('主题资源不存在', 404, strtoupper($request->method()) === 'HEAD');
        $asset = rawurldecode($parts[2]);
        try { $asset = ThemeManifest::validateRelativePath($asset, '资源'); }
        catch (\Throwable $e) { return self::error('主题资源路径不合法', 403, strtoupper($request->method()) === 'HEAD'); }
        if (preg_match('~(^|/)(?:theme\.json|README(?:\.md)?|docs(?:/|$)|\.[^/]+)($|/)~i', $asset)) return self::error('主题资源不可访问', 404, strtoupper($request->method()) === 'HEAD');
        $themePath = (string)$theme['_path'];
        $assetRoot = ThemeManifest::assetRoot($theme);
        $file = realpath($themePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $asset));
        if ($file === false || strpos($file, $assetRoot . DIRECTORY_SEPARATOR) !== 0 || !is_file($file)) return self::error('主题资源不存在', 404, strtoupper($request->method()) === 'HEAD');
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!isset(self::MIME[$ext])) return self::error('主题资源类型不允许', 404, strtoupper($request->method()) === 'HEAD');
        $etag = '"' . sha1($release . '|' . $asset . '|' . (string)filesize($file)) . '"';
        $cache = 'public, max-age=31536000, immutable';
        $headers = ['Content-Type' => self::MIME[$ext], 'Cache-Control' => $cache, 'ETag' => $etag, 'X-Content-Type-Options' => 'nosniff'];
        $ifNoneMatch = (string)$request->header('If-None-Match');
        if (in_array($etag, array_map('trim', explode(',', $ifNoneMatch)), true)) return Response::create('', 'html', 304)->header($headers);
        $body = file_get_contents($file);
        if ($body === false) return self::error('主题资源无法读取', 500, strtoupper($request->method()) === 'HEAD');
        $response = Response::create($body, 'html', 200)->header($headers);
        if (strtoupper($request->method()) === 'HEAD') $response->content('');
        return $response;
    }

    public static function activeContext(Request $request): array
    {
        $binding = self::binding($request);
        if ($binding === null) return [];
        $theme = self::theme($binding);
        if ($theme === null) return [];
        return [
            'theme' => [
                'id' => $binding['theme_id'],
                'name' => $theme['name'],
                'version' => $theme['version'],
                'mode' => 'spa',
                'release' => $binding['release_key'],
            ],
            'config_schema' => $theme['config'] ?? [],
            'config_values' => self::config($theme, $binding['config_overrides'] ?? []),
        ];
    }

    private static function binding(Request $request): ?array
    {
        try { $host = Hostname::normalize((string)$request->host()); }
        catch (\Throwable $e) { return null; }
        try { return (new ThemeBindingRepository())->find($host); }
        catch (\Throwable $e) { return null; }
    }

    private static function theme(array $binding): ?array
    {
        return FilesystemThemeCatalog::inspect((string)$binding['theme_id'], (string)$binding['release_key']);
    }

    private static function config(array $theme, array $overrides): array
    {
        $config = [];
        foreach (($theme['config'] ?? []) as $key => $definition) $config[$key] = $definition['default'] ?? null;
        foreach ($overrides as $key => $value) if (array_key_exists($key, $config)) $config[$key] = $value;
        return $config;
    }

    private static function injectHead(string $html, string $content): string
    {
        $result = preg_replace('/(<head(?:\\s[^>]*)?>)/i', '$1' . $content, $html, 1, $count);
        if ($count > 0) return (string)$result;
        return $content . $html;
    }

    private static function error(string $message, int $status, bool $head = false): Response
    {
        $response = Response::create('<h1>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</h1>', 'html', $status)->header(['Content-Type' => 'text/html; charset=utf-8', 'X-Content-Type-Options' => 'nosniff']);
        if ($head) $response->content('');
        return $response;
    }
}
