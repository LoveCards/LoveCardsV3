<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Infrastructure;

use app\modules\Operations\SystemLifecycle\Contract\ReleaseEnvironment;
use app\modules\Platform\Eventing\Public\HandlerRegistry;
use think\facade\Db;

final class NativeReleaseEnvironment implements ReleaseEnvironment
{
    public function __construct(private HandlerRegistry $handlers) {}

    public function phpVersion(): string
    {
        return PHP_VERSION;
    }

    public function mysqlVersion(): string
    {
        $rows = Db::query('SELECT VERSION() AS `version`');
        $raw = (string) ($rows[0]['version'] ?? '');
        preg_match_all('/[0-9]+\.[0-9]+(?:\.[0-9]+)?/', $raw, $matches);
        if (($matches[0] ?? []) === []) throw new \RuntimeException('无法读取 MySQL 版本');
        $versions = $matches[0];
        return stripos($raw, 'mariadb') !== false ? (string) end($versions) : (string) $versions[0];
    }

    public function hasExtension(string $extension): bool
    {
        return extension_loaded($extension);
    }

    public function handlerCatalogHash(): string
    {
        return $this->handlers->catalogHash();
    }
}
