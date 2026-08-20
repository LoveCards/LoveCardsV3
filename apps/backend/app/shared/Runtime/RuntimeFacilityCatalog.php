<?php
declare(strict_types=1);

namespace app\shared\Runtime;

use think\facade\Config;

final class RuntimeFacilityCatalog
{
    /** @return FacilityConfig[] */
    public function configs(): array
    {
        $rows = Config::get('runtime.facilities', []); $out = [];
        foreach ($rows as $row) {
            $out[] = new FacilityConfig((string) $row['name'], (string) ($row['mode'] ?? FacilityMode::AUTO), (bool) ($row['configured'] ?? false), (string) ($row['provider'] ?? 'none'), isset($row['fallback']) ? (string) $row['fallback'] : null);
        }
        return $out;
    }
}
