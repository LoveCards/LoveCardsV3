<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Domain;

/** Canonical SHA-256 used by release.json handler_catalog_hash. */
final class HandlerCatalogHasher
{
    /** @param array<string, mixed> $catalog */
    public static function hashArray(array $catalog): string
    {
        return hash('sha256', self::canonicalJson($catalog));
    }

    public static function hashFile(string $path): string
    {
        if (!is_file($path) || is_link($path) || !is_readable($path)) throw new \RuntimeException('handler-catalog.json 不存在或不可读');
        $json = file_get_contents($path);
        if ($json === false) throw new \RuntimeException('无法读取 handler-catalog.json');
        try { $catalog = json_decode($json, true, 128, JSON_THROW_ON_ERROR); }
        catch (\JsonException $exception) { throw new \RuntimeException('handler-catalog.json 不是有效 JSON', 0, $exception); }
        if (!is_array($catalog)) throw new \RuntimeException('handler-catalog.json 根节点必须是数组或对象');
        return self::hashArray($catalog);
    }

    /** @param mixed $value */
    private static function canonicalJson($value): string
    {
        return json_encode(self::canonicalize($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** @param mixed $value */
    private static function canonicalize($value)
    {
        if (!is_array($value)) return $value;
        if ($value === [] || array_keys($value) === range(0, count($value) - 1)) {
            return array_map(static fn($item) => self::canonicalize($item), $value);
        }
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) $value[$key] = self::canonicalize($item);
        return $value;
    }
}
