<?php

namespace app\modules\Platform\Library\Contract;

use app\shared\Resource\ResourceRef;

interface LibraryRepository
{
    public function save(int $userId, ResourceRef $resource): bool;
    public function remove(int $userId, ResourceRef $resource): bool;
    public function recordView(int $userId, ResourceRef $resource): void;

    /** @return array{items: array<int, array<string, mixed>>, next_cursor: ?string, has_more: bool} */
    public function listSaved(int $userId, ?string $cursor, int $limit): array;

    /** @return array{items: array<int, array<string, mixed>>, next_cursor: ?string, has_more: bool} */
    public function listHistory(int $userId, ?string $cursor, int $limit): array;

    public function clearHistory(int $userId): int;
}
