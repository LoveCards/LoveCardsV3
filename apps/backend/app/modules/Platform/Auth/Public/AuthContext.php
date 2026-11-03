<?php

namespace app\modules\Platform\Auth\Public;

use app\shared\Authorization\GrantSet;

final class AuthContext
{
    private $uid;
    private $user;
    private $roleIds;
    private $capabilities;
    private $renewedToken;
    private $visitor;
    private ?string $sessionPublicId;
    private GrantSet $grants;

    private function __construct(
        int $uid,
        $user,
        array $roleIds,
        GrantSet $grants,
        ?string $renewedToken,
        bool $visitor,
        ?string $sessionPublicId
    ) {
        $this->uid = $uid;
        $this->user = $user;
        $this->roleIds = $roleIds;
        $this->grants = $grants;
        $this->capabilities = $grants->capabilityNames();
        $this->renewedToken = $renewedToken;
        $this->visitor = $visitor;
        $this->sessionPublicId = $sessionPublicId;
    }

    public static function authenticated(
        int $uid,
        $user,
        array $roleIds,
        GrantSet $grants,
        ?string $renewedToken = null,
        ?string $sessionPublicId = null
    ): self {
        return new self($uid, $user, $roleIds, $grants, $renewedToken, false, $sessionPublicId);
    }

    public static function visitor(array $roleIds, GrantSet $grants): self
    {
        return new self(0, null, $roleIds, $grants, null, true, null);
    }

    public function uid(): int
    {
        return $this->uid;
    }

    /** Internal database identity. Public API identities use users.uid. */
    public function userId(): int
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

    public function grants(): GrantSet
    {
        return $this->grants;
    }

    public function renewedToken(): ?string
    {
        return $this->renewedToken;
    }

    public function sessionPublicId(): ?string { return $this->sessionPublicId; }

    public function isVisitor(): bool
    {
        return $this->visitor;
    }

    public function hasAnyCapability(array $required): bool
    {
        return count(array_intersect($required, $this->capabilities)) > 0;
    }
}
