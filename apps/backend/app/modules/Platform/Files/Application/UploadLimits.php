<?php

namespace app\modules\Platform\Files\Application;

use app\api\ApiException;
// Application 层不得触碰其它模块的 Infrastructure，统一走 Config 模块 Public 门面。
use app\modules\Platform\Config\Public\Config as ConfigService;

/**
 * 场景化上传限额
 *
 * 头像类场景（avatar、profile_cover）受 upload.avatar_* 约束；
 * 内容类场景（card、comment、direct，以及不携带场景的直传路径）受 upload.content_* 约束。
 * 读时回落链：场景新键 → 旧全局键 upload.user_image_* → 内置默认。
 *
 * 错误文案保留「超出上传限制」「文件格式错误」关键词——前台 user-facing-error.js
 * 依赖该正则映射友好提示，调整文案必须同步前台。
 */
final class UploadLimits
{
    private const IDENTITY_SCENES = ['avatar', 'profile_cover'];
    private const DEFAULT_SIZE_MB = 2;
    private const DEFAULT_EXTENSIONS = 'jpg,png,gif,webp,jpeg';

    public static function bucket(string $scene): string
    {
        return in_array($scene, self::IDENTITY_SCENES, true) ? 'avatar' : 'content';
    }

    /**
     * 常规上传闸：校验已上传文件对象的尺寸与扩展名。
     *
     * 扩展名必须取客户端原始文件名（getOriginalExtension）。
     * 真实 multipart 上传落在 /tmp/phpXXXX（无扩展名），框架的 File 类
     * 继承 SplFileInfo，getExtension() 返回磁盘临时名的扩展名（恒为空）——
     * 用它会把所有上传误判为「文件格式错误」，与旧 fileExt 校验的语义也不一致。
     * （BehaviorBaseline E2 会扫描本目录源码中的框架引用字面量，注释也不例外。）
     */
    public static function enforce(object $file, string $scene): void
    {
        self::assertWithin(
            (int) $file->getSize(),
            strtolower((string) $file->getOriginalExtension()),
            self::bucket($scene)
        );
    }

    /**
     * 直传闸：在签发凭证之前按声明的文件名与大小校验；直传不携带场景，归内容类。
     */
    public static function enforcePending(string $filename, int $size): void
    {
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
        self::assertWithin($size, $extension, 'content');
    }

    private static function assertWithin(int $size, string $extension, string $bucket): void
    {
        $limitMb = self::sizeMb($bucket);
        if ($size > $limitMb * 1024 * 1000) {
            throw ApiException::badRequest('文件超出上传限制（单文件不超过 ' . $limitMb . 'MB）');
        }
        if (!in_array($extension, self::extensions($bucket), true)) {
            throw ApiException::badRequest('文件格式错误');
        }
    }

    private static function sizeMb(string $bucket): int
    {
        $size = (int) ConfigService::get('upload.' . $bucket . '_size', 0);
        if ($size <= 0) {
            $size = (int) ConfigService::get('upload.user_image_size', self::DEFAULT_SIZE_MB);
        }

        return $size > 0 ? $size : self::DEFAULT_SIZE_MB;
    }

    /**
     * @return string[]
     */
    private static function extensions(string $bucket): array
    {
        $raw = trim((string) ConfigService::get('upload.' . $bucket . '_ext', ''));
        if ($raw === '') {
            $raw = trim((string) ConfigService::get('upload.user_image_ext', self::DEFAULT_EXTENSIONS));
        }
        if ($raw === '') {
            $raw = self::DEFAULT_EXTENSIONS;
        }

        return array_values(array_filter(array_map(
            static fn ($item) => strtolower(trim((string) $item)),
            explode(',', $raw)
        ), static fn ($item) => $item !== ''));
    }

    /**
     * 幂等物化：schema 注册（ConfigService::init）首次播种场景新键后，
     * 把旧全局键的现存自定义值写入新键，避免存量自定义被文件默认值顶替。
     *
     * @param string[] $seededKeys init 本次在 upload 组新播种的键（可带 upload. 前缀）
     */
    public static function materialize(array $seededKeys): void
    {
        $tracked = ['avatar_size', 'avatar_ext', 'content_size', 'content_ext'];
        $pending = [];
        foreach ($seededKeys as $key) {
            $name = str_starts_with((string) $key, 'upload.') ? substr((string) $key, 7) : (string) $key;
            if (in_array($name, $tracked, true)) {
                $pending[] = $name;
            }
        }
        if ($pending === []) {
            return;
        }

        $rows = ConfigService::getGroup('upload');
        $payload = [];
        foreach ($pending as $key) {
            $legacyKey = str_contains($key, 'size') ? 'user_image_size' : 'user_image_ext';
            if (array_key_exists($legacyKey, $rows)) {
                $payload[$key] = $rows[$legacyKey];
            }
        }
        if ($payload !== []) {
            ConfigService::setGroup('upload', $payload);
        }
    }
}
