<?php

namespace app\modules\Platform\Auth\Domain;

final class SessionId
{
    private const MIN_LENGTH = 32;
    private const MAX_LENGTH = 128;

    private function __construct(private string $raw)
    {
    }

    public static function generate(): self
    {
        return new self(rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));
    }

    public static function fromRaw(string $raw): self
    {
        $length = strlen($raw);
        if (
            $length < self::MIN_LENGTH
            || $length > self::MAX_LENGTH
            || preg_match('/^[A-Za-z0-9_-]+$/D', $raw) !== 1
        ) {
            throw new \InvalidArgumentException('JTI 格式无效');
        }

        return new self($raw);
    }

    public function raw(): string
    {
        return $this->raw;
    }

    public function hash(): string
    {
        return hash('sha256', $this->raw);
    }
}
