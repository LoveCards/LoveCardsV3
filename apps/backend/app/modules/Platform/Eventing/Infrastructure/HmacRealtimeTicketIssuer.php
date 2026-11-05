<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Infrastructure;

use app\modules\Platform\Eventing\Contract\RealtimeTicketIssuer;

final class HmacRealtimeTicketIssuer implements RealtimeTicketIssuer
{
    private const PROTOCOL = 'lc-realtime-1';
    private const ALLOWED_TOPICS = ['notifications', 'messages'];

    public function issue(int $recipientId, array $topics = self::ALLOWED_TOPICS): array
    {
        if ($recipientId < 1) throw new \InvalidArgumentException('Realtime recipient 无效');

        $secret = trim((string) env('LC_REALTIME_TICKET_SECRET', ''));
        if (strlen($secret) < 32) throw new \RuntimeException('Realtime ticket secret 未配置');

        $normalizedTopics = array_values(array_unique(array_map('strval', $topics)));
        if ($normalizedTopics === [] || count($normalizedTopics) > count(self::ALLOWED_TOPICS)) {
            throw new \InvalidArgumentException('Realtime topic 不能为空');
        }
        foreach ($normalizedTopics as $topic) {
            if (!in_array($topic, self::ALLOWED_TOPICS, true)) throw new \InvalidArgumentException('Realtime topic 不支持');
        }

        $ttl = max(15, min(300, (int) env('LC_REALTIME_TICKET_TTL_SECONDS', 60)));
        $issuedAt = time();
        $payload = [
            'aud' => self::PROTOCOL,
            'recipient_key' => 'user:' . $recipientId,
            'topics' => $normalizedTopics,
            'iat' => $issuedAt,
            'exp' => $issuedAt + $ttl,
            'jti' => bin2hex(random_bytes(16)),
        ];
        $encoded = self::base64Url(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $signature = self::base64Url(hash_hmac('sha256', $encoded, $secret, true));
        $endpoint = trim((string) env('LC_REALTIME_PUBLIC_URL', ''));
        if ($endpoint === '' || filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
            throw new \RuntimeException('Realtime Gateway public URL 未配置');
        }

        return [
            'ticket' => $encoded . '.' . $signature,
            'endpoint' => rtrim($endpoint, '/') . '/stream',
            'protocol' => self::PROTOCOL,
            'expires_at' => gmdate('c', $payload['exp']),
            'topics' => $normalizedTopics,
            'cursor_strategy' => 'opaque',
        ];
    }

    private static function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
