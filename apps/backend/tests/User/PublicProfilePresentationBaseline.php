<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/app/shared/Transport/Http/Resource.php';
require $root . '/app/modules/Platform/User/Transport/Api/PublicUserProfileResource.php';

use app\modules\Platform\User\Transport\Api\PublicUserProfileResource;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$profile = PublicUserProfileResource::make([
    'uid' => '123456789012',
    'username' => 'profile_user',
    'avatar' => null,
    'cover_url' => '/media/0123456789abcdef',
    'followers' => 4,
    'following' => 3,
    'can_view_relations' => true,
    'stats' => ['received_likes' => 12],
]);
$assert($profile['cover_url'] === '/media/0123456789abcdef', 'public profile must expose the resolved cover url');
$assert($profile['stats'] === ['received_likes' => 12], 'public profile must expose received likes inside stats');

$empty = PublicUserProfileResource::make(['uid' => '123456789012', 'username' => 'empty_user']);
$assert($empty['cover_url'] === null, 'missing profile cover must return null');
$assert($empty['stats']['received_likes'] === 0, 'missing received likes must return zero');

$schema = PublicUserProfileResource::schema();
$assert(in_array('cover_url', $schema['required'], true), 'cover_url must be a stable required nullable field');
$assert(in_array('stats', $schema['required'], true), 'stats must be a stable required object');
$assert(($schema['properties']['stats']['properties']['received_likes']['type'] ?? null) === 'integer', 'received_likes must be an integer');

$profileInput = file_get_contents($root . '/app/modules/Platform/User/Transport/Api/Profile.php');
$profileCover = file_get_contents($root . '/app/modules/Platform/User/Public/ProfileCover.php');
$fileInput = file_get_contents($root . '/app/modules/Platform/Files/Transport/Api/Files.php');
$assert(str_contains($profileInput, "'cover_file_id'"), 'profile update must accept cover_file_id');
$assert(str_contains($fileInput, 'profile_cover'), 'file upload must expose the profile_cover scene');
$assert(str_contains($profileCover, "Files::replaceReferences"), 'profile cover must use the canonical file reference lifecycle');
$assert(!str_contains($profileInput, "'cover_url' =>"), 'profile update must not accept arbitrary cover URLs');

fwrite(STDOUT, "PASS public profile presentation baseline\n");
