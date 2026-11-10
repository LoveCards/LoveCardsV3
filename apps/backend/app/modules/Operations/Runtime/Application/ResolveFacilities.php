<?php

declare(strict_types=1);

namespace app\modules\Operations\Runtime\Application;

use app\modules\Platform\Config\Public\RedisConnectionSettings;
use app\shared\Runtime\FacilityConfig;
use app\shared\Runtime\FacilityProbe;
use app\shared\Runtime\FacilityResolver;

final class ResolveFacilities
{
    private FacilityResolver $resolver;
    private FacilityProbe $probe;

    public function __construct(FacilityResolver $resolver, FacilityProbe $probe)
    {
        $this->resolver = $resolver;
        $this->probe = $probe;
    }

    /**
     * @param FacilityConfig[] $configs
     */
    public function execute(array $configs, ?\DateTimeImmutable $checkedAt = null): array
    {
        $statuses = [];
        foreach ($configs as $config) {
            if (!$config instanceof FacilityConfig) {
                throw new \InvalidArgumentException('RuntimeFacility 配置必须是 FacilityConfig');
            }
            if (isset($statuses[$config->name()])) {
                throw new \InvalidArgumentException("RuntimeFacility 重复定义：{$config->name()}");
            }
            if ($config->name() === 'eventing.wakeup') {
                // Redis 配置来自系统配置或显式环境托管；不能在配置文件加载时查数据库。
                try {
                    $configured = RedisConnectionSettings::current()['enabled'];
                } catch (\Throwable $ignored) {
                    $configured = false;
                }
                $config = new FacilityConfig(
                    $config->name(),
                    $config->desiredMode(),
                    $configured,
                    $config->primaryProvider()
                );
            }
            $statuses[$config->name()] = $this->resolver->resolve($config, $this->probe, $checkedAt);
        }

        return $statuses;
    }
}
