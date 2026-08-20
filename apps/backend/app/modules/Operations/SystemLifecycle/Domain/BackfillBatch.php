<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Domain;

final class BackfillBatch
{
    private bool $complete;
    private ?string $nextCursor;

    private function __construct(bool $complete, ?string $nextCursor)
    {
        if (!$complete && ($nextCursor === null || $nextCursor === '' || strlen($nextCursor) > 2048)) {
            throw new \InvalidArgumentException('未完成的 Backfill 必须返回有效游标');
        }
        if ($complete && $nextCursor !== null) {
            throw new \InvalidArgumentException('已完成的 Backfill 不应返回游标');
        }
        $this->complete = $complete;
        $this->nextCursor = $nextCursor;
    }

    public static function complete(): self { return new self(true, null); }
    public static function pending(string $nextCursor): self { return new self(false, $nextCursor); }
    public function isComplete(): bool { return $this->complete; }
    public function nextCursor(): ?string { return $this->nextCursor; }
}
