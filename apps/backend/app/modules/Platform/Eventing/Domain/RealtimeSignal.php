<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Domain;

final class RealtimeSignal
{
    private string $topic;
    private string $recipientKey;
    private string $cursorHint;

    public function __construct(string $topic, string $recipientKey, string $cursorHint = '')
    {
        if (!preg_match('/^[a-z][a-z0-9_.-]{0,99}$/', $topic)) {
            throw new \InvalidArgumentException('Realtime topic 不合法');
        }
        if ($recipientKey === '' || strlen($recipientKey) > 191) {
            throw new \InvalidArgumentException('Realtime recipient_key 不合法');
        }
        if (strlen($cursorHint) > 191) {
            throw new \InvalidArgumentException('Realtime cursor_hint 过长');
        }
        $this->topic = $topic;
        $this->recipientKey = $recipientKey;
        $this->cursorHint = $cursorHint;
    }

    public function topic(): string { return $this->topic; }
    public function recipientKey(): string { return $this->recipientKey; }
    public function cursorHint(): string { return $this->cursorHint; }
}
