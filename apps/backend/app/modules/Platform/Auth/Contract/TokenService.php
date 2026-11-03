<?php

namespace app\modules\Platform\Auth\Contract;

interface TokenService
{
    public function sign(array $data): string;
    public function verify(string $token): array;
    public function invalidate(string $token): void;
}
