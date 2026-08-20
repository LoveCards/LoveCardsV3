<?php

declare(strict_types=1);

namespace app\shared\Runtime;

final class FacilityResolver
{
    public function resolve(
        FacilityConfig $config,
        FacilityProbe $probe,
        ?\DateTimeImmutable $checkedAt = null
    ): FacilityStatus {
        $checkedAt = $checkedAt ?: new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $mode = $config->desiredMode();

        if ($mode === FacilityMode::OFF) {
            return $this->status($config, false, false, false, null, 'disabled_by_config', $checkedAt, true);
        }

        if (!$config->configured()) {
            if ($mode === FacilityMode::AUTO && $config->fallbackProvider() !== null) {
                return $this->status(
                    $config,
                    false,
                    false,
                    false,
                    $config->fallbackProvider(),
                    'primary_not_configured',
                    $checkedAt,
                    true
                );
            }

            return $this->status(
                $config,
                false,
                false,
                false,
                null,
                'primary_not_configured',
                $checkedAt,
                $mode !== FacilityMode::REQUIRED
            );
        }

        try {
            $result = $probe->probe($config->name(), $config->primaryProvider());
        } catch (\Throwable $exception) {
            $result = FacilityProbeResult::unreachable('probe_failed');
        }

        if ($result->healthyState()) {
            return $this->status(
                $config,
                $result->supported(),
                $result->reachable(),
                true,
                $config->primaryProvider(),
                'healthy',
                $checkedAt,
                true
            );
        }

        $fallback = $mode === FacilityMode::AUTO ? $config->fallbackProvider() : null;

        return $this->status(
            $config,
            $result->supported(),
            $result->reachable(),
            false,
            $fallback,
            $result->reasonCode(),
            $checkedAt,
            $mode !== FacilityMode::REQUIRED
        );
    }

    private function status(
        FacilityConfig $config,
        bool $supported,
        bool $reachable,
        bool $healthy,
        ?string $effectiveProvider,
        string $reasonCode,
        \DateTimeImmutable $checkedAt,
        bool $ready
    ): FacilityStatus {
        return new FacilityStatus(
            $config->name(),
            $config->desiredMode(),
            $config->configured(),
            $supported,
            $reachable,
            $healthy,
            $effectiveProvider,
            $config->fallbackProvider(),
            $reasonCode,
            $checkedAt,
            $ready
        );
    }
}
