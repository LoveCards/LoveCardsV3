<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Contract;

/**
 * 全局通知策略当前版本来源（由 Notifications 模块适配器实现）。
 * 事件登记时把当时的版本冻结进 event_deliveries.policy_revision（契约 §5.2）。
 */
interface PolicyVersionSource
{
    /** 无策略目录时返回 null（not-configured，fail-open）。 */
    public function currentRevision(): ?int;
}
