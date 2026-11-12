<?php

require_once __DIR__ . '/../../app/modules/Platform/Rbac/Domain/CapabilityCatalog.php';
require_once __DIR__ . '/../../app/modules/Platform/Rbac/Domain/SystemRolePolicy.php';

use app\modules\Platform\Rbac\Domain\CapabilityCatalog;
use app\modules\Platform\Rbac\Domain\SystemRolePolicy;

$roles = SystemRolePolicy::capabilityMatrix([
    'root' => 1,
    'admin' => 2,
    'user' => 3,
    'guest' => 4,
]);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$assert(isset(CapabilityCatalog::all()['comments.moderate']), 'comments.moderate must be catalogued');
$assert(isset(CapabilityCatalog::all()['comments.update']), 'comments.update must be catalogued for managed scope checks');
$assert(isset(CapabilityCatalog::all()['comments.update.all']), 'managed comments.update.all must be catalogued');
$assert(isset(CapabilityCatalog::all()['comments.pin.all']), 'card-scoped comments.pin.all must be catalogued');
$assert(CapabilityCatalog::descriptionFor('cards.update', 'own') === '编辑自己的卡片', 'own permission description must describe own resources');
$assert(CapabilityCatalog::descriptionFor('cards.update', 'all') === '编辑任意卡片', 'all permission description must describe all resources');
$assert(in_array('cards.update', $roles[3], true), 'user must edit own cards');
$assert(in_array('cards.delete', $roles[3], true), 'user must delete own cards');
$assert(in_array('comments.delete', $roles[3], true), 'user must delete own comments');
$assert(!in_array('comments.update', $roles[3], true), 'user must not edit comment bodies');
$assert(in_array('comments.moderate.all', $roles[2], true), 'admin must moderate comments');
$assert(in_array('comments.update.all', $roles[2], true), 'admin default matrix must edit comment bodies');
$assert(in_array('comments.pin.all', $roles[2], true), 'admin default matrix must pin card comments');

$route = file_get_contents(__DIR__ . '/../../app/api/route/comments.php');
$assert(!str_contains($route, "name('comments.update')"), 'public comment update route must not exist');
$assert(str_contains($route, "name('comments.moderateManaged')"), 'admin moderation route must exist');
$assert(str_contains($route, "\$grant('comments.update', Scope::ALL)"), 'managed comment route must require update.all for body edits');
$assert(str_contains($route, "name('comments.pinManaged')"), 'managed card comment pin route must exist');
$assert(str_contains($route, "name('comments.unpinManaged')"), 'managed card comment unpin route must exist');
$assert(str_contains($route, "\$grant('comments.pin', Scope::ALL)"), 'pin routes must require comments.pin.all');

$cardService = file_get_contents(__DIR__ . '/../../app/modules/Content/Cards/Service/Cards.php');
$cardValidator = file_get_contents(__DIR__ . '/../../app/modules/Content/Cards/Transport/Api/Cards.php');
$commentValidator = file_get_contents(__DIR__ . '/../../app/modules/Content/Comments/Transport/Api/Comments.php');
$commentService = file_get_contents(__DIR__ . '/../../app/modules/Content/Comments/Service/Comments.php');
$commentPinMigration = file_get_contents(__DIR__ . '/../../database/migrations/20260824000002.sql');
$userValidator = file_get_contents(__DIR__ . '/../../app/modules/Platform/User/Transport/Api/Users.php');
$tagService = file_get_contents(__DIR__ . '/../../app/modules/Content/Tags/Service/Tags.php');
$tagValidator = file_get_contents(__DIR__ . '/../../app/modules/Content/Tags/Transport/Api/Tags.php');
$targetSchema = file_get_contents(__DIR__ . '/../../database/schema.sql');
$targetSeed = file_get_contents(__DIR__ . '/../../database/seed.sql');
$userModel = file_get_contents(__DIR__ . '/../../app/modules/Platform/User/Infrastructure/Persistence/Model/Users.php');
$assert(!str_contains($cardValidator, "'allUpdate'"), 'legacy card allUpdate scene must not re-enable derived-field writes');
$assert(!str_contains($commentValidator, "'allUpdate'"), 'legacy comment allUpdate scene must not re-enable body edits');
$assert(str_contains($commentValidator, "'normal' => ['id', 'content', 'status']"), 'managed comment mutation must accept content and moderation fields only');
$assert(!str_contains($commentValidator, "'is_top' => 'number|in:0,1'"), 'legacy per-comment pin flag must not remain writable');
$assert(str_contains($commentPinMigration, 'PRIMARY KEY (`card_id`)'), 'one card must have at most one pinned comment');
$assert(str_contains($commentPinMigration, 'UNIQUE KEY `uq_card_comment_pins_comment` (`comment_id`)'), 'one comment must not be pinned into multiple cards');
$assert(str_contains($commentService, "->where('parent_id', 0)") && str_contains($commentService, "->where('status', 0)"), 'pin command must accept only visible top-level comments');
$assert(str_contains($commentService, "\$card['pinned_comment_id']"), 'card comment list must resolve the card pointer');
$assert(!str_contains($commentService, "Db::table('card_comment_pins')"), 'runtime must not query the retired pin table');
$assert(!str_contains($targetSchema, 'CREATE TABLE `card_comment_pins`'), 'new installs must not restore the retired pin table');
$assert(preg_match('/CREATE TABLE `comments` \((.*?)\) ENGINE/s', $targetSchema, $commentTable) === 1
    && !str_contains($commentTable[1], '`is_top`'), 'new installs must not restore the retired comment pin column');
$assert(preg_match('/CREATE TABLE `cards` \((.*?)\) ENGINE/s', $targetSchema, $cardTable) === 1
    && str_contains($cardTable[1], '`is_top`') && str_contains($cardTable[1], '`pinned_comment_id`'),
    'card pinning and comment pinning must retain their separate current fields');
$assert(str_contains($commentService, "->orderRaw('(`id` = ' . \$pinnedId . ') DESC')"), 'card comment list must place its resolved pin first');
$assert(str_contains($commentService, "self::clearPinsForComments(\$ids)"), 'comment lifecycle mutations must clear invalid pins');
$assert(!str_contains($userValidator, "'allUpdate'"), 'legacy user allUpdate scene must not mix profile and role writes');
$assert(preg_match('/CREATE TABLE `users` \((.*?)\) ENGINE/s', $targetSchema, $usersTable) === 1
    && !str_contains($usersTable[1], '`roles_id`'), 'new installs must not restore the legacy role JSON column');
$assert(!str_contains($targetSeed, '`roles_id`'), 'new install seed must insert root roles through user_roles only');
$assert(!str_contains($userModel, "'roles_id' => 'json'"), 'runtime model must not require the removed role column');
$assert(str_contains($userValidator, "'roles_id' => ['type' => 'array'"), 'public role assignment contract must remain available');
$assert(!str_contains($tagValidator, "'allUpdate'"), 'legacy tag allUpdate scene must not re-enable ownership writes');
$assert(
    str_contains($tagValidator, "'normal' => ['name', 'status']"),
    'tag admin update must only accept mutable presentation and moderation fields'
);
$assert(
    str_contains($tagService, "unset(\$data['aid'], \$data['user_id'])"),
    'tag update must discard application and ownership fields at the service boundary'
);
$assert(
    str_contains($cardService, "CardPolicy::assertCan(\$grants, 'pin'"),
    'card update must independently authorize is_top changes'
);
$assert(
    str_contains($cardService, "CardPolicy::assertCan(\$grants, 'approve'"),
    'card update must independently authorize status changes'
);
$assert(
    str_contains($cardService, "unset(\$data['user_id'], \$data['comments'], \$data['views'], \$data['good'], \$data['goods'])"),
    'card update must discard ownership and derived counters'
);

fwrite(STDOUT, "Permission boundary baseline passed.\n");
