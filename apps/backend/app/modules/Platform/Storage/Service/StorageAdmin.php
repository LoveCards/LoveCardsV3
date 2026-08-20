<?php

namespace app\modules\Platform\Storage\Service;

use app\modules\Platform\Storage\Infrastructure\StorageFactory;
use app\modules\Platform\Storage\Infrastructure\ChannelManager;
use app\modules\Platform\Config\Public\Config as ConfigService;

/**
 * 存储渠道管理用例；驱动发现和物理调用由 Infrastructure 提供。
 */
final class StorageAdmin
{
    public static function meta(string $type): ?array
    {
        $driverClass = StorageFactory::getDriverClass($type);
        if (!$driverClass) {
            return null;
        }

        $meta = $driverClass::meta();
        $meta['fields'] = self::normalizeFields($type, $meta['fields'] ?? []);
        $schema = [];
        foreach ($meta['fields'] ?? [] as $field) {
            $schema[$field['key']] = [
                'type' => StorageFactory::mapMetaType($field['type'] ?? 'text'),
                'default' => $field['default'] ?? '',
                'description' => $field['label'] ?? $field['key'],
            ];
        }

        return [
            'type' => $type,
            'name' => $meta['name'] ?? $type,
            'icon' => $meta['icon'] ?? '',
            'schema' => $schema,
            'group' => 'storage_' . $type,
        ];
    }

    public static function install(): array
    {
        $results = [];
        foreach (StorageFactory::getRegisteredTypes() as $type) {
            $meta = self::meta($type);
            if ($meta && !empty($meta['schema'])) {
                $results[] = ConfigService::register($meta['group'], $meta['schema']);
                $results[] = ['filled' => ConfigService::fillDefaults($meta['group'], $meta['schema'])];
            }
        }

        $results[] = ConfigService::register('storage', [
            'default' => ['type' => 'string', 'default' => 'local', 'description' => '默认存储渠道'],
            'rate_limit_max' => ['type' => 'int', 'default' => 10, 'description' => '限流次数'],
            'rate_limit_window' => ['type' => 'int', 'default' => 60, 'description' => '限流窗口(秒)'],
            'direct_upload_expire' => ['type' => 'int', 'default' => 3600, 'description' => '直传过期时间(秒)'],
        ]);
        return $results;
    }

    public static function types(): array
    {
        $result = [];
        foreach (StorageFactory::getRegisteredTypes() as $type) {
            $driverClass = StorageFactory::getDriverClass($type);
            $meta = $driverClass ? $driverClass::meta() : [];
            $result[] = [
                'type' => $type,
                'name' => $meta['name'] ?? $type,
                'icon' => $meta['icon'] ?? 'mdi-cloud',
            ];
        }
        return $result;
    }

    public static function channels(): array
    {
        $result = [];
        foreach (StorageFactory::getRegisteredTypes() as $type) {
            $driverClass = StorageFactory::getDriverClass($type);
            if (!$driverClass) {
                continue;
            }
            $meta = $driverClass::meta();
            $meta['fields'] = self::normalizeFields($type, $meta['fields'] ?? []);
            $result[] = [
                'slug' => $type,
                'name' => $meta['name'] ?? $type,
                'icon' => $meta['icon'] ?? 'mdi-cloud',
                'fields' => $meta['fields'] ?? [],
                'configured' => ChannelManager::exists($type),
                'available' => ChannelManager::isAvailable($type),
            ];
        }
        return $result;
    }

    private static function normalizeFields(string $type, array $fields): array
    {
        if ($type !== 'local') return $fields;
        return [
            ['key' => 'root', 'label' => '存储根目录', 'type' => 'text', 'default' => 'public/storage'],
            ['key' => 'url_prefix', 'label' => 'URL前缀', 'type' => 'text', 'default' => '/storage'],
            ['key' => 'allow_mime_types', 'label' => '允许的MIME类型', 'type' => 'text', 'default' => 'image/jpeg,image/png,image/gif,image/webp,application/pdf'],
            ['key' => 'max_file_size', 'label' => '最大文件大小(字节)', 'type' => 'number', 'default' => 10485760],
            ['key' => 'path_template', 'label' => '路径模板', 'type' => 'text', 'default' => '{date}/{uuid}.{ext}'],
        ];
    }
}
