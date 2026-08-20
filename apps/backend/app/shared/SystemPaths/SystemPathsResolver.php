<?php
declare(strict_types=1);

namespace app\shared\SystemPaths;

/** 框架组合根负责把配置数组交给本解析器。 */
final class SystemPathsResolver
{
    public const ENV_INSTANCE_ROOT = 'LC_INSTANCE_ROOT';

    /** @param array{instance_root?: mixed} $config */
    public function resolve(array $config, string $activeReleaseRoot): SystemPaths
    {
        $instanceRoot = $config['instance_root'] ?? null;
        if (!is_string($instanceRoot) || trim($instanceRoot) === '') {
            throw new \InvalidArgumentException('缺少 SystemPaths 配置 instance_root');
        }
        return new SystemPaths($instanceRoot, $activeReleaseRoot);
    }

    /** @param array<string, mixed> $environment */
    public function resolveEnvironment(array $environment, string $activeReleaseRoot): SystemPaths
    {
        return $this->resolve(
            ['instance_root' => $environment[self::ENV_INSTANCE_ROOT] ?? null],
            $activeReleaseRoot
        );
    }
}
