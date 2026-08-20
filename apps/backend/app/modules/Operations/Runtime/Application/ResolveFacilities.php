<?php

declare(strict_types=1);

namespace app\modules\Operations\Runtime\Application;

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
            $statuses[$config->name()] = $this->resolver->resolve($config, $this->probe, $checkedAt);
        }

        return $statuses;
    }
}
