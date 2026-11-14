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
        $json = Db::table('notification_policy_versions')
            ->where('policy_revision', $policyRevision)
            ->value('snapshot_json');
        if ($json === null) return null;
        return $this->decodeSnapshot($json);
    }

    private function decodeSnapshot(mixed $json): array
    {
        if (!is_string($json) || $json === '') throw new \RuntimeException('通知策略快照为空或损坏');
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || $decoded === [] || array_is_list($decoded)) {
            throw new \RuntimeException('通知策略快照格式损坏');
        }
        $snapshot = [];
        foreach ($decoded as $eventType => $rule) {
            if (!is_string($eventType) || !is_array($rule)
                || !is_bool($rule['enabled'] ?? null) || !is_bool($rule['locked'] ?? null)
                || !is_bool($rule['user_disableable'] ?? null)
                || !array_key_exists('notification_type', $rule)
                || ($rule['notification_type'] !== null && !is_string($rule['notification_type']))) {
                throw new \RuntimeException('通知策略快照规则损坏');
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
        $revision = Db::table('notification_policy_versions')->max('policy_revision');
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
        return $this->normalizeRows($rows);
    }

    private function normalizeRows(array $rows): array
    {
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

    public function applyChanges(array $changes, int $expectedRevision, ?int $actorId, ?string $reason, callable $audit): array
    {
        $newRevision = $expectedRevision + 1;
        $now = gmdate('Y-m-d H:i:s.u');
        return Db::transaction(function () use ($changes, $expectedRevision, $newRevision, $actorId, $reason, $now, $audit): array {
            // 先锁版本行并重查最新版本；版本快照的唯一键仍是并发 CAS 的最终裁决。
            $version = Db::table('notification_policy_versions')
                ->where('policy_revision', $expectedRevision)
                ->lock(true)
                ->find();
            if ($version === null || $this->currentRevision() !== $expectedRevision) {
                throw new \RuntimeException('stale-revision');
            }
            $lockedRows = Db::table('notification_policies')
                ->where('policy_revision', $expectedRevision)
                ->order('event_type', 'asc')
                ->lock(true)
                ->select()->toArray();
            if ($lockedRows === []) {
                throw new \RuntimeException('stale-revision');
            }

            $before = $this->normalizeRows($lockedRows);
            $snapshot = $this->decodeSnapshot($version['snapshot_json'] ?? null);
            $byEvent = [];
            foreach ($before as $index => $row) {
                $eventType = $row['event_type'];
                if (isset($byEvent[$eventType])) throw new \RuntimeException('通知策略目录包含重复事件');
                $byEvent[$eventType] = $index;
                $expectedRule = array_intersect_key($row, array_flip(['enabled', 'locked', 'user_disableable', 'notification_type']));
                // 历史目录以 system.* 表示强制公告类型集合，行上的 system.moderation 只是默认实例类型。
                if ($eventType === 'system.notification.issued'
                    && $expectedRule['locked'] === true
                    && $expectedRule['notification_type'] === 'system.moderation'
                    && ($snapshot[$eventType]['notification_type'] ?? null) === 'system.*') {
                    $expectedRule['notification_type'] = 'system.*';
                }
                if (!isset($snapshot[$eventType]) || $snapshot[$eventType] != $expectedRule) {
                    throw new \RuntimeException('通知策略现行规则与快照不一致');
                }
            }
            if (count($snapshot) !== count($byEvent)) throw new \RuntimeException('通知策略现行规则与快照不一致');
            $after = $before;
            $seen = [];
            foreach ($changes as $change) {
                $key = $change['event_type'] . '|' . $change['event_version'] . '|' . $change['rule_key'];
                $snapshotKey = $change['event_type'];
                $index = $byEvent[$snapshotKey] ?? null;
                if ($index === null || $before[$index]['event_version'] !== $change['event_version']
                    || $before[$index]['rule_key'] !== $change['rule_key']) {
                    throw new \RuntimeException('unknown-rule: ' . $key);
                }
                if (isset($seen[$snapshotKey]) || !is_bool($change['enabled'])) throw new \RuntimeException('unknown-rule: 重复或非法规则');
                $seen[$snapshotKey] = true;
                if ($snapshot[$snapshotKey]['locked']) {
                    throw new \RuntimeException('locked: ' . $key);
                }
                $snapshot[$snapshotKey]['enabled'] = $change['enabled'];
                $after[$index]['enabled'] = $change['enabled'];
            }
            ksort($snapshot);
            foreach ($after as &$row) $row['policy_revision'] = $newRevision;
            unset($row);

            // 全部现行行晋升到新版本；仅变更行改写 enabled。
            $updated = Db::table('notification_policies')
                ->where('policy_revision', $expectedRevision)
                ->update(['policy_revision' => $newRevision, 'updated_by' => $actorId, 'updated_at' => $now]);
            if ($updated !== count($lockedRows)) {
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
            try {
                Db::table('notification_policy_versions')->insert([
                    'policy_revision' => $newRevision,
                    'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'source' => 'admin',
                    'reason' => $reason,
                    'created_by' => $actorId,
                    'created_at' => $now,
                ]);
            } catch (\Throwable $exception) {
                if (str_contains($exception->getMessage(), 'uq_np_rev')) {
                    throw new \RuntimeException('stale-revision', 0, $exception);
                }
                throw $exception;
            }
            $audit($before, $after, $newRevision);
            return ['revision' => $newRevision, 'policies' => $after, 'before' => $before];
        });
    }
}
