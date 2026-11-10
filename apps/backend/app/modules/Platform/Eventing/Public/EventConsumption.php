<?php
declare(strict_types=1);

namespace app\modules\Platform\Eventing\Public;

use app\modules\Platform\Eventing\Infrastructure\ThinkDbEventConsumptionStore;
use think\facade\Db;

/** 事件消费策略公开入口；通信控制面只依赖此入口。 */
final class EventConsumption
{
    public function current(): ?array
    {
        return $this->store()->current();
    }

    /** 只能在调用者的 MySQL 事务内调用，锁必须在通信配置行之前取得。 */
    public function lockResidentForRealtime(): bool
    {
        return $this->store()->lockResidentForRealtime();
    }

    public function switchMode(string $target, int $expectedRevision, ?int $actorId, ?callable $audit = null, ?string $scheduledTrigger = null, ?callable $requirePolling = null): array
    {
        return $this->store()->switchMode($target, $expectedRevision, $actorId, $audit, $scheduledTrigger, $requirePolling);
    }

    /** 首次引入定时策略的升级门禁：新代码停掉请求内执行前必须见到外部触发。 */
    public function assertUpgradeActivationReady(): void
    {
        $state = $this->current();
        if ($state === null || $state['state'] !== 'active') {
            throw new EventConsumptionConflict('event-consumer-unavailable', '事件消费者未就绪，不能激活新 Release');
        }
        if ($state['mode'] === 'resident') return;
        $trigger = (string) ($state['scheduled_trigger'] ?? 'none');
        if (!in_array($trigger, ['cli', 'http'], true)) {
            throw new EventConsumptionConflict('scheduled-trigger-unavailable', '请先配置并验证外部 cron 定时消费，再激活新 Release');
        }
        try {
            $latest = Db::table('scheduler_runs')
                ->order('run_id', 'desc')
                ->find();
        } catch (\Throwable $exception) {
            throw new EventConsumptionConflict('scheduled-trigger-unavailable', '无法验证外部 cron 定时消费运行记录');
        }
        $observedSource = (string) ($latest['trigger_source'] ?? '');
        $sourceMatches = $observedSource === $trigger || ($trigger === 'cli' && $observedSource === 'legacy');
        if (!is_array($latest) || !$sourceMatches
            || (string) ($latest['outcome'] ?? '') !== 'ok'
            || (string) ($latest['finished_at'] ?? '') < gmdate('Y-m-d H:i:s', time() - 3600)) {
            throw new EventConsumptionConflict('scheduled-trigger-unavailable', '请先配置外部 cron 并确认最近一小时运行成功，再激活新 Release');
        }
    }

    private function store(): ThinkDbEventConsumptionStore
    {
        return new ThinkDbEventConsumptionStore();
    }
}
