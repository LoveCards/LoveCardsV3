<?php
declare(strict_types=1);

namespace app\modules\Platform\Safety\Domain;

/** 举报待办的唯一状态集合，供列表筛选和工作台计数共用。 */
final class ReportStatus
{
    public const PENDING = ['open', 'reviewing'];
}
