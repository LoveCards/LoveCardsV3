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

    /** @param iterable<HandlerRegistration> $registrations */
    public function __construct(iterable $registrations)
    {
        foreach ($registrations as $registration) $this->register($registration);
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
