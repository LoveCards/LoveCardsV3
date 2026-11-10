<?php
declare(strict_types=1);

namespace app\modules\Platform\Eventing\Infrastructure;

use think\facade\Db;

/** 单行 CAS 配置；事件和 Handler 的代码注册仍是目录权威。 */
final class ThinkDbSignalPolicyStore
{
    public function catalog(array $registrations): array
    {
        return $this->renderCatalog($registrations, $this->current());
    }

    private function renderCatalog(array $registrations, array $state): array
    {
        $items = [];
        foreach ($registrations as $registration) {
            $key = (string) $registration['key'];
            $items[] = $registration + [
                'enabled' => array_key_exists($key, $state['overrides'])
                    ? (bool) $state['overrides'][$key]
                    : (bool) $registration['default_enabled'],
            ];
        }
        return ['revision' => $state['revision'], 'signals' => $items];
    }

    public function enabled(string $key, array $registrations): bool
    {
        foreach ($registrations as $registration) {
            if ($registration['key'] !== $key) continue;
            $state = $this->current();
            return array_key_exists($key, $state['overrides'])
                ? (bool) $state['overrides'][$key]
                : (bool) $registration['default_enabled'];
        }
        throw new \InvalidArgumentException('未注册的主动更新信号');
    }

    /** @param callable(array,array,int):void $audit 在同一数据库事务中写审计。 */
    public function applyChanges(array $registrations, array $changes, int $expectedRevision, int $actorId, callable $audit): array
    {
        if ($expectedRevision < 1 || $changes === []) throw new \InvalidArgumentException('策略版本或变更为空');
        $known = [];
        foreach ($registrations as $registration) $known[$registration['key']] = true;
        $parsed = [];
        foreach ($changes as $change) {
            if (!is_array($change) || !is_string($change['key'] ?? null)
                || !array_key_exists($change['key'], $known)
                || !is_bool($change['enabled'] ?? null)
                || array_key_exists($change['key'], $parsed)) {
                throw new \InvalidArgumentException('主动更新策略包含未知、重复或非法的规则');
            }
            $parsed[$change['key']] = $change['enabled'];
        }

        $updatedState = Db::transaction(function () use ($parsed, $expectedRevision, $actorId, $audit): array {
            $row = Db::table('signal_policy_state')->where('id', 1)->lock(true)->find();
            if ($row === null || (int) $row['revision'] !== $expectedRevision) {
                throw new \RuntimeException('stale-revision');
            }
            $before = $this->decode($row['overrides_json']);
            $after = array_merge($before, $parsed);
            ksort($after, SORT_STRING);
            $updated = Db::table('signal_policy_state')->where('id', 1)->where('revision', $expectedRevision)->update([
                'overrides_json' => json_encode($after, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'revision' => $expectedRevision + 1,
                'updated_by' => $actorId,
                'updated_at' => gmdate('Y-m-d H:i:s.u'),
            ]);
            if ($updated !== 1) throw new \RuntimeException('stale-revision');
            $audit($before, $after, $expectedRevision + 1);
            return ['revision' => $expectedRevision + 1, 'overrides' => $after];
        });
        return $this->renderCatalog($registrations, $updatedState);
    }

    private function current(): array
    {
        $row = Db::table('signal_policy_state')->where('id', 1)->find();
        if ($row === null) throw new \RuntimeException('主动更新策略尚未初始化');
        return ['revision' => (int) $row['revision'], 'overrides' => $this->decode($row['overrides_json'])];
    }

    private function decode(mixed $json): array
    {
        $value = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($value)) throw new \UnexpectedValueException('主动更新策略数据损坏');
        foreach ($value as $key => $enabled) {
            if (!is_string($key) || !is_bool($enabled)) throw new \UnexpectedValueException('主动更新策略数据损坏');
        }
        return $value;
    }
}
