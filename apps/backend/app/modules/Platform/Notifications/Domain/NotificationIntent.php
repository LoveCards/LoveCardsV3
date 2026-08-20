<?php

namespace app\modules\Platform\Notifications\Domain;

use app\shared\Resource\ResourceRef;

final class NotificationIntent
{
    public function __construct(
        private int $recipientId,
        private string $dedupeKey,
        private string $type,
        private ?int $actorId = null,
        private ?ResourceRef $resource = null,
        private array $data = []
    ) {
        $this->dedupeKey = trim($dedupeKey);
        $this->type = strtolower(trim($type));
        if ($recipientId <= 0) throw new \InvalidArgumentException('通知接收用户无效');
        if ($this->dedupeKey === '' || strlen($this->dedupeKey) > 128) throw new \InvalidArgumentException('通知幂等键无效');
        if (!preg_match('/^[a-z][a-z0-9_.-]{0,63}$/', $this->type)) throw new \InvalidArgumentException('通知类型无效');
        $payload = json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (strlen($payload) > 4096) throw new \InvalidArgumentException('通知数据超出限制');
    }

    public function recipientId(): int { return $this->recipientId; }
    public function dedupeKey(): string { return $this->dedupeKey; }
    public function type(): string { return $this->type; }
    public function actorId(): ?int { return $this->actorId; }
    public function resource(): ?ResourceRef { return $this->resource; }
    public function data(): array { return $this->data; }
}
