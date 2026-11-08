<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Application;

use app\modules\Platform\Notifications\Contract\NotificationPolicyStore;

/**
 * 投影前策略决策（契约 §5.2）：
 * - 冻结版本或快照缺失 → not-configured（fail-open，绝不因策略缺行丢通知）；
 * - 事件未见于快照 → not-configured；
 * - enabled → applied；关闭 → skipped（不删除事件与投递，只跳过投影）。
 */
final class NotificationPolicyGate
{
    public function __construct(private NotificationPolicyStore $store)
    {
    }

    public function decide(string $eventType, ?int $frozenRevision): string
    {
        if ($frozenRevision === null || $eventType === '') {
            return 'not-configured';
        }
        $snapshot = $this->store->snapshot($frozenRevision);
        if ($snapshot === null) {
            return 'not-configured';
        }
        $rule = $snapshot[$eventType] ?? null;
        if ($rule === null) {
            return 'not-configured';
        }
        return ($rule['enabled'] ?? true) ? 'applied' : 'skipped';
    }
}
