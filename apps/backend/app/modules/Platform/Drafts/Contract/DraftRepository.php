<?php

declare(strict_types=1);

namespace app\modules\Platform\Drafts\Contract;

interface DraftRepository
{
    public function save(int $userId, ?int $id, string $title, string $content, array $payload = []): int;
    public function find(int $userId, int $id): ?array;

    /** @return array{items: array, next_cursor: ?string, has_more: bool} */
    public function page(int $userId, ?string $cursor, int $limit): array;

    public function delete(int $userId, int $id): bool;
    /** @return array{total: int, active: int} */
    public function totals(): array;
}
