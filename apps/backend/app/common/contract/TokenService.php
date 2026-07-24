<?php

namespace app\common\contract;

interface TokenService
{
    public function sign(array $data): string;

    public function verify(string $token): array;

    public function invalidate(string $token): void;
}
