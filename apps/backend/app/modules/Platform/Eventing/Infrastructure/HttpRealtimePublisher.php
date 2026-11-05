<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Infrastructure;

use app\modules\Platform\Eventing\Contract\RealtimePublisher;
use app\modules\Platform\Eventing\Domain\RealtimeSignal;

/** 向独立 Gateway 发送低价值唤醒信号；失败不影响已提交的业务事实。 */
final class HttpRealtimePublisher implements RealtimePublisher
{
    public function publish(RealtimeSignal $signal): void
    {
        $url = trim((string) env('LC_REALTIME_INTERNAL_URL', ''));
        $secret = trim((string) env('LC_REALTIME_INTERNAL_SECRET', ''));
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false || strlen($secret) < 32) return;
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || !in_array($parts['scheme'], ['http', 'https'], true)) return;
        $url = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) $url .= ':' . (int) $parts['port'];
        $url .= rtrim((string) ($parts['path'] ?? ''), '/') . '/internal/signals';

        try {
            $payload = json_encode([
                'signal_id' => bin2hex(random_bytes(16)),
                'topic' => $signal->topic(),
                'recipient_key' => $signal->recipientKey(),
                'cursor_hint' => $signal->cursorHint(),
                'occurred_at' => gmdate('c'),
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $context = stream_context_create(['http' => [
                'method' => 'POST',
                'header' => "Authorization: Bearer {$secret}\r\nContent-Type: application/json\r\n",
                'content' => $payload,
                'timeout' => 0.25,
                'ignore_errors' => true,
            ]]);
            @file_get_contents($url, false, $context);
        } catch (\Throwable) {
            // Gateway 是 best-effort 唤醒通道，业务事实仍由 MySQL 保存。
        }
    }
}
