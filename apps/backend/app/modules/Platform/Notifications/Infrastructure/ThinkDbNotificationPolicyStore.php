<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Infrastructure;

use app\modules\Platform\Notifications\Contract\NotificationPolicyStore;
use think\facade\Db;

/**
 * notification_policies / notification_policy_versions 存储实现（契约 §5.1）。
 * 修改在单事务内：CAS 校验期望版本 → 更新行 → 写入新版本快照（canonical order）。
 */
final class ThinkDbNotificationPolicyStore implements NotificationPolicyStore
{
    public function snapshot(int $policyRevision): ?array
    {
        if ($policyRevision < 1) {
            return null;
        }
        try {
            $json = Db::table('notification_policy_versions')
                ->where('policy_revision', $policyRevision)
                ->value('snapshot_json');
        } catch (\Throwable $exception) {
            return null;
        }
        if (!is_string($json) || $json === '') {
            return null;
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return null;
        }
        $snapshot = [];
        foreach ($decoded as $eventType => $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $snapshot[(string) $eventType] = [
                'enabled' => (bool) ($rule['enabled'] ?? true),
                'locked' => (bool) ($rule['locked'] ?? false),
                'user_disableable' => (bool) ($rule['user_disableable'] ?? true),
                'notification_type' => isset($rule['notification_type']) ? (string) $rule['notification_type'] : null,
            ];
        }
        return $snapshot;
    }

    public function currentRevision(): ?int
    {
        try {
            $revision = Db::table('notification_policy_versions')->max('policy_revision');
        } catch (\Throwable $exception) {
            return null;
        }
        return $revision === null ? null : (int) $revision;
    }

    public function listCurrent(): array
    {
        $revision = $this->currentRevision();
        if ($revision === null) {
            return [];
        }
        $rows = Db::table('notification_policies')
            ->where('policy_revision', $revision)
            ->order('event_type', 'asc')
            ->select()
            ->toArray();
        return array_map(static fn(array $row): array => [
            'event_type' => (string) $row['event_type'],
            'event_version' => (int) $row['event_version'],
            'rule_key' => (string) $row['rule_key'],
            'enabled' => (bool) $row['enabled'],
            'notification_type' => isset($row['notification_type']) && $row['notification_type'] !== null ? (string) $row['notification_type'] : null,
            'recipient_rule' => (string) $row['recipient_rule'],
            'user_disableable' => (bool) $row['user_disableable'],
            'locked' => (bool) $row['locked'],
            'policy_revision' => (int) $row['policy_revision'],
        ], $rows);
    }

    public function applyChanges(array $changes, int $expectedRevision, ?int $actorId, ?string $reason): array
    {
        $newRevision = $expectedRevision + 1;
        $now = gmdate('Y-m-d H:i:s.u');
        $before = null;
        Db::transaction(function () use ($changes, $expectedRevision, $newRevision, $actorId, $reason, $now, &$before): void {
            // 先锁住当前 revision 的全部规则行，再做 CAS。两个并发请求只能有一个拿到这把锁；
            // 后续请求会看不到 expected_revision，从而稳定返回 stale-revision。
            $lockedRows = Db::table('notification_policies')
                ->where('policy_revision', $expectedRevision)
                ->lock(true)
                ->select();
            if ($lockedRows->isEmpty()) {
                throw new \RuntimeException('stale-revision');
            }

            $before = $this->listCurrent();
            $snapshot = $this->snapshot($expectedRevision) ?? [];
            foreach ($changes as $change) {
                $key = $change['event_type'] . '|' . $change['event_version'] . '|' . $change['rule_key'];
                $snapshotKey = $change['event_type'];
                if (!isset($snapshot[$snapshotKey])) {
                    throw new \RuntimeException('unknown-rule: ' . $key);
                }
                if ($snapshot[$snapshotKey]['locked']) {
                    throw new \RuntimeException('locked: ' . $key);
                }
                $snapshot[$snapshotKey]['enabled'] = $change['enabled'];
            }
            ksort($snapshot);

            // 全部现行行晋升到新版本；仅变更行改写 enabled。
            $updated = Db::table('notification_policies')
                ->where('policy_revision', $expectedRevision)
                ->update(['policy_revision' => $newRevision, 'updated_by' => $actorId, 'updated_at' => $now]);
            if ($updated !== $lockedRows->count()) {
                throw new \RuntimeException('stale-revision');
            }
            foreach ($changes as $change) {
                Db::table('notification_policies')
                    ->where('event_type', $change['event_type'])
                    ->where('event_version', $change['event_version'])
                    ->where('rule_key', $change['rule_key'])
                    ->where('policy_revision', $newRevision)
                    ->update(['enabled' => $change['enabled'] ? 1 : 0, 'updated_by' => $actorId, 'updated_at' => $now]);
            }
            Db::table('notification_policy_versions')->insert([
                'policy_revision' => $newRevision,
                'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'source' => 'admin',
                'reason' => $reason,
                'created_by' => $actorId,
                'created_at' => $now,
            ]);
        });

        return ['revision' => $newRevision, 'policies' => $this->listCurrent(), 'before' => $before ?? []];
    }
}
