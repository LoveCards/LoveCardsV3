<?php
declare(strict_types=1);

namespace app\shared\Transport\Http;

/**
 * Explain upload failures caused by PHP's request-size directives.
 *
 * PHP can discard the complete multipart body before ThinkPHP is invoked when
 * post_max_size is exceeded. In that case the application sees neither a
 * file nor a field-level upload error, so callers would otherwise receive the
 * misleading "file is required" validation response.
 */
final class UploadLimitDiagnostics
{
    /**
     * @param array<string,mixed>|null $files
     * @param array<string,mixed>|null $server
     * @return array{reason:string,message:string,limit:string,content_length?:int}|null
     */
    public static function detect(
        string $field = 'file',
        ?array $files = null,
        ?array $server = null,
        ?string $postMaxSize = null,
        ?string $uploadMaxFilesize = null
    ): ?array {
        $files ??= $_FILES;
        $server ??= $_SERVER;
        $postMaxSize ??= (string) ini_get('post_max_size');
        $uploadMaxFilesize ??= (string) ini_get('upload_max_filesize');

        $entry = $files[$field] ?? null;
        if (is_array($entry)) {
            $error = (int) ($entry['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
                return [
                    'reason' => 'upload_max_filesize',
                    'message' => 'Release ZIP 超过 PHP upload_max_filesize，请提高 PHP-FPM 上传限制',
                    'limit' => $uploadMaxFilesize,
                ];
            }
        }

        // Once post_max_size is exceeded PHP normally leaves $_FILES empty.
        // Compare Content-Length against the effective limit only when both
        // values are finite and numeric; 0/-1 mean an unlimited directive.
        $contentLength = self::positiveInt($server['CONTENT_LENGTH'] ?? null);
        $postBytes = self::parseBytes($postMaxSize);
        if (!is_array($entry) && $contentLength !== null && $postBytes !== null && $contentLength >= $postBytes) {
            return [
                'reason' => 'post_max_size',
                'message' => 'Release ZIP 请求超过 PHP post_max_size，请提高 PHP-FPM/Nginx 上传限制',
                'limit' => $postMaxSize,
                'content_length' => $contentLength,
            ];
        }

        return null;
    }

    /** @return array{post_max_size:string,upload_max_filesize:string} */
    public static function limits(): array
    {
        return [
            'post_max_size' => (string) ini_get('post_max_size'),
            'upload_max_filesize' => (string) ini_get('upload_max_filesize'),
        ];
    }

    public static function parseBytes(?string $value): ?int
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0' || $value === '-1') return null;
        if (!preg_match('/^(\d+(?:\.\d+)?)\s*([kmgt]?)b?$/i', $value, $matches)) return null;
        $units = ['' => 1, 'k' => 1024, 'm' => 1024 ** 2, 'g' => 1024 ** 3, 't' => 1024 ** 4];
        $unit = strtolower($matches[2]);
        $bytes = (float) $matches[1] * ($units[$unit] ?? 0);
        if ($bytes <= 0 || $bytes > PHP_INT_MAX) return null;
        return (int) $bytes;
    }

    private static function positiveInt(mixed $value): ?int
    {
        if (is_int($value)) return $value > 0 ? $value : null;
        if (!is_string($value) || !preg_match('/^\d+$/', trim($value))) return null;
        $number = (int) trim($value);
        return $number > 0 ? $number : null;
    }
}
