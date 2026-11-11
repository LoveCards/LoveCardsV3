<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Infrastructure;

use app\modules\Platform\Eventing\Contract\PolicyVersionSource;

/**
 * 读取 notification_policy_versions 的最大版本：Outbox 登记时冻结到投递行。
 */
final class ThinkDbNotificationPolicyVersionSource implements PolicyVersionSource
{
    public function currentRevision(): ?int
    {
        // 读取错误必须随业务事务失败，不能新建被误认作历史未配置的 NULL 投递。
        return (new ThinkDbNotificationPolicyStore())->currentRevision();
    }
}
