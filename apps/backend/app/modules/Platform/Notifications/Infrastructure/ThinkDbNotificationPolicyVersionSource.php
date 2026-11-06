<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Infrastructure;

use app\modules\Platform\Eventing\Contract\PolicyVersionSource;
use think\facade\Db;

/**
 * 读取 notification_policy_versions 的最大版本：Outbox 登记时冻结到投递行。
 */
final class ThinkDbNotificationPolicyVersionSource implements PolicyVersionSource
{
    public function currentRevision(): ?int
    {
        try {
            $revision = Db::table('notification_policy_versions')->max('policy_revision');
        } catch (\Throwable $exception) {
            // 表尚未迁移（expand 前）：视为无策略目录，绝不阻断事件登记。
            return null;
        }
        return $revision === null ? null : (int) $revision;
    }
}
