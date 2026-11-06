<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Contract;

/**
 * 通知策略存储 Port（契约 §5）。快照按 policy_revision 冻结，
 * 修改以 CAS 生成新版本，绝不原地改写历史快照。
 */
interface NotificationPolicyStore
{
    /**
     * 某版本的全量策略快照：event_type => {enabled, locked, user_disableable, notification_type}。
     * 版本不存在（未迁移/历史）返回 null = not-configured。
     *
     * @return array<string, array{enabled: bool, locked: bool, user_disableable: bool, notification_type: ?string}>|null
     */
    public function snapshot(int $policyRevision): ?array;

    /** 当前全局版本；无目录返回 null。 */
    public function currentRevision(): ?int;

    /**
     * 当前版本的规则行（管理视图）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function listCurrent(): array;

    /**
     * CAS 批量修改并生成新 revision（同事务）。
     *
     * @param array<int, array{event_type: string, event_version: int, rule_key: string, enabled: bool}> $changes
     * @return array{revision: int, policies: array<int, array<string, mixed>>}
     */
    public function applyChanges(array $changes, int $expectedRevision, ?int $actorId, ?string $reason): array;
}
