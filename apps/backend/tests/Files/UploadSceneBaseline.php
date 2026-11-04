<?php
declare(strict_types=1);

// ============================================================
// LoveCardsV3 — Files 模块「场景化上传限额」行为基线测试
//
// 范围：锁定 UploadLimits 的分桶、回落链、扩展名闸、直传闸与幂等物化
// 运行：@php tests/Files/UploadSceneBaseline.php
// 架构：standalone PHP 脚本（无 PHPUnit / 无框架引导）
// 模式：namespace 替身（ConfigService/ApiException）+ require 生产代码
// ============================================================

// ════════════════════════════════════════════════════════════
// 1. ApiException 替身：仅需 badRequest 工厂与消息可读
// ════════════════════════════════════════════════════════════
namespace app\api
{
    class ApiException extends \Exception
    {
        public static function badRequest(string $message): self
        {
            return new self($message);
        }
    }
}

// ════════════════════════════════════════════════════════════
// 2. ConfigService 替身：内存键值 + setGroup 写入记录
// ════════════════════════════════════════════════════════════
namespace app\modules\Platform\Config\Infrastructure
{
    class ConfigService
    {
        /** @var array<string, mixed> 全组键值（键含组前缀，如 upload.avatar_size） */
        public static array $rows = [];

        /** @var array<int, array<string, mixed>> 记录每次 setGroup 写入 */
        public static array $writes = [];

        public static function get(string $key, $default = null)
        {
            return array_key_exists($key, self::$rows) ? self::$rows[$key] : $default;
        }

        public static function getGroup(string $group): array
        {
            $result = [];
            foreach (self::$rows as $key => $value) {
                if (str_starts_with($key, "{$group}.")) {
                    $result[substr($key, strlen($group) + 1)] = $value;
                }
            }

            return $result;
        }

        public static function setGroup(string $group, array $config): bool
        {
            self::$writes[] = $config;
            foreach ($config as $key => $value) {
                self::$rows["{$group}.{$key}"] = $value;
            }

            return true;
        }

        public static function reset(): void
        {
            self::$rows = [];
            self::$writes = [];
        }
    }
}

// ════════════════════════════════════════════════════════════
// 3. 载入生产代码
// ════════════════════════════════════════════════════════════
namespace
{
    require __DIR__ . '/../../app/modules/Platform/Files/Application/UploadLimits.php';

    use app\api\ApiException;
    use app\modules\Platform\Config\Infrastructure\ConfigService;
    use app\modules\Platform\Files\Application\UploadLimits;

    /**
     * 构造一个具备 getSize/getExtension 的上传文件替身。
     */
    function uploadedFile(int $size, string $extension): object
    {
        return new class($size, $extension) {
            public function __construct(private int $size, private string $extension)
            {
            }

            public function getSize(): int
            {
                return $this->size;
            }

            public function getExtension(): string
            {
                return $this->extension;
            }
        };
    }

    function expectRejected(callable $callback, string $keyword, string $label): void
    {
        try {
            $callback();
        } catch (ApiException $exception) {
            if (!str_contains($exception->getMessage(), $keyword)) {
                throw new \RuntimeException("{$label}: 错误文案应包含「{$keyword}」，实际：{$exception->getMessage()}");
            }

            return;
        }
        throw new \RuntimeException("{$label}: 预期被拒绝，但通过了闸口");
    }

    $tests = [];

    // ------------------------------------------------------------
    // 分桶归类
    // ------------------------------------------------------------
    $tests['A1: scene bucket classification'] = static function (): void {
        $cases = [
            'avatar' => 'avatar',
            'profile_cover' => 'avatar',
            'card' => 'content',
            'comment' => 'content',
            'direct' => 'content',
            '' => 'content',
            'unknown' => 'content',
        ];
        foreach ($cases as $scene => $bucket) {
            if (UploadLimits::bucket($scene) !== $bucket) {
                throw new \RuntimeException("场景 {$scene} 应归入 {$bucket} 桶");
            }
        }
    };

    // ------------------------------------------------------------
    // 无配置时的内置默认（2MB / 默认扩展名）
    // ------------------------------------------------------------
    $tests['B1: built-in defaults when no config rows'] = static function (): void {
        ConfigService::reset();
        $limit = 2 * 1024 * 1000;
        // 恰好等于上限必须放行（与旧 fileSize 规则一致的边界语义）
        UploadLimits::enforce(uploadedFile($limit, 'jpg'), 'avatar');
        UploadLimits::enforce(uploadedFile($limit, 'webp'), 'card');
        expectRejected(
            static fn () => UploadLimits::enforce(uploadedFile($limit + 1, 'jpg'), 'card'),
            '超出上传限制',
            'B1 超限'
        );
    };

    // ------------------------------------------------------------
    // 回落链：新键缺失 → 旧全局键
    // ------------------------------------------------------------
    $tests['C1: fallback to legacy global keys when scene keys absent'] = static function (): void {
        ConfigService::reset();
        ConfigService::$rows = [
            'upload.user_image_size' => 5,
            'upload.user_image_ext' => 'png',
        ];
        // 5MB 内容图必须放行；旧扩展名 png 之外的 jpg 必须拒绝
        UploadLimits::enforce(uploadedFile(5 * 1024 * 1000, 'png'), 'card');
        UploadLimits::enforce(uploadedFile(2 * 1024 * 1000, 'png'), 'avatar');
        expectRejected(
            static fn () => UploadLimits::enforce(uploadedFile(1024, 'jpg'), 'card'),
            '文件格式错误',
            'C1 旧扩展名'
        );
        expectRejected(
            static fn () => UploadLimits::enforce(uploadedFile(5 * 1024 * 1000 + 1, 'png'), 'avatar'),
            '超出上传限制',
            'C1 旧大小'
        );
    };

    // ------------------------------------------------------------
    // 场景新键覆盖旧键，两桶互不影响
    // ------------------------------------------------------------
    $tests['D1: scene keys override legacy and buckets are independent'] = static function (): void {
        ConfigService::reset();
        ConfigService::$rows = [
            'upload.user_image_size' => 5,
            'upload.user_image_ext' => 'jpg,png',
            'upload.avatar_size' => 1,
            'upload.avatar_ext' => 'png',
            'upload.content_size' => 8,
            'upload.content_ext' => 'jpg,png,webp',
        ];
        UploadLimits::enforce(uploadedFile(8 * 1024 * 1000, 'webp'), 'card');
        UploadLimits::enforce(uploadedFile(1024 * 1000, 'png'), 'profile_cover');
        expectRejected(
            static fn () => UploadLimits::enforce(uploadedFile(2 * 1024 * 1000, 'png'), 'avatar'),
            '超出上传限制',
            'D1 头像桶更严'
        );
        expectRejected(
            static fn () => UploadLimits::enforce(uploadedFile(1024, 'webp'), 'avatar'),
            '文件格式错误',
            'D1 头像扩展名'
        );
        expectRejected(
            static fn () => UploadLimits::enforce(uploadedFile(9 * 1024 * 1000, 'webp'), 'card'),
            '超出上传限制',
            'D1 内容桶上限'
        );
    };

    // ------------------------------------------------------------
    // 空值/非法新键回落到旧键（防保存空值造成 0MB 静默收紧）
    // ------------------------------------------------------------
    $tests['D2: zero or empty scene keys fall back instead of tightening to zero'] = static function (): void {
        ConfigService::reset();
        ConfigService::$rows = [
            'upload.user_image_size' => 5,
            'upload.user_image_ext' => 'jpg,png,gif,webp,jpeg',
            'upload.avatar_size' => 0,
            'upload.avatar_ext' => '  ',
            'upload.content_size' => -1,
            'upload.content_ext' => '',
        ];
        UploadLimits::enforce(uploadedFile(5 * 1024 * 1000, 'jpg'), 'card');
        UploadLimits::enforce(uploadedFile(5 * 1024 * 1000, 'jpg'), 'avatar');
    };

    // ------------------------------------------------------------
    // 直传闸：按声明的文件名/大小，归内容桶，凭证签发前生效
    // ------------------------------------------------------------
    $tests['E1: direct upload gate uses content bucket and filename extension'] = static function (): void {
        ConfigService::reset();
        ConfigService::$rows = [
            'upload.avatar_size' => 1,
            'upload.content_size' => 4,
        ];
        UploadLimits::enforcePending('photo.jpeg', 4 * 1024 * 1000);
        expectRejected(
            static fn () => UploadLimits::enforcePending('photo.jpeg', 4 * 1024 * 1000 + 1),
            '超出上传限制',
            'E1 直传大小'
        );
        expectRejected(
            static fn () => UploadLimits::enforcePending('noextension', 1024),
            '文件格式错误',
            'E1 无扩展名'
        );
        expectRejected(
            static fn () => UploadLimits::enforcePending('evil.exe', 1024),
            '文件格式错误',
            'E1 危险扩展名'
        );
    };

    // ------------------------------------------------------------
    // 错误文案兼容前台 user-facing-error.js 的关键词映射
    // ------------------------------------------------------------
    $tests['F1: error wording stays compatible with frontend regex mapping'] = static function (): void {
        ConfigService::reset();
        ConfigService::$rows = ['upload.content_size' => 1];
        expectRejected(
            static fn () => UploadLimits::enforce(uploadedFile(2 * 1024 * 1000, 'jpg'), 'card'),
            '超出上传限制',
            'F1 大小关键词'
        );
        expectRejected(
            static fn () => UploadLimits::enforce(uploadedFile(1024, 'bat'), 'card'),
            '文件格式错误',
            'F1 格式关键词'
        );
    };

    // ------------------------------------------------------------
    // 幂等物化：首次播种场景键时复制旧键现值（键带组前缀）
    // ------------------------------------------------------------
    $tests['G1: materialize copies legacy values for freshly seeded scene keys'] = static function (): void {
        ConfigService::reset();
        ConfigService::$rows = ['upload.user_image_size' => 5, 'upload.user_image_ext' => 'jpg,gif'];
        UploadLimits::materialize([
            'upload.avatar_size',
            'upload.avatar_ext',
            'upload.content_size',
            'upload.content_ext',
        ]);
        foreach (['upload.avatar_size' => 5, 'upload.avatar_ext' => 'jpg,gif', 'upload.content_size' => 5, 'upload.content_ext' => 'jpg,gif'] as $key => $expected) {
            if ((ConfigService::$rows[$key] ?? null) !== $expected) {
                throw new \RuntimeException("物化后 {$key} 应为 {$expected}");
            }
        }
    };

    // ------------------------------------------------------------
    // 幂等物化：再次 init（无新播种键）不得覆盖已被管理员修改的值
    // ------------------------------------------------------------
    $tests['G2: materialize is no-op when scene keys were not freshly seeded'] = static function (): void {
        ConfigService::reset();
        ConfigService::$rows = [
            'upload.user_image_size' => 5,
            'upload.avatar_size' => 3,
            'upload.avatar_ext' => 'png',
            'upload.content_size' => 8,
            'upload.content_ext' => 'webp',
        ];
        UploadLimits::materialize(['sender.some_key', 'upload.user_image_size']);
        UploadLimits::materialize([]);
        if (ConfigService::$writes !== []) {
            throw new \RuntimeException('重复 init 不应产生任何 upload 写入');
        }
    };

    // ------------------------------------------------------------
    // 幂等物化：旧全局键没有行（新装环境）不写入，保持文件默认值
    // ------------------------------------------------------------
    $tests['G3: materialize does not write when legacy keys have no rows'] = static function (): void {
        ConfigService::reset();
        ConfigService::$rows = ['upload.avatar_size' => 2, 'upload.avatar_ext' => 'jpg,png,gif,webp,jpeg'];
        UploadLimits::materialize(['upload.avatar_size', 'upload.avatar_ext', 'upload.content_size', 'upload.content_ext']);
        if (ConfigService::$writes !== []) {
            throw new \RuntimeException('旧键无行时不应写入，保持注册默认值');
        }
    };

    // ------------------------------------------------------------
    // 闸口挂载点锁定：常规上传与直传凭证签发前均调用 UploadLimits
    // ------------------------------------------------------------
    $tests['H1: upload and direct-upload use cases call the gate before driver work'] = static function (): void {
        $uploadCode = file_get_contents(__DIR__ . '/../../app/modules/Platform/Files/Application/UploadFile.php');
        $directCode = file_get_contents(__DIR__ . '/../../app/modules/Platform/Files/Application/DirectUpload.php');
        if (strpos($uploadCode, 'UploadLimits::enforce($file, $scene);') === false) {
            throw new \RuntimeException('UploadFile 用例必须在驱动上传前调用 UploadLimits::enforce');
        }
        if (strpos($directCode, 'UploadLimits::enforcePending($filename, $size);') === false) {
            throw new \RuntimeException('DirectUpload 必须在签发凭证前调用 UploadLimits::enforcePending');
        }
    };
}

// ════════════════════════════════════════════════════════════
// 运行器
// ════════════════════════════════════════════════════════════
namespace
{
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
        fwrite(STDERR, "\n{$failures} Files upload scene baseline test(s) failed.\n");
        exit(1);
    }

    fwrite(STDOUT, "\n" . count($tests) . " Files upload scene baseline tests passed.\n");
}
