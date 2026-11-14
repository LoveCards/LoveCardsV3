<?php

declare(strict_types=1);

namespace app\modules\Platform\Config\Infrastructure;

use app\api\ApiException;
use think\facade\Db;

/** 配置控制路径直接读取 SQL，不依赖由本模块管理的缓存连接。 */
class ConfigService
{
    private const REVISION_KEY = '__revision';

    public static function init(): array
    {
        $results = [];
        foreach (glob(config_path() . 'apps/*.php') as $file) {
            $schema = include $file;
            if (is_array($schema)) $results[pathinfo($file, PATHINFO_FILENAME)] = self::register(pathinfo($file, PATHINFO_FILENAME), $schema);
        }
        return $results;
    }

    public static function register(string $group, array $schema, bool $clearCache = true): array
    {
        self::assertKeys($schema);
        return Db::transaction(static function () use ($group, $schema): array {
            $revision = self::lockRevision($group, true);
            $seeded = [];
            $skipped = [];
            foreach ($schema as $key => $definition) {
                $query = Db::table('configs')->where('group', $group)->where('key', $key);
                if ($query->find() !== null) { $skipped[] = "{$group}.{$key}"; continue; }
                self::insertValue($group, $key, $definition['default'] ?? '', $definition['type'] ?? 'string', $definition['description'] ?? '');
                $seeded[] = "{$group}.{$key}";
            }
            if ($seeded !== []) self::advanceRevision($group, $revision);
            return ['group' => $group, 'seeded' => $seeded, 'skipped' => $skipped];
        });
    }

    public static function fillDefaults(string $group, array $schema): array
    {
        self::assertKeys($schema);
        return Db::transaction(static function () use ($group, $schema): array {
            $revision = self::lockRevision($group, true);
            $filled = [];
            foreach ($schema as $key => $definition) {
                if (!array_key_exists('default', $definition)) continue;
                $row = Db::table('configs')->where('group', $group)->where('key', $key)->find();
                if ($row === null || ($row['value'] ?? null) !== '') continue;
                self::writeValue($group, $key, $definition['default']);
                $filled[] = "{$group}.{$key}";
            }
            if ($filled !== []) self::advanceRevision($group, $revision);
            return $filled;
        });
    }

    public static function get(string $key, $default = null)
    {
        if (!str_contains($key, '.')) return self::getGroup($key);
        [$group, $name] = explode('.', $key, 2);
        if ($name === self::REVISION_KEY) return $default;
        if ($group !== 'redis' && ($value = self::getEnvValue($group, $name)) !== null) {
            return self::castValue($value, self::getTypeFromSchema($group, $name));
        }
        $row = Db::table('configs')->where('group', $group)->where('key', $name)->find();
        return $row === null ? $default : self::castValue($row['value'], $row['type'] ?? 'string');
    }

    public static function getGroup(string $group): array
    {
        return self::getSnapshot($group)['values'];
    }

    /** 单次 SQL 查询取得值和版本，避免管理表单读到跨版本组合。 */
    public static function getSnapshot(string $group): array
    {
        $rows = Db::table('configs')->where('group', $group)->order('key', 'asc')->select()->toArray();
        $values = [];
        $revision = null;
        foreach ($rows as $row) {
            if ($row['key'] === self::REVISION_KEY) { $revision = (int) $row['value']; continue; }
            $value = $row['value'];
            if ($group !== 'redis') $value = self::getEnvValue($group, $row['key']) ?? $value;
            $values[$row['key']] = self::castValue($value, $row['type'] ?? 'string');
        }
        return ['values' => $values, 'revision' => $revision];
    }

    public static function getSchema(string $group): array
    {
        $schema = [];
        foreach (Db::table('configs')->where('group', $group)->select()->toArray() as $row) {
            if ($row['key'] === self::REVISION_KEY) continue;
            $schema[$row['key']] = ['type' => $row['type'] ?? 'string', 'default' => $row['value'] ?? '', 'description' => $row['description'] ?? ''];
        }
        return $schema;
    }

    public static function getSchemaGroups(): array
    {
        return Db::table('configs')->where('key', '<>', self::REVISION_KEY)->distinct(true)->column('group');
    }

    public static function isEnvironmentManaged(string $group, string $key): bool
    {
        return $group !== 'redis' && self::getEnvValue($group, $key) !== null;
    }

    public static function set(string $key, $value): bool
    {
        if (!str_contains($key, '.')) throw new \InvalidArgumentException("Config key must be 'group.name'");
        [$group, $name] = explode('.', $key, 2);
        return self::setGroup($group, [$name => $value]);
    }

    public static function setGroup(string $group, array $config): bool
    {
        self::assertKeys($config);
        Db::transaction(static function () use ($group, $config): void {
            $revision = self::lockRevision($group, true);
            foreach ($config as $key => $value) self::writeValue($group, $key, $value);
            if ($config !== []) self::advanceRevision($group, $revision);
        });
        return true;
    }

    /** 所有管理组在同一事务提交，返回锁内捕获的确定快照。 */
    public static function compareAndSwapGroups(array $groups, array $expectedRevisions, ?callable $validate = null): array
    {
        ksort($groups, SORT_STRING);
        foreach ($groups as $config) self::assertKeys($config);
        return Db::transaction(static function () use ($groups, $expectedRevisions, $validate): array {
            $revisions = [];
            foreach ($groups as $group => $config) {
                $revision = self::lockRevision($group, false);
                if (($expectedRevisions[$group] ?? null) !== $revision) {
                    throw new ApiException('配置已被其他操作修改，请重新加载后重试', ApiException::CODE_CONFIG_CONFLICT, ['reason_code' => 'stale-config-revision']);
                }
                $revisions[$group] = $revision;
            }
            if ($validate !== null) $validate();
            $result = [];
            foreach ($groups as $group => $config) {
                foreach ($config as $key => $value) self::writeValue($group, $key, $value);
                if ($config !== []) self::advanceRevision($group, $revisions[$group]);
                $result[$group] = self::getSnapshot($group);
            }
            return $result;
        });
    }

    public static function deleteGroup(string $group): bool
    {
        Db::transaction(static function () use ($group): void {
            $revision = self::lockRevision($group, true);
            Db::table('configs')->where('group', $group)->where('key', '<>', self::REVISION_KEY)->delete();
            self::advanceRevision($group, $revision);
        });
        return true;
    }

    public static function deleteKey(string $group, string $key): bool
    {
        self::assertKeys([$key => null]);
        Db::transaction(static function () use ($group, $key): void {
            $revision = self::lockRevision($group, true);
            $deleted = Db::table('configs')->where('group', $group)->where('key', $key)->delete();
            if ($deleted > 0) self::advanceRevision($group, $revision);
        });
        return true;
    }

    /** 权威配置不再缓存；保留已有公开重载接口，无需访问受管理 Redis。 */
    public static function reload(?string $group = null): void {}

    private static function assertKeys(array $config): void
    {
        if (array_key_exists(self::REVISION_KEY, $config)) throw new \InvalidArgumentException('配置版本为保留元数据，不能直接修改');
    }

    private static function lockRevision(string $group, bool $initialize): int
    {
        $query = Db::table('configs')->where('group', $group)->where('key', self::REVISION_KEY);
        $row = $query->lock(true)->find();
        if ($row === null && $initialize) {
            self::insertValue($group, self::REVISION_KEY, 1, 'int', '内部配置组版本');
            return 1;
        }
        $revision = $row === null ? 0 : filter_var($row['value'], FILTER_VALIDATE_INT);
        if ($revision === false || $revision < 1) throw ApiException::capabilityUnavailable('配置版本未就绪，请先完成数据库迁移', 'config-revision-unavailable');
        return $revision;
    }

    private static function advanceRevision(string $group, int $revision): void
    {
        Db::table('configs')->where('group', $group)->where('key', self::REVISION_KEY)->update([
            'value' => (string) ($revision + 1), 'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private static function writeValue(string $group, string $key, mixed $value): void
    {
        $query = Db::table('configs')->where('group', $group)->where('key', $key);
        $row = $query->find();
        if ($row === null) { self::insertValue($group, $key, $value, self::detectType($value)); return; }
        $query->update(['value' => self::encodeValue($value), 'updated_at' => gmdate('Y-m-d H:i:s')]);
    }

    private static function insertValue(string $group, string $key, mixed $value, string $type, string $description = ''): void
    {
        Db::table('configs')->insert(['group' => $group, 'key' => $key, 'value' => self::encodeValue($value),
            'type' => $type, 'description' => $description, 'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s')]);
    }

    private static function encodeValue(mixed $value): string
    {
        if (is_array($value)) return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (is_bool($value)) return $value ? '1' : '0';
        return (string) $value;
    }

    protected static function getEnvValue(string $group, string $name): ?string
    {
        $tryKeys = [
            strtoupper("{$group}.{$name}"),
            strtoupper(str_replace('.', '_', "{$group}.{$name}")),
        ];

        if (strpos($group, 'storage_') === 0) {
            $shortGroup = substr($group, 8);
            $tryKeys[] = strtoupper("{$shortGroup}_{$name}");
        }

        foreach ($tryKeys as $tryKey) {
            $val = env($tryKey);
            if ($val !== null && $val !== '') {
                if (is_bool($val)) return $val ? '1' : '0';
                if (is_int($val) || is_float($val) || is_string($val)) return (string) $val;
                throw new \RuntimeException('环境配置值必须是标量：' . $tryKey);
            }
        }

        return null;
    }

    /**
     * 从 schema 获取字段类型
     */
    protected static function getTypeFromSchema(string $group, string $name): string
    {
        $schema = self::getSchema($group);
        return $schema[$name]['type'] ?? 'string';
    }

    /**
     * 类型转换
     */
    protected static function castValue($value, string $type)
    {
        switch ($type) {
            case 'bool':
                return in_array($value, ['1', 'true', 'yes'], true);
            case 'int':
                if ($value === '' || $value === null) return 0;
                return (int) $value;
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }

    /**
     * 自动检测类型
     */
    protected static function detectType($value): string
    {
        if (is_bool($value)) return 'bool';
        if (is_int($value)) return 'int';
        if (is_array($value)) return 'json';
        return 'string';
    }
}
