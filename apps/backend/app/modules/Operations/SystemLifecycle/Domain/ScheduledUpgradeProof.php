<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Domain;

/** 迁移前成功触发与迁移期间暂停心跳共同证明可安全激活。 */
final class ScheduledUpgradeProof
{
    private const FRESH_SECONDS = 3600;

    public static function capture(array $state, ?array $latest, ?string $previousReleaseId, \DateTimeImmutable $now): array
    {
        self::assertState($state);
        $proof = [
            'mode' => $state['mode'],
            'previous_release_id' => $previousReleaseId,
            'captured_at' => $now->format(DATE_ATOM),
        ];
        if ($state['mode'] === 'resident') return $proof;
        $trigger = self::trigger($state);
        if (!is_array($latest) || (int) ($latest['run_id'] ?? 0) < 1
            || ($latest['trigger_source'] ?? null) !== $trigger
            || ($latest['outcome'] ?? null) !== 'ok'
            || ($previousReleaseId === null
                ? !in_array((string) ($latest['release_id'] ?? ''), ['', 'legacy'], true)
                : !hash_equals($previousReleaseId, (string) ($latest['release_id'] ?? '')))
            || !self::fresh((string) ($latest['finished_at'] ?? ''), $now)) {
            throw new \LogicException('迁移前缺少当前旧 Release 的成功定时触发');
        }
        return array_replace($proof, [
            'trigger' => $trigger,
            'run_id' => (int) $latest['run_id'],
            'release_id' => (string) $latest['release_id'],
        ]);
    }

    public static function assertForActivation(
        array $state, ?array $latest, array $proof, ?string $previousReleaseId,
        string $planId, \DateTimeImmutable $now
    ): void {
        self::assertState($state);
        if (($proof['mode'] ?? null) !== $state['mode']
            || ($proof['previous_release_id'] ?? null) !== $previousReleaseId) {
            throw new \LogicException('事件消费模式或旧 Release 已改变');
        }
        if ($state['mode'] === 'resident') return;
        $trigger = self::trigger($state);
        if (($proof['trigger'] ?? null) !== $trigger
            || (int) ($proof['run_id'] ?? 0) < 1
            || !is_array($latest)
            || ($latest['outcome'] ?? null) !== 'paused'
            || ($latest['worker_id'] ?? null) !== 'upgrade-pause-' . $planId
            || ($latest['trigger_source'] ?? null) !== $trigger
            || ($previousReleaseId === null
                ? !in_array((string) ($latest['release_id'] ?? ''), ['', 'legacy'], true)
                : ($latest['release_id'] ?? null) !== $previousReleaseId)
            || (int) ($latest['run_id'] ?? 0) <= (int) $proof['run_id']
            || !self::fresh((string) ($latest['finished_at'] ?? ''), $now)) {
            throw new \LogicException('迁移期间缺少当前旧 Release 的新鲜暂停心跳');
        }
    }

    private static function assertState(array $state): void
    {
        if (($state['state'] ?? null) !== 'active'
            || !in_array($state['mode'] ?? null, ['scheduled', 'resident'], true)) {
            throw new \LogicException('事件消费者未就绪');
        }
    }

    private static function trigger(array $state): string
    {
        $trigger = (string) ($state['scheduled_trigger'] ?? 'none');
        if (!in_array($trigger, ['cli', 'http'], true)) {
            throw new \LogicException('外部定时触发尚未配置');
        }
        return $trigger;
    }

    private static function fresh(string $value, \DateTimeImmutable $now): bool
    {
        try { $time = (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->getTimestamp(); }
        catch (\Throwable $ignored) { return false; }
        return $value !== '' && $time <= $now->getTimestamp()
            && $now->getTimestamp() - $time <= self::FRESH_SECONDS;
    }
}
