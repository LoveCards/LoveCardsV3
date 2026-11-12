<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/shared/Transport/Http/Resource.php';
require_once __DIR__ . '/../../app/modules/Content/Likes/Transport/Api/LikeResource.php';

use app\modules\Content\Likes\Transport\Api\LikeResource;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$old = LikeResource::make([
    'id' => 7, 'aid' => 99, 'pid' => 999, 'ref_type' => 'card', 'ref_id' => 42,
    'uid' => 5, 'created_at' => '2026-11-13 00:00:00', 'ip' => '127.0.0.1',
]);
$assert($old['aid'] === 1 && $old['pid'] === 42, 'legacy response fields must derive from the canonical reference');
$assert(!array_key_exists('ip', $old), 'private IP must not be exposed');

$afterExpand = LikeResource::make([
    'id' => 8, 'aid' => null, 'pid' => null, 'ref_type' => 'comment', 'ref_id' => 84,
    'uid' => 5, 'created_at' => '2026-11-13 00:01:00',
]);
$assert($afterExpand['aid'] === 1 && $afterExpand['pid'] === 84, 'compatibility release must read rows written without legacy values');

$afterContract = LikeResource::make([
    'id' => 9, 'ref_type' => 'comment', 'ref_id' => 85,
    'uid' => 5, 'created_at' => '2026-11-13 00:02:00',
]);
$assert($afterContract['aid'] === 1 && $afterContract['pid'] === 85, 'legacy API response must survive column removal');

fwrite(STDOUT, "Like resource compatibility passed.\n");
