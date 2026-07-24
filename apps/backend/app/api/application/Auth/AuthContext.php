<?php

namespace app\api\application\Auth;

final class AuthContext
{
    private $uid;
    private $user;
    private $roleIds;
    private $capabilities;
    private $renewedToken;
    private $visitor;

    private function __construct(
        int $uid,
        $user,
        array $roleIds,
        array $capabilities,
        ?string $renewedToken,
        bool $visitor
    ) {
        $this->uid = $uid;
        $this->user = $user;
        $this->roleIds = $roleIds;
        $this->capabilities = $capabilities;
        $this->renewedToken = $renewedToken;
        $this->visitor = $visitor;
    }

    public static function authenticated(
        int $uid,
        $user,
        array $roleIds,
        array $capabilities,
        ?string $renewedToken = null
    ): self {
        return new self($uid, $user, $roleIds, $capabilities, $renewedToken, false);
    }

    public static function visitor(array $roleIds, array $capabilities): self
    {
        return new self(0, null, $roleIds, $capabilities, null, true);
    }

    public function uid(): int
    {
        return $this->uid;
    }

    public function user()
    {
        return $this->user;
    }

    public function roleIds(): array
    {
        return $this->roleIds;
    }

    public function capabilities(): array
    {
        return $this->capabilities;
    }

    public function renewedToken(): ?string
    {
        return $this->renewedToken;
    }

    public function isVisitor(): bool
    {
        return $this->visitor;
    }

    public function hasAnyCapability(array $required): bool
    {
        return count(array_intersect($required, $this->capabilities)) > 0;
    }
}
