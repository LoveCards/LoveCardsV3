<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Application;

use app\modules\Platform\Notifications\Contract\NotificationPolicyStore;

/**
 * 投影前策略决策（契约 §5.2）：
 * - 历史冻结版本为 NULL → not-configured；已冻结版本不可读则抛错并等待投递重试；
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
        if ($frozenRevision === null) {
            return 'not-configured';
        }
        if ($frozenRevision < 1 || $eventType === '') {
            throw new \RuntimeException('通知冻结策略标识无效');
        }
        $snapshot = $this->store->snapshot($frozenRevision);
        if ($snapshot === null) {
            throw new \RuntimeException('通知冻结策略版本不存在：' . $frozenRevision);
        }
        $rule = $snapshot[$eventType] ?? null;
        if ($rule === null) {
            return 'not-configured';
        }
        return ($rule['enabled'] ?? true) ? 'applied' : 'skipped';
    }
}
