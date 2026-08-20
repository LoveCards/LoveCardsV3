<?php

declare(strict_types=1);

namespace app\shared\Runtime;

interface FacilityProbe
{
    public function probe(string $facility, string $provider): FacilityProbeResult;
}
