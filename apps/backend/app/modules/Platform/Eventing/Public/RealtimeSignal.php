<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Public;

/**
 * Realtime 信号值对象：只携带 topic / recipient scope / cursor hint，不承载业务事实。
 * 浏览器与 Gateway 收到信号后必须回到游标 API 读取真实数据（契约 §4.5）。
 */
final class RealtimeSignal
{
    private string $topic;
    /** @var string[] */
    private array $recipientKeys;
    private string $cursorHint;

    public function __construct(string $topic, string|array $recipientKey, string $cursorHint = '')
    {
        if (!preg_match('/^[a-z][a-z0-9_.-]{0,99}$/', $topic)) {
            throw new \InvalidArgumentException('Realtime topic 不合法');
        }
        $recipientKeys = is_array($recipientKey) ? array_values($recipientKey) : [$recipientKey];
        if ($recipientKeys === [] || count($recipientKeys) > 200 || count(array_unique($recipientKeys)) !== count($recipientKeys)) {
            throw new \InvalidArgumentException('Realtime recipient_keys 不合法');
        }
        foreach ($recipientKeys as $key) {
            if (!is_string($key) || $key === '' || strlen($key) > 191) {
                throw new \InvalidArgumentException('Realtime recipient_key 不合法');
            }
        }
        if (strlen($cursorHint) > 191) {
            throw new \InvalidArgumentException('Realtime cursor_hint 过长');
        }
        $this->topic = $topic;
        $this->recipientKeys = $recipientKeys;
        $this->cursorHint = $cursorHint;
    }

    public function topic(): string { return $this->topic; }

    public function recipientKey(): string { return $this->recipientKeys[0]; }
    /** @return string[] */
    public function recipientKeys(): array { return $this->recipientKeys; }

    public function cursorHint(): string { return $this->cursorHint; }
}
