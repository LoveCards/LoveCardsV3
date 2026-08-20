<?php
declare(strict_types=1);

namespace app\modules\Platform\Safety\Contract;

use app\shared\Resource\ResourceRef;

interface ReportTargetResolver
{
    /** @return array<string,mixed>|null */
    public function snapshotForReporter(int $reporterId, ResourceRef $resource): ?array;
}
