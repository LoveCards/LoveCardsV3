<?php

namespace app\common\infra;

use app\common\contract\TokenService;

class JwtTokenService implements TokenService
{
    public function sign(array $data): string
    {
        return Jwt::sign($data);
    }

    public function verify(string $token): array
    {
        return Jwt::verify($token);
    }

    public function invalidate(string $token): void
    {
        Jwt::invalidate($token);
    }
}
