<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/app/shared/Authorization/Scope.php';
require_once $root . '/app/shared/Authorization/Grant.php';
require_once $root . '/app/shared/Authorization/GrantSet.php';
require_once $root . '/app/modules/Platform/Rbac/Domain/SystemRolePolicy.php';
require_once $root . '/app/modules/Platform/User/Public/UserStatus.php';
require_once $root . '/app/modules/Platform/User/Service/Policy/RootAccountPolicy.php';

use app\modules\Platform\Rbac\Domain\SystemRolePolicy;
use app\modules\Platform\User\Public\UserStatus;
use app\modules\Platform\User\Service\Policy\RootAccountPolicy;
use app\shared\Authorization\Grant;
use app\shared\Authorization\GrantSet;
use app\shared\Authorization\Scope;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$grantSet = static function (array $rows): GrantSet {
    return new GrantSet(array_map(
        static fn (array $row): Grant => new Grant($row[0], $row[1]),
        $rows
    ));
};

$roles = ['root' => 1, 'admin' => 2, 'user' => 3, 'guest' => 4];
$adminGrants = $grantSet([
    ['roles.assign', Scope::GLOBAL],
    ['users.update', Scope::ALL],
    ['users.delete', Scope::ALL],
    ['users.roles.assign', Scope::GLOBAL],
]);
$empty = new GrantSet();
$elevated = $grantSet([['system.recover', Scope::GLOBAL]]);

$assert(SystemRolePolicy::roleMutationViolation(10, [2], $adminGrants, 1, $empty, $empty, $roles) !== null, 'admin must not modify root role');
$assert(SystemRolePolicy::roleMutationViolation(10, [2], $adminGrants, 2, $adminGrants, $adminGrants, $roles) !== null, 'admin must not rewrite its own role');
$assert(SystemRolePolicy::roleMutationViolation(10, [2], $adminGrants, 5, $elevated, $empty, $roles) !== null, 'admin must not strip a role above its grant ceiling');
$assert(SystemRolePolicy::roleMutationViolation(10, [2], $adminGrants, 5, $empty, $elevated, $roles) !== null, 'admin must not assign a grant it does not own');
$assert(SystemRolePolicy::roleMutationViolation(10, [2], $adminGrants, 5, $empty, $adminGrants, $roles) === null, 'admin may assign its own grants to an unrelated lower role');
$assert(SystemRolePolicy::roleMutationViolation(1, [1], $empty, 1, $empty, $elevated, $roles) === null, 'root may administer the root role');

$assert(SystemRolePolicy::userMutationViolation(10, [2], $adminGrants, 20, [1], $empty, null, null, $roles) !== null, 'admin must not update or delete root user');
$assert(SystemRolePolicy::userMutationViolation(10, [2], $adminGrants, 20, [5], $elevated, null, null, $roles) !== null, 'admin must not manage a higher-grant user');
$assert(SystemRolePolicy::userMutationViolation(10, [2], $adminGrants, 20, [5], $elevated, null, null, $roles, false) === null, 'action-specific security policy may ignore unrelated target grants');
$assert(SystemRolePolicy::userMutationViolation(10, [2], $adminGrants, 20, [1], $empty, null, null, $roles, false) !== null, 'action-specific policy must still protect root targets');
$assert(SystemRolePolicy::userMutationViolation(10, [2], $adminGrants, 10, [2], $adminGrants, [2], $adminGrants, $roles) !== null, 'admin must not change its own roles');
$assert(SystemRolePolicy::userMutationViolation(10, [2], $adminGrants, 20, [3], $empty, [1], $empty, $roles) !== null, 'admin must not assign root role');
$assert(SystemRolePolicy::userMutationViolation(10, [2], $adminGrants, 20, [3], $empty, [5], $elevated, $roles) !== null, 'admin must not assign a higher role');
$assert(SystemRolePolicy::userMutationViolation(10, [2], $adminGrants, 20, [3], $empty, [2], $adminGrants, $roles) === null, 'admin may assign a peer role within its own ceiling');
$assert(SystemRolePolicy::userMutationViolation(1, [1], $empty, 20, [1], $empty, [], $empty, $roles) === null, 'root user management proceeds to the separate last-root policy');

$allCards = $grantSet([['cards.update', Scope::ALL]]);
$ownCards = $grantSet([['cards.update', Scope::OWN]]);
$assert(SystemRolePolicy::grantsWithin($allCards, $ownCards), 'all scope must cover own scope');
$assert(!SystemRolePolicy::grantsWithin($ownCards, $allCards), 'own scope must not cover all scope');

$oneRoot = [['id' => 1, 'status' => UserStatus::ACTIVE, 'deleted_at' => null]];
$twoRoots = [
    ['id' => 1, 'status' => UserStatus::ACTIVE, 'deleted_at' => null],
    ['id' => 2, 'status' => UserStatus::HIDDEN, 'deleted_at' => null],
];
$assert(!RootAccountPolicy::preservesAccess($oneRoot, [1 => ['deleted' => true]]), 'last root must not be deleted');
$assert(!RootAccountPolicy::preservesAccess($oneRoot, [1 => ['has_root' => false]]), 'last root role must not be removed');
$assert(!RootAccountPolicy::preservesAccess($oneRoot, [1 => ['status' => UserStatus::BANNED]]), 'last root must not be disabled');
$assert(RootAccountPolicy::preservesAccess($twoRoots, [1 => ['deleted' => true]]), 'one root may be removed when another authenticatable root remains');
$twoRoots[1]['status'] = UserStatus::PENDING;
$assert(!RootAccountPolicy::preservesAccess($twoRoots, [1 => ['deleted' => true]]), 'pending root must not satisfy final-root protection');
$assert(RootAccountPolicy::nextBatchStatus('approve', UserStatus::ACTIVE) === UserStatus::PENDING, 'approve toggle must model the persisted transition');
$assert(RootAccountPolicy::nextBatchStatus('ban', UserStatus::BANNED) === UserStatus::ACTIVE, 'ban toggle must model unbanning');
$assert(RootAccountPolicy::nextBatchStatus('hide', UserStatus::ACTIVE) === UserStatus::HIDDEN, 'hidden root remains authenticatable');

$rolesController = file_get_contents($root . '/app/api/controller/Rbac/Roles.php');
$rolesService = file_get_contents($root . '/app/modules/Platform/Rbac/Service/Roles.php');
$usersController = file_get_contents($root . '/app/api/controller/User/Users.php');
$administrationController = file_get_contents($root . '/app/api/controller/Platform/Administration.php');
$usersService = file_get_contents($root . '/app/modules/Platform/User/Service/Users.php');
$assert(str_contains($rolesController, '$auth->uid(), $auth->roleIds(), $auth->grants()'), 'role writes must pass actor identity, roles and grants to the service');
$assert(str_contains($rolesController, 'RolesService::batchOperate(') && str_contains($rolesController, 'RolesService::reseed('), 'role batch and reseed must use actor-aware service entry points');
$assert(substr_count($rolesService, 'self::assertRoleMutationAllowed(') >= 4, 'role batch, update, delete and grant assignment must share the vertical policy');
$assert(str_contains($rolesService, 'SystemRolePolicy::isRootActor(') && str_contains($rolesService, "->allows('roles.assign', Scope::GLOBAL)"), 'role reseed must require both root identity and roles.assign grant');
$assert(str_contains($usersController, '$auth->uid(), $auth->roleIds(), $auth->grants()'), 'user writes must pass actor identity, roles and grants to the service');
$assert(str_contains($administrationController, '$auth->roleIds()') && str_contains($administrationController, '$auth->grants()'), 'session revocation must pass actor roles and grants to the application');
$assert(str_contains($administrationController, 'AdminSecurity::class)->overview('), 'security overview must compute action availability from the authenticated actor');
$assert(str_contains($usersService, 'RootAccountPolicy::preservesAccess') && str_contains($usersService, '->lock(true)'), 'last-root protection must run against locked persistence rows');

fwrite(STDOUT, "PASS privilege boundary baseline\n");
