<?php
declare(strict_types=1);

namespace app\modules\Platform\Eventing\Infrastructure;

use app\modules\Platform\Eventing\Public\EventConsumptionConflict;
use think\facade\Db;

/** 消费者模式的单例持久状态；所有领取者必须在领取事务内先锁定本行。 */
final class ThinkDbEventConsumptionStore
{
    public function current(): ?array
    {
        try {
            return $this->normalize(Db::table('eventing_consumer_state')->where('id', 1)->find());
        } catch (\Throwable $ignored) {
            return null;
        }
    }

    /** 调用者已开启事务；锁顺序为事件模式行、通信模式行。 */
    public function locked(): ?array
    {
        return $this->normalize(Db::table('eventing_consumer_state')->where('id', 1)->lock(true)->find());
    }

    /** 必须在通信配置 CAS 的事务内调用。 */
    public function lockResidentForRealtime(): bool
    {
        $row = $this->locked();
        return $row !== null && $row['mode'] === 'resident' && $row['state'] === 'active';
    }

    /**
     * 切换先停新领取；有未过期租约时保留 draining，管理员按返回 revision 重试推进。
     * 审计回调在同一事务运行，形参为 before/after。
     */
    public function switchMode(string $target, int $expectedRevision, ?int $actorId, ?callable $audit, ?string $scheduledTrigger, ?callable $requirePolling): array
    {
        if (!in_array($target, ['scheduled', 'resident'], true)) {
            throw new EventConsumptionConflict('invalid-mode', '事件处理模式不合法');
        }
        if ($scheduledTrigger !== null && !in_array($scheduledTrigger, ['none', 'cli', 'http'], true)) {
            throw new EventConsumptionConflict('invalid-trigger', '定时消费触发源不合法');
        }
        Db::startTrans();
        try {
            $before = $this->locked();
            if ($before === null) {
                throw new EventConsumptionConflict('state-unavailable', '事件处理模式尚未初始化');
            }
            if ($before['revision'] !== $expectedRevision) {
                throw new EventConsumptionConflict('stale-revision', '事件处理模式已变化，请刷新后重试');
            }
            if ($before['state'] === 'draining' && $before['target_mode'] !== $target) {
                throw new EventConsumptionConflict('transition-draining', '已有事件处理模式切换正在等待租约结算');
            }
            $trigger = $scheduledTrigger ?? $before['scheduled_trigger'];
            if ($before['state'] === 'draining' && $trigger !== $before['scheduled_trigger']) {
                throw new EventConsumptionConflict('transition-draining', '定时触发源切换正在等待租约结算');
            }
            if ($before['state'] === 'active' && $before['mode'] === $target && $trigger === $before['scheduled_trigger']) {
                Db::commit();
                return $before;
            }
            if ($target === 'scheduled' && $before['mode'] !== 'scheduled') {
                if ($requirePolling === null) {
                    throw new EventConsumptionConflict('communication-guard-required', '通信模式门禁缺失');
                }
                if ($requirePolling() !== true) {
                    throw new EventConsumptionConflict('communication-realtime', '请先将前端通信切换为轮询');
                }
                if ($before['mode'] === 'resident' && $trigger === 'none') {
                    throw new EventConsumptionConflict('trigger-not-configured', '请先选择定时消费触发源');
                }
            }

            $transitionRequired = $before['mode'] !== $target || $before['state'] === 'draining'
                || ($before['mode'] === 'scheduled' && $trigger !== $before['scheduled_trigger']);
            $activeLeases = $transitionRequired ? (int) Db::table('event_deliveries')
                ->where('status', 'processing')
                ->where('lease_expires_at', '>=', gmdate('Y-m-d H:i:s.u'))
                ->count() : 0;
            $after = [
                'mode' => $activeLeases > 0 ? $before['mode'] : $target,
                'state' => $activeLeases > 0 ? 'draining' : 'active',
                'target_mode' => $activeLeases > 0 ? $target : null,
                'scheduled_trigger' => $trigger,
                'revision' => $before['revision'] + 1,
            ];
            Db::table('eventing_consumer_state')->where('id', 1)->update([
                'mode' => $after['mode'],
                'state' => $after['state'],
                'target_mode' => $after['target_mode'],
                'scheduled_trigger' => $trigger,
                'revision' => $after['revision'],
                'updated_by' => $actorId,
                'updated_at' => gmdate('Y-m-d H:i:s.u'),
            ]);
            if ($audit !== null) $audit($before, $after);
            Db::commit();
            return $after;
        } catch (\Throwable $exception) {
            Db::rollback();
            throw $exception;
        }
    }

    private function normalize($row): ?array
    {
        if (!is_array($row)) return null;
        $mode = (string) ($row['mode'] ?? '');
        $state = (string) ($row['state'] ?? '');
        $target = $row['target_mode'] ?? null;
        $trigger = (string) ($row['scheduled_trigger'] ?? '');
        if (!in_array($mode, ['scheduled', 'resident'], true)
            || !in_array($state, ['active', 'draining'], true)
            || !in_array($trigger, ['none', 'cli', 'http'], true)
            || ($target !== null && !in_array($target, ['scheduled', 'resident'], true))
            || ($state === 'draining' && $target === null)
            || ($state === 'active' && $target !== null)) {
            return null;
        }
        return ['mode' => $mode, 'state' => $state, 'target_mode' => $target,
            'scheduled_trigger' => $trigger, 'revision' => (int) ($row['revision'] ?? 0)];
    }
}
