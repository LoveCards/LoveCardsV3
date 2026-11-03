<?php

namespace app\modules\Theme\Infrastructure;

use app\modules\Theme\Domain\Hostname;
use think\facade\Db;

final class ThemeBindingRepository
{
    public function find(string $host): ?array
    {
        $host = Hostname::normalize($host);
        $row = Db::table('theme_bindings')->where('hostname', $host)->where('enabled', 1)->find();
        return $row ? $this->decode($row) : null;
    }

    public function list(): array
    {
        return array_map(fn(array $row): array => $this->decode($row), Db::table('theme_bindings')->order('hostname')->select()->toArray());
    }

    public function save(array $binding): int
    {
        $host = Hostname::normalize((string)$binding['hostname']);
        $themeId = (string)($binding['theme_id'] ?? '');
        $releaseKey = (string)($binding['release_key'] ?? '');
        if (!preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_-]{0,63}$/', $themeId)) throw new \InvalidArgumentException('主题 id 不合法');
        if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?-[a-f0-9]{16}$/', $releaseKey)) throw new \InvalidArgumentException('主题 release 不合法');
        try { $configOverrides = json_encode($binding['config_overrides'] ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); }
        catch (\Throwable $e) { throw new \InvalidArgumentException('主题绑定配置无法序列化', 0, $e); }
        $values = [
            'hostname' => $host,
            'theme_id' => $themeId,
            'release_key' => $releaseKey,
            'config_overrides' => $configOverrides,
            'enabled' => (int)($binding['enabled'] ?? 1),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if (empty($binding['id'])) {
            $existing = Db::table('theme_bindings')->where('hostname', $host)->find();
            if ($existing) $binding['id'] = $existing['id'];
        }
        if (!empty($binding['id'])) {
            Db::table('theme_bindings')->where('id', (int)$binding['id'])->update($values);
            return (int)$binding['id'];
        }
        $values['created_at'] = date('Y-m-d H:i:s');
        return (int)Db::table('theme_bindings')->insertGetId($values);
    }

    public function delete(int $id): void
    {
        Db::table('theme_bindings')->where('id', $id)->delete();
    }

    public function isBound(string $themeId, string $releaseKey): bool
    {
        return (bool)Db::table('theme_bindings')
            ->where('theme_id', $themeId)
            ->where('release_key', $releaseKey)
            ->where('enabled', 1)
            ->find();
    }

    private function decode(array $row): array
    {
        $decoded = json_decode((string)($row['config_overrides'] ?? '{}'), true);
        if (!is_array($decoded)) throw new \RuntimeException('主题绑定配置损坏');
        $row['config_overrides'] = $decoded;
        return $row;
    }
}
