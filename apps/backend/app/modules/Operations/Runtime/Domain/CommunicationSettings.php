<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Domain;

/**
 * 通信运行参数的白名单与上下限（契约 §2.2）。
 *
 * 服务端强制：白名单外键一律拒绝；数值越界给出中文字段级违例。
 * 基础设施参数（Gateway URL、内部 token）不在这里——只走 env。
 */
final class CommunicationSettings
{
    public const MODES = ['polling', 'realtime'];

    /** @var array<string,array{type:string,min?:float,max?:float}> */
    private const SPEC = [
        'notification_interval_seconds' => ['type' => 'int', 'min' => 5, 'max' => 300],
        'message_interval_seconds' => ['type' => 'int', 'min' => 5, 'max' => 300],
        'unread_interval_seconds' => ['type' => 'int', 'min' => 5, 'max' => 120],
        'polling_backoff_factor' => ['type' => 'float', 'min' => 1.0, 'max' => 5.0],
        'polling_max_interval_seconds' => ['type' => 'int', 'min' => 60, 'max' => 900],
        'pause_when_hidden' => ['type' => 'bool'],
        'worker_stale_seconds' => ['type' => 'int', 'min' => 10, 'max' => 300],
        'scheduler_stale_seconds' => ['type' => 'int', 'min' => 300, 'max' => 86400],
        'gateway_stale_seconds' => ['type' => 'int', 'min' => 5, 'max' => 120],
        'require_supervisor' => ['type' => 'bool'],
    ];

    /** 可经 API 提交的运行参数键（不含 desired_mode/expected_revision）。 */
    public const INPUT_KEYS = [
        'notification_interval_seconds', 'message_interval_seconds', 'unread_interval_seconds',
        'polling_backoff_factor', 'polling_max_interval_seconds', 'pause_when_hidden',
        'worker_stale_seconds', 'scheduler_stale_seconds', 'gateway_stale_seconds', 'require_supervisor',
    ];

    public static function defaults(): array
    {
        return [
            'notification_interval_seconds' => 30,
            'message_interval_seconds' => 45,
            'unread_interval_seconds' => 20,
            'polling_backoff_factor' => 2,
            'polling_max_interval_seconds' => 300,
            'pause_when_hidden' => true,
            'worker_stale_seconds' => 30,
            'scheduler_stale_seconds' => 3600,
            'gateway_stale_seconds' => 15,
            'require_supervisor' => false,
        ];
    }

    /**
     * 校验 API 输入（desired_mode/expected_revision + 提供的设置键）。
     *
     * @return array<int,array{field:string,message:string}>
     */
    public static function validateInput(array $input): array
    {
        $violations = [];
        foreach ($input as $key => $value) {
            if ($key === 'desired_mode') {
                if (!is_string($value) || !in_array($value, self::MODES, true)) {
                    $violations[] = ['field' => 'desired_mode', 'message' => '通信模式只能是 polling 或 realtime'];
                }
                continue;
            }
            if ($key === 'expected_revision') {
                if (!is_numeric($value) || (int) $value < 1) {
                    $violations[] = ['field' => 'expected_revision', 'message' => '配置版本号无效，请刷新后重试'];
                }
                continue;
            }
            if (!isset(self::SPEC[$key])) {
                $violations[] = ['field' => (string) $key, 'message' => '不支持的配置项'];
                continue;
            }
            $violation = self::validateValue((string) $key, $value);
            if ($violation !== null) $violations[] = $violation;
        }
        return $violations;
    }

    /** 存储读取的防御归一化：白名单外的键静默丢弃，类型纠正。 */
    public static function normalize(array $raw): array
    {
        $out = self::defaults();
        foreach ($raw as $key => $value) {
            if (!isset(self::SPEC[$key]) || !self::validType(self::SPEC[$key]['type'], $value)) continue;
            $out[$key] = self::cast(self::SPEC[$key]['type'], $value);
        }
        return $out;
    }

    /** 合并提交值到当前设置，得到完整新设置（调用方须先 validateInput）。 */
    public static function merge(array $current, array $input): array
    {
        $out = self::normalize($current);
        foreach ($input as $key => $value) {
            if (!isset(self::SPEC[$key])) continue;
            $out[$key] = self::cast(self::SPEC[$key]['type'], $value);
        }
        return $out;
    }

    /** @return array{field:string,message:string}|null */
    private static function validateValue(string $key, mixed $value): ?array
    {
        $spec = self::SPEC[$key];
        if (!self::validType($spec['type'], $value)) {
            return ['field' => $key, 'message' => self::typeMessage($key, $spec['type'])];
        }
        if ($spec['type'] === 'bool') return null;
        $number = (float) $value;
        $min = (float) ($spec['min'] ?? PHP_FLOAT_MAX);
        $max = (float) ($spec['max'] ?? PHP_FLOAT_MAX);
        if ($number < $min || $number > $max) {
            $unit = str_contains($key, 'seconds') ? '秒' : '';
            return ['field' => $key, 'message' => "该配置项允许范围 {$min}~{$max}{$unit}"];
        }
        if ($spec['type'] === 'int' && abs($number - round($number)) > 1e-9) {
            return ['field' => $key, 'message' => '该配置项必须为整数'];
        }
        return null;
    }

    private static function validType(string $type, mixed $value): bool
    {
        return match ($type) {
            'bool' => is_bool($value),
            'int' => is_int($value) || (is_numeric($value) && (string) (int) $value === (string) $value),
            'float' => is_numeric($value),
            default => false,
        };
    }

    private static function cast(string $type, mixed $value): mixed
    {
        return match ($type) {
            'bool' => (bool) $value,
            'int' => (int) $value,
            default => (float) $value,
        };
    }

    private static function typeMessage(string $key, string $type): string
    {
        return $type === 'bool' ? "{$key} 必须为布尔值" : "{$key} 必须为数字";
    }
}
