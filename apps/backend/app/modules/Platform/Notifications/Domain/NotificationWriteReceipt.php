<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Domain;

final class NotificationWriteReceipt
{
    public function __construct(private int $id, private bool $created)
    {
        if ($id < 1) throw new \InvalidArgumentException('通知 ID 无效');
    }

    public function id(): int { return $this->id; }
    public function created(): bool { return $this->created; }
}
