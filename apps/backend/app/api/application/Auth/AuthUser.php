<?php

namespace app\api\application\Auth;

final class AuthUser
{
    private $id;
    private $status;
    private $passwordHash;
    private $roleIds;

    public function __construct(int $id, int $status, string $passwordHash, array $roleIds)
    {
        $this->id = $id;
        $this->status = $status;
        $this->passwordHash = $passwordHash;
        $this->roleIds = $roleIds;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }

    public function roleIds(): array
    {
        return $this->roleIds;
    }
}
