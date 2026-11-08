<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Domain;

use app\modules\Operations\SystemLifecycle\Exception\ReleaseManifestException;

final class ReleaseManifest
{
    private const SEMVER_PATTERN = '/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:-(?:0|[1-9A-Za-z-][0-9A-Za-z-]*)(?:\.(?:0|[1-9A-Za-z-][0-9A-Za-z-]*))*)?(?:\+(?:0|[1-9A-Za-z-][0-9A-Za-z-]*)(?:\.(?:0|[1-9A-Za-z-][0-9A-Za-z-]*))*)?$/';
    private const REQUIRED_FIELDS = [
        'schema_version', 'release_id', 'version', 'build', 'channel', 'sequence',
        'issued_at', 'expires_at', 'minimum_php', 'minimum_mysql', 'required_extensions',
        'optional_facilities', 'source_schema_range', 'target_schema', 'migration_checksums',
        'handler_catalog_hash', 'file_hashes', 'signature_key_id', 'signature',
    ];

    /** @var array<string, mixed> */
    private array $data;
    private \DateTimeImmutable $issuedAt;
    private \DateTimeImmutable $expiresAt;

    /** @param array<string, mixed> $data */
    private function __construct(array $data, \DateTimeImmutable $issuedAt, \DateTimeImmutable $expiresAt)
    {
        $this->data = $data;
        $this->issuedAt = $issuedAt;
        $this->expiresAt = $expiresAt;
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $missing = array_diff(self::REQUIRED_FIELDS, array_keys($data));
        $unknown = array_diff(array_keys($data), [...self::REQUIRED_FIELDS, 'release_notes']);
        if ($missing !== []) throw new ReleaseManifestException('Release Manifest 缺少字段: ' . implode(', ', $missing));
        if ($unknown !== []) throw new ReleaseManifestException('Release Manifest 包含未知字段: ' . implode(', ', $unknown));
        if ($data['schema_version'] !== 1) throw new ReleaseManifestException('不支持的 Release Manifest 协议版本');

        self::assertIdentifier($data['release_id'], 'release_id', 128);
        if (!is_string($data['version']) || preg_match(self::SEMVER_PATTERN, $data['version']) !== 1) {
            throw new ReleaseManifestException('Release version 必须是 SemVer');
        }
        if (array_key_exists('release_notes', $data)
            && (!is_string($data['release_notes']) || strlen($data['release_notes']) > 16000)) {
            throw new ReleaseManifestException('Release 更新说明不合法');
        }
        self::assertIdentifier($data['build'], 'build', 128);
        if (!is_string($data['channel']) || preg_match('/^[a-z][a-z0-9_-]{0,31}$/', $data['channel']) !== 1) {
            throw new ReleaseManifestException('Release channel 不合法');
        }
        if (!is_int($data['sequence']) || $data['sequence'] < 1) throw new ReleaseManifestException('Release sequence 必须是正整数');

        $issuedAt = self::parseDate($data['issued_at'], 'issued_at');
        $expiresAt = self::parseDate($data['expires_at'], 'expires_at');
        if ($expiresAt <= $issuedAt) throw new ReleaseManifestException('Release expires_at 必须晚于 issued_at');

        self::assertRuntimeVersion($data['minimum_php'], 'minimum_php');
        self::assertRuntimeVersion($data['minimum_mysql'], 'minimum_mysql');
        $data['required_extensions'] = self::normalizeStringList($data['required_extensions'], 'required_extensions');
        $data['optional_facilities'] = self::normalizeStringList($data['optional_facilities'], 'optional_facilities');

        if (!is_array($data['source_schema_range'])
            || array_keys($data['source_schema_range']) !== ['min', 'max']) {
            throw new ReleaseManifestException('source_schema_range 必须只包含 min 和 max');
        }
        self::assertSchemaId($data['source_schema_range']['min'], true, 'source_schema_range.min');
        self::assertSchemaId($data['source_schema_range']['max'], true, 'source_schema_range.max');
        self::assertSchemaId($data['target_schema'], false, 'target_schema');

        $data['migration_checksums'] = self::normalizeChecksumMap($data['migration_checksums'], true, 'migration_checksums');
        if (!is_string($data['handler_catalog_hash']) || preg_match('/^[a-f0-9]{64}$/', strtolower($data['handler_catalog_hash'])) !== 1) {
            throw new ReleaseManifestException('handler_catalog_hash 必须是 SHA-256');
        }
        $data['handler_catalog_hash'] = strtolower($data['handler_catalog_hash']);
        $data['file_hashes'] = self::normalizeChecksumMap($data['file_hashes'], false, 'file_hashes');
        if ($data['file_hashes'] === []) throw new ReleaseManifestException('file_hashes 不能为空');
        foreach (array_keys($data['file_hashes']) as $path) self::assertRelativePath($path);

        self::assertIdentifier($data['signature_key_id'], 'signature_key_id', 128);
        if (!is_string($data['signature']) || base64_decode($data['signature'], true) === false || base64_decode($data['signature'], true) === '') {
            throw new ReleaseManifestException('Release signature 必须是有效 Base64');
        }

        return new self($data, $issuedAt, $expiresAt);
    }

    public function releaseId(): string { return (string) $this->data['release_id']; }
    public function version(): string { return (string) $this->data['version']; }
    public function releaseNotes(): ?string { return isset($this->data['release_notes']) ? (string) $this->data['release_notes'] : null; }
    public function build(): string { return (string) $this->data['build']; }
    public function channel(): string { return (string) $this->data['channel']; }
    public function sequence(): int { return (int) $this->data['sequence']; }
    public function minimumPhp(): string { return (string) $this->data['minimum_php']; }
    public function minimumMysql(): string { return (string) $this->data['minimum_mysql']; }
    /** @return string[] */
    public function requiredExtensions(): array { return $this->data['required_extensions']; }
    /** @return string[] */
    public function optionalFacilities(): array { return $this->data['optional_facilities']; }
    /** @return array{min:string,max:string} */
    public function sourceSchemaRange(): array { return $this->data['source_schema_range']; }
    public function targetSchema(): string { return (string) $this->data['target_schema']; }
    public function handlerCatalogHash(): string { return (string) $this->data['handler_catalog_hash']; }
    public function issuedAt(): \DateTimeImmutable { return $this->issuedAt; }
    public function expiresAt(): \DateTimeImmutable { return $this->expiresAt; }
    public function signatureKeyId(): string { return (string) $this->data['signature_key_id']; }
    public function signatureBytes(): string { return (string) base64_decode((string) $this->data['signature'], true); }

    /** @return array<string, string> */
    public function migrationChecksums(): array { return $this->data['migration_checksums']; }
    /** @return array<string, string> */
    public function fileHashes(): array { return $this->data['file_hashes']; }
    /** @return array<string, mixed> */
    public function toArray(): array { return $this->data; }

    public function canonicalPayload(): string
    {
        $payload = $this->data;
        unset($payload['signature']);
        return json_encode(self::canonicalize($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function canonicalDocument(): string
    {
        return json_encode(
            self::canonicalize($this->data),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }

    public function documentChecksum(): string
    {
        return hash('sha256', $this->canonicalDocument());
    }

    private static function parseDate($value, string $field): \DateTimeImmutable
    {
        if (!is_string($value)
            || preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:\.[0-9]{1,6})?(?:Z|[+-][0-9]{2}:[0-9]{2})$/', $value) !== 1) {
            throw new ReleaseManifestException($field . ' 必须是 RFC3339 时间');
        }
        try {
            return new \DateTimeImmutable($value);
        } catch (\Throwable $exception) {
            throw new ReleaseManifestException($field . ' 不是有效时间', 0, $exception);
        }
    }

    private static function assertIdentifier($value, string $field, int $maxLength): void
    {
        if (!is_string($value) || strlen($value) > $maxLength || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $value) !== 1) {
            throw new ReleaseManifestException($field . ' 不合法');
        }
    }

    private static function assertRuntimeVersion($value, string $field): void
    {
        if (!is_string($value) || preg_match('/^[0-9]+\.[0-9]+(?:\.[0-9]+)?$/', $value) !== 1) {
            throw new ReleaseManifestException($field . ' 不合法');
        }
    }

    private static function assertSchemaId($value, bool $allowZero, string $field): void
    {
        if (!is_string($value)) throw new ReleaseManifestException($field . ' 不合法');
        if ($allowZero && $value === '0') return;
        if (preg_match('/^[0-9]{14}(?:_[a-z0-9][a-z0-9_-]{0,63})?$/', $value) !== 1) {
            throw new ReleaseManifestException($field . ' 不合法');
        }
    }

    /** @return string[] */
    private static function normalizeStringList($value, string $field): array
    {
        if (!is_array($value) || !self::isList($value)) throw new ReleaseManifestException($field . ' 必须是数组');
        $normalized = [];
        foreach ($value as $item) {
            if (!is_string($item) || preg_match('/^[a-z][a-z0-9._-]{0,63}$/', $item) !== 1) {
                throw new ReleaseManifestException($field . ' 包含非法值');
            }
            $normalized[$item] = true;
        }
        $items = array_keys($normalized);
        sort($items, SORT_STRING);
        return $items;
    }

    /** @return array<string, string> */
    private static function normalizeChecksumMap($value, bool $migrationIds, string $field): array
    {
        if (!is_array($value) || self::isList($value) && $value !== []) throw new ReleaseManifestException($field . ' 必须是对象');
        $normalized = [];
        foreach ($value as $key => $checksum) {
            // PHP casts all-digit JSON object keys to integers when decoding to an array.
            if ((!is_string($key) && !is_int($key)) || !is_string($checksum) || preg_match('/^[a-f0-9]{64}$/', strtolower($checksum)) !== 1) {
                throw new ReleaseManifestException($field . ' 包含非法 Checksum');
            }
            $key = (string) $key;
            if ($migrationIds) self::assertSchemaId($key, false, $field . ' key');
            $normalized[$key] = strtolower($checksum);
        }
        ksort($normalized, SORT_STRING);
        return $normalized;
    }

    private static function assertRelativePath(string $path): void
    {
        if ($path === '' || strpos($path, "\0") !== false || strpos($path, '\\') !== false || $path[0] === '/' || preg_match('/^[A-Za-z]:/', $path)) {
            throw new ReleaseManifestException('file_hashes 包含非法路径');
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || preg_match('/[<>:"|?*]/', $segment) || preg_match('/[. ]$/', $segment)) {
                throw new ReleaseManifestException('file_hashes 包含非法路径');
            }
        }
    }

    private static function canonicalize($value)
    {
        if (!is_array($value)) return $value;
        if (self::isList($value)) {
            return array_map(static function ($item) { return self::canonicalize($item); }, $value);
        }
        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) $value[$key] = self::canonicalize($item);
        return $value;
    }

    private static function isList(array $value): bool
    {
        if ($value === []) return true;
        return array_keys($value) === range(0, count($value) - 1);
    }
}
