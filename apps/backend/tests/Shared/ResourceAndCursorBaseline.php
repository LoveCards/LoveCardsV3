<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/app/shared/Resource/ResourceRef.php';
require $root . '/app/shared/Transport/Http/Resource.php';
require $root . '/app/shared/Transport/Http/CursorPage.php';
require $root . '/app/shared/Transport/Http/CursorPageResource.php';

use app\shared\Resource\ResourceRef;
use app\shared\Transport\Http\CursorPage;
use app\shared\Transport\Http\CursorPageResource;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$ref = new ResourceRef('comment', '01J_TEST-1');
$assert($ref->key() === 'comment:01J_TEST-1', 'resource key should be stable');
$assert($ref->toArray() === ['type' => 'comment', 'id' => '01J_TEST-1'], 'resource shape should be stable');

$invalid = false;
try {
    new ResourceRef('../comment', '1');
} catch (InvalidArgumentException) {
    $invalid = true;
}
$assert($invalid, 'invalid resource type must be rejected');

$page = CursorPage::make([['id' => 3], ['id' => 2]], '2', true);
$assert($page['next_cursor'] === '2', 'next cursor should be preserved');
$assert($page['has_more'] === true, 'has_more should be preserved');
$assert(count($page['data']) === 2, 'cursor data should be normalized');

$transport = CursorPageResource::make($page);
$assert($transport === $page, 'cursor response resource must preserve page metadata');
$assert(CursorPageResource::schema()['required'] === ['data', 'next_cursor', 'has_more'], 'cursor response schema must be explicit');

$routeConfig = file_get_contents($root . '/config/route.php');
$assert(str_contains($routeConfig, "'route_complete_match'  => true"), 'API routes must match completely so stream/deep paths are not shadowed');

fwrite(STDOUT, "PASS shared resource and cursor baseline\n");
