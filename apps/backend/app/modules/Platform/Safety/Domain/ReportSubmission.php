<?php

namespace app\modules\Platform\Safety\Domain;

use app\shared\Resource\ResourceRef;

final class ReportSubmission
{
    private const REASONS = ['spam', 'abuse', 'privacy', 'illegal', 'other'];

    public function __construct(
        private int $reporterId,
        private ResourceRef $resource,
        private string $reason,
        private string $details = ''
    ) {
        $this->reason = strtolower(trim($reason));
        $this->details = trim($details);
        if ($reporterId <= 0) throw new \InvalidArgumentException('举报用户无效');
        if (!in_array($this->reason, self::REASONS, true)) throw new \InvalidArgumentException('举报原因无效');
        if (mb_strlen($this->details) > 2000) throw new \InvalidArgumentException('举报说明过长');
    }

    public function reporterId(): int { return $this->reporterId; }
    public function resource(): ResourceRef { return $this->resource; }
    public function reason(): string { return $this->reason; }
    public function details(): string { return $this->details; }
}
