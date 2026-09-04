<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Domain;

final class SystemNotice
{
    public const NOTICE = 'system.notice';
    public const SECURITY = 'system.security';
    public const MODERATION = 'system.moderation';

    private const TYPES = [self::NOTICE, self::SECURITY, self::MODERATION];
    private const SEVERITIES = ['info', 'success', 'warning', 'critical'];

    private int $recipientId;
    private string $noticeKey;
    private string $type;
    private string $message;
    private ?string $actionUrl;
    private string $severity;
    private ?int $actorId;

    public function __construct(
        int $recipientId,
        string $noticeKey,
        string $type,
        string $message,
        ?string $actionUrl = null,
        string $severity = 'info',
        ?int $actorId = null
    ) {
        $noticeKey = trim($noticeKey);
        $message = trim($message);

        if ($recipientId < 1) {
            throw new \InvalidArgumentException('系统通知接收用户无效');
        }
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,95}$/', $noticeKey)) {
            throw new \InvalidArgumentException('系统通知幂等键无效');
        }
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('系统通知类型无效');
        }
        if ($message === '') {
            throw new \InvalidArgumentException('系统通知内容不能为空');
        }
        if (!in_array($severity, self::SEVERITIES, true)) {
            throw new \InvalidArgumentException('系统通知级别无效');
        }
        if ($actorId !== null && $actorId < 1) {
            throw new \InvalidArgumentException('系统通知发起用户无效');
        }

        $actionUrl = self::normalizeActionUrl($actionUrl);
        $notificationPayload = [
            'message' => $message,
            'action_url' => $actionUrl,
            'severity' => $severity,
        ];
        $encoded = json_encode($notificationPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($encoded) > 4096) {
            throw new \InvalidArgumentException('系统通知数据超出限制');
        }

        $this->recipientId = $recipientId;
        $this->noticeKey = $noticeKey;
        $this->type = $type;
        $this->message = $message;
        $this->actionUrl = $actionUrl;
        $this->severity = $severity;
        $this->actorId = $actorId;
    }

    public static function fromEventPayload(array $eventPayload, ?int $actorId): self
    {
        self::assertExactKeys(
            $eventPayload,
            ['recipient_id', 'notice_key', 'notification_type', 'payload'],
            'system.notification.issued payload'
        );

        $payload = $eventPayload['payload'] ?? null;
        if (!is_array($payload)) {
            throw new \UnexpectedValueException('system.notification.issued payload.payload 必须是对象');
        }
        self::assertExactKeys($payload, ['message', 'action_url', 'severity'], '系统通知 payload');

        if (!is_int($eventPayload['recipient_id'] ?? null)
            || !is_string($eventPayload['notice_key'] ?? null)
            || !is_string($eventPayload['notification_type'] ?? null)
            || !is_string($payload['message'] ?? null)
            || !(is_string($payload['action_url'] ?? null) || ($payload['action_url'] ?? null) === null)
            || !is_string($payload['severity'] ?? null)) {
            throw new \UnexpectedValueException('system.notification.issued payload 字段类型无效');
        }

        try {
            return new self(
                $eventPayload['recipient_id'],
                $eventPayload['notice_key'],
                $eventPayload['notification_type'],
                $payload['message'],
                $payload['action_url'],
                $payload['severity'],
                $actorId
            );
        } catch (\InvalidArgumentException $exception) {
            throw new \UnexpectedValueException('system.notification.issued payload 无效', 0, $exception);
        }
    }

    public function recipientId(): int { return $this->recipientId; }
    public function actorId(): ?int { return $this->actorId; }

    /** @return array{recipient_id: int, notice_key: string, notification_type: string, payload: array{message: string, action_url: ?string, severity: string}} */
    public function eventPayload(): array
    {
        return [
            'recipient_id' => $this->recipientId,
            'notice_key' => $this->noticeKey,
            'notification_type' => $this->type,
            'payload' => $this->notificationPayload(),
        ];
    }

    public function notificationIntent(): NotificationIntent
    {
        return new NotificationIntent(
            $this->recipientId,
            'system:' . $this->noticeKey . ':' . $this->recipientId,
            $this->type,
            $this->actorId,
            null,
            $this->notificationPayload()
        );
    }

    /** @return array{message: string, action_url: ?string, severity: string} */
    private function notificationPayload(): array
    {
        return [
            'message' => $this->message,
            'action_url' => $this->actionUrl,
            'severity' => $this->severity,
        ];
    }

    private static function normalizeActionUrl(?string $actionUrl): ?string
    {
        if ($actionUrl === null) {
            return null;
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $actionUrl)) {
            throw new \InvalidArgumentException('系统通知跳转路径无效');
        }

        $actionUrl = trim($actionUrl);
        if ($actionUrl === '' || strlen($actionUrl) > 2048) {
            throw new \InvalidArgumentException('系统通知跳转路径无效');
        }

        $decoded = rawurldecode($actionUrl);
        if (!str_starts_with($actionUrl, '/')
            || str_starts_with($actionUrl, '//')
            || str_contains($actionUrl, '\\')
            || str_contains($actionUrl, '://')
            || str_contains($decoded, '//')
            || str_contains($decoded, '\\')
            || str_contains($decoded, '://')
            || preg_match('/[\x00-\x1F\x7F]/', $decoded)) {
            throw new \InvalidArgumentException('系统通知跳转路径必须是站内路径');
        }

        $parts = parse_url($actionUrl);
        if ($parts === false
            || isset($parts['scheme'])
            || isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])) {
            throw new \InvalidArgumentException('系统通知跳转路径必须是站内路径');
        }

        return $actionUrl;
    }

    private static function assertExactKeys(array $value, array $expected, string $field): void
    {
        $actual = array_keys($value);
        sort($actual);
        sort($expected);
        if ($actual !== $expected) {
            throw new \UnexpectedValueException($field . ' 字段不完整或包含未授权字段');
        }
    }
}
