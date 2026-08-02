<?php

namespace app\modules\Platform\Auth\Infrastructure;

use app\modules\Platform\Auth\Contract\TokenService;

final class JwtTokenService implements TokenService
{
    public function sign(array $data): string { return Jwt::sign($data); }
    public function verify(string $token): array { return Jwt::verify($token); }
    public function invalidate(string $token): void { Jwt::invalidate($token); }
}
