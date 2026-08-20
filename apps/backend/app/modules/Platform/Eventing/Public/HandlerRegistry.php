<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Public;

final class HandlerRegistry
{
    /** @var HandlerRegistration[] */
    private array $byKey = [];
    /** @var array<string, HandlerRegistration[]> */
    private array $byEvent = [];

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
            'file.purge.requested',
            1,
            'files.purge',
            'storage',
            ExecutionMode::REQUEST_SAFE,
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
