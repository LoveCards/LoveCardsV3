<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Domain;

/**
 * 通知列表游标编解码；主动信号只可携带读取提示，
 * 浏览器仍以通知 PHP API 返回的游标和数据为准。
 */
final class NotificationCursor
{
    public static function encode(string $createdAt, int $id): string
    {
        $payload = json_encode(['created_at' => $createdAt, 'id' => $id], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    /**
     * 解析新版游标；不识别旧版纯数字游标（旧语义由仓储 cursorAnchor 兼容）。
     *
     * @return array{created_at: string, id: int}|null
     */
    public static function decode(string $cursor): ?array
    {
        $cursor = trim($cursor);
        if ($cursor === '') {
            return null;
        }
        $padding = strlen($cursor) % 4;
        $encoded = strtr($cursor, '-_', '+/');
        if ($padding > 0) {
            $encoded .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode($encoded, true);
        $payload = is_string($decoded) ? json_decode($decoded, true) : null;
        $createdAt = is_array($payload) ? trim((string) ($payload['created_at'] ?? '')) : '';
        $id = is_array($payload) ? (int) ($payload['id'] ?? 0) : 0;
        if ($createdAt === '' || $id < 1 || preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $createdAt) !== 1) {
            throw new \InvalidArgumentException('通知游标无效');
        }
        return ['created_at' => $createdAt, 'id' => $id];
    }
}
