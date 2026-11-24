<?php

declare(strict_types=1);

namespace app\modules\Platform\Drafts\Application;

use app\modules\Platform\Drafts\Contract\DraftRepository;

final class ManageDrafts
{
    public function __construct(private DraftRepository $repository) {}

    public function save(int $userId, ?int $id, string $title, string $content, array $payload = []): int
    {
        return $this->repository->save($userId, $id, $title, $content, $payload);
    }

    public function find(int $userId, int $id): ?array
    {
        return $this->repository->find($userId, $id);
    }

    public function page(int $userId, ?string $cursor, int $limit): array
    {
        return $this->repository->page($userId, $cursor, $limit);
    }

    public function delete(int $userId, int $id): bool
    {
        return $this->repository->delete($userId, $id);
    }
}
