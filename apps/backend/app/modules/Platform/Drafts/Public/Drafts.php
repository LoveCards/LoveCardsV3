<?php

declare(strict_types=1);

namespace app\modules\Platform\Drafts\Public;

use app\modules\Platform\Drafts\Application\ManageDrafts;
use app\modules\Platform\Drafts\Contract\DraftRepository;

final class Drafts
{
    public static function save(int $userId, ?int $id, string $title, string $content, array $payload = []): int
    {
        return app(ManageDrafts::class)->save($userId, $id, $title, $content, $payload);
    }

    public static function find(int $userId, int $id): ?array
    {
        return app(ManageDrafts::class)->find($userId, $id);
    }

    public static function page(int $userId, ?string $cursor, int $limit): array
    {
        return app(ManageDrafts::class)->page($userId, $cursor, $limit);
    }

    public static function delete(int $userId, int $id): bool
    {
        return app(ManageDrafts::class)->delete($userId, $id);
    }

    public static function totals(): array
    {
        return app(DraftRepository::class)->totals();
    }
}
