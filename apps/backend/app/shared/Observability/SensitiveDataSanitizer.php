<?php
declare(strict_types=1);

namespace app\shared\Observability;

final class SensitiveDataSanitizer
{
    public const REDACTED = '[REDACTED]';

    private int $maxDepth;
    private int $maxStringLength;

    public function __construct(int $maxDepth = 8, int $maxStringLength = 4096)
    {
        if ($maxDepth < 1 || $maxStringLength < 128) {
            throw new \InvalidArgumentException('日志脱敏器限制不合法');
        }
        $this->maxDepth = $maxDepth;
        $this->maxStringLength = $maxStringLength;
    }

    /** @return array<string|int, mixed> */
    public function sanitizeContext(array $context): array
    {
        /** @var array<string|int, mixed> $sanitized */
        $sanitized = $this->sanitizeValue($context, null, 0);
        return $sanitized;
    }

    public function sanitizeText(string $text): string
    {
        if (preg_match('//u', $text) !== 1) return '[BINARY DATA]';
        $text = (string) preg_replace('/\bBearer\s+[A-Za-z0-9._~+\/=:-]+/i', 'Bearer ' . self::REDACTED, $text);
        $text = (string) preg_replace(
            '/((?:password|passwd|secret|token|grant|authorization|api[_-]?key|access[_-]?key|private[_-]?key)\s*["\']?\s*[:=]\s*["\']?)[^\s,"\']+/i',
            '$1' . self::REDACTED,
            $text
        );
        if (strlen($text) > $this->maxStringLength) {
            $text = substr($text, 0, $this->maxStringLength) . '[TRUNCATED]';
        }
        return $text;
    }

    private function sanitizeValue($value, ?string $key, int $depth)
    {
        if ($key !== null && $this->isSensitiveKey($key)) return self::REDACTED;
        if ($depth >= $this->maxDepth) return '[MAX DEPTH]';
        if (is_string($value)) return $this->sanitizeText($value);
        if ($value === null || is_int($value) || is_float($value) || is_bool($value)) return $value;
        if (is_array($value)) {
            $result = [];
            foreach ($value as $childKey => $childValue) {
                $result[$childKey] = $this->sanitizeValue($childValue, is_string($childKey) ? $childKey : null, $depth + 1);
            }
            return $result;
        }
        if ($value instanceof \Throwable) {
            return [
                'class' => get_class($value),
                'message' => $this->sanitizeText($value->getMessage()),
                'code' => $value->getCode(),
            ];
        }
        if ($value instanceof \Stringable) return $this->sanitizeText((string) $value);
        if (is_object($value)) return ['class' => get_class($value)];
        if (is_resource($value)) return '[RESOURCE]';
        return '[UNSUPPORTED]';
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $key));
        return $normalized === 'grant' || preg_match('/(?:password|passwd|pwd|secret|token|authorization|cookie|privatekey|apikey|accesskey|recoverycode|verificationcode|otp)/', $normalized) === 1;
    }
}
