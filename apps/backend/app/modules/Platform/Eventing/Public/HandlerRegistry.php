<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Public;

final class HandlerRegistry
{
    /** @var HandlerRegistration[] */
    private array $byKey = [];
    /** @var array<string, HandlerRegistration[]> */
    private array $byEvent = [];
    /** @var array<string, true> */
    private array $signalKeys = [];

    public function __construct()
    {
        $this->register(new HandlerRegistration(
            'social.followed',
            1,
            'notifications.social-followed',
            'notifications',
            ExecutionMode::REQUEST_SAFE,
            new \app\modules\Platform\Notifications\Infrastructure\SocialFollowedNotificationHandler()
        ));
        $this->register(new HandlerRegistration(
            'message.sent',
            1,
            'notifications.message-sent',
            'notifications',
            ExecutionMode::REQUEST_SAFE,
            new \app\modules\Platform\Notifications\Infrastructure\MessageSentNotificationHandler()
        ));
        $this->register(new HandlerRegistration(
            'message.sent',
            1,
            'realtime.message-sent',
            'realtime',
            ExecutionMode::DAEMON_REQUIRED,
            new \app\modules\Platform\Messaging\Infrastructure\MessageSentSignalHandler(),
            100,
            [5, 30, 300, 1800],
            [
                'key' => 'messages.new',
                'topic' => 'messages',
                'owner' => 'Messaging',
                'display_name' => '新私信主动提示',
                'impact_text' => '关闭后私信仍可发送和读取，浏览器不主动提示新私信。',
                'default_enabled' => true,
            ]
        ));
        $this->register(new HandlerRegistration(
            'message.read',
            1,
            'realtime.message-read',
            'realtime',
            ExecutionMode::DAEMON_REQUIRED,
            new \app\modules\Platform\Messaging\Infrastructure\MessageReadSignalHandler(),
            100,
            [5, 30, 300, 1800],
            [
                'key' => 'messages.read',
                'topic' => 'messages',
                'owner' => 'Messaging',
                'display_name' => '私信已读状态主动提示',
                'impact_text' => '关闭后已读状态照常保存，浏览器不主动提示会话已读变化。',
                'default_enabled' => true,
            ]
        ));
        $this->register(new HandlerRegistration(
            'content.liked',
            1,
            'notifications.content-liked',
            'notifications',
            ExecutionMode::REQUEST_SAFE,
            new \app\modules\Platform\Notifications\Infrastructure\ContentLikedNotificationHandler()
        ));
        $this->register(new HandlerRegistration(
            'library.saved',
            1,
            'notifications.library-saved',
            'notifications',
            ExecutionMode::REQUEST_SAFE,
            new \app\modules\Platform\Notifications\Infrastructure\LibrarySavedNotificationHandler()
        ));
        $this->register(new HandlerRegistration(
            'content.comment.published',
            1,
            'notifications.content-comment-published',
            'notifications',
            ExecutionMode::REQUEST_SAFE,
            new \app\modules\Platform\Notifications\Infrastructure\ContentCommentPublishedNotificationHandler()
        ));
        $this->register(new HandlerRegistration(
            'content.mentions.added',
            1,
            'notifications.content-mentions-added',
            'notifications',
            ExecutionMode::REQUEST_SAFE,
            new \app\modules\Platform\Notifications\Infrastructure\ContentMentionsAddedNotificationHandler()
        ));
        $this->register(new HandlerRegistration(
            'system.notification.issued',
            1,
            'notifications.system-notification-issued',
            'notifications',
            ExecutionMode::REQUEST_SAFE,
            new \app\modules\Platform\Notifications\Infrastructure\SystemNotificationIssuedHandler()
        ));
        $this->register(new HandlerRegistration(
            'notification.created',
            1,
            'realtime.notification-created',
            'realtime',
            ExecutionMode::DAEMON_REQUIRED,
            new \app\modules\Platform\Notifications\Infrastructure\NotificationCreatedSignalHandler(),
            100,
            [5, 30, 300, 1800],
            [
                'key' => 'notifications.new',
                'topic' => 'notifications',
                'owner' => 'Notifications',
                'display_name' => '新站内通知主动提示',
                'impact_text' => '关闭后站内通知照常生成和读取，浏览器不主动提示新通知。',
                'default_enabled' => true,
            ]
        ));
        $this->register(new HandlerRegistration(
            'file.purge.requested',
            1,
            'files.purge',
            'storage',
            ExecutionMode::DAEMON_REQUIRED,
            new \app\modules\Platform\Files\Infrastructure\PurgeFileHandler(),
            8,
            [5, 30, 300, 1800, 7200, 21600, 86400]
        ));
    }

    public function register(HandlerRegistration $registration): void
    {
        $key = $registration->handlerKey();
        if (isset($this->byKey[$key])) {
            throw new \LogicException("Event Handler 重复注册：{$key}");
        }
        $signal = $registration->signal();
        if ($signal !== null) {
            if (isset($this->signalKeys[$signal['key']])) {
                throw new \LogicException("Realtime signal 重复注册：{$signal['key']}");
            }
            $this->signalKeys[$signal['key']] = true;
        }

        $eventKey = $this->eventKey($registration->eventType(), $registration->eventVersion());
        $this->byKey[$key] = $registration;
        $this->byEvent[$eventKey][] = $registration;
    }

    public function find(string $handlerKey): ?HandlerRegistration
    {
        return $this->byKey[$handlerKey] ?? null;
    }

    /**
     * @return HandlerRegistration[]
     */
    public function handlersFor(EventEnvelope $event): array
    {
        return $this->byEvent[$this->eventKey($event->eventType(), $event->eventVersion())] ?? [];
    }

    /**
     * 返回指定执行模式实际注册的队列，供 HTTP、Scheduler 与 Worker 共用。
     *
     * @param string[] $executionModes
     * @return string[]
     */
    public function queueNamesFor(array $executionModes): array
    {
        if ($executionModes === []) {
            throw new \InvalidArgumentException('execution_modes 不能为空');
        }
        foreach ($executionModes as $executionMode) {
            if (!is_string($executionMode)) {
                throw new \InvalidArgumentException('execution_mode 必须是字符串');
            }
            ExecutionMode::assert($executionMode);
        }

        $queueNames = [];
        foreach ($this->byKey as $registration) {
            if (in_array($registration->executionMode(), $executionModes, true)) {
                $queueNames[$registration->queueName()] = true;
            }
        }
        $queueNames = array_keys($queueNames);
        sort($queueNames, SORT_STRING);

        return $queueNames;
    }

    public function catalogHash(): string
    {
        return hash('sha256', json_encode(
            self::canonicalize($this->catalog()),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
    }

    /** @return array<int, array<string, mixed>> */
    public function catalog(): array
    {
        $catalog = [];
        $registrations = $this->byKey;
        ksort($registrations, SORT_STRING);
        foreach ($registrations as $registration) {
            $catalog[] = $registration->catalogData();
        }

        return $catalog;
    }

    /** @return array<int, array<string, mixed>> */
    public function signalCatalog(): array
    {
        $catalog = [];
        foreach ($this->byKey as $registration) {
            $signal = $registration->signal();
            if ($signal === null) continue;
            $catalog[] = $signal + [
                'event_type' => $registration->eventType(),
                'event_version' => $registration->eventVersion(),
                'handler_key' => $registration->handlerKey(),
            ];
        }
        usort($catalog, static fn(array $a, array $b): int => strcmp($a['key'], $b['key']));
        return $catalog;
    }

    private function eventKey(string $eventType, int $version): string
    {
        return $eventType . '@' . $version;
    }

    /** @param mixed $value @return mixed */
    private static function canonicalize($value)
    {
        if (!is_array($value)) return $value;
        if ($value === [] || array_keys($value) === range(0, count($value) - 1)) {
            return array_map(static fn($item) => self::canonicalize($item), $value);
        }
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) $value[$key] = self::canonicalize($item);
        return $value;
    }
}
