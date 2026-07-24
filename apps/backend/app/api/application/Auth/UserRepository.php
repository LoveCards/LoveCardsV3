<?php

namespace app\api\application\Auth;

interface UserRepository
{
    public function findById(int $id): ?AuthUser;

    public function findByAccount(string $account): ?AuthUser;

    public function contactExists(string $email, string $phone): bool;

    public function create(
        string $number,
        string $username,
        string $email,
        string $phone,
        string $passwordHash,
        array $roleIds,
        int $status
    ): AuthUser;
}
