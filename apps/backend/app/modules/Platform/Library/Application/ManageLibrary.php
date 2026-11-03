<?php

namespace app\modules\Platform\Library\Application;

use app\modules\Platform\Library\Contract\LibraryRepository;
use app\shared\Resource\ResourceRef;
use app\shared\Transport\Http\CursorPage;

final class ManageLibrary
{
    public function __construct(private LibraryRepository $repository)
    {
    }

    public function save(int $userId, ResourceRef $resource): bool
    {
        $this->assertUser($userId);
        return $this->repository->save($userId, $resource);
    }

    public function remove(int $userId, ResourceRef $resource): bool
    {
        $this->assertUser($userId);
        return $this->repository->remove($userId, $resource);
    }

    public function recordView(int $userId, ResourceRef $resource): void
    {
        $this->assertUser($userId);
        $this->repository->recordView($userId, $resource);
    }

    /** @return array{data: array<int, mixed>, next_cursor: ?string, has_more: bool} */
    public function saved(int $userId, ?string $cursor = null, int $limit = 20): array
    {
        $this->assertUser($userId);
        return $this->page($this->repository->listSaved($userId, $cursor, $this->limit($limit)));
    }

    /** @return array{data: array<int, mixed>, next_cursor: ?string, has_more: bool} */
    public function history(int $userId, ?string $cursor = null, int $limit = 20): array
    {
        $this->assertUser($userId);
        return $this->page($this->repository->listHistory($userId, $cursor, $this->limit($limit)));
    }

    public function clearHistory(int $userId): int
    {
        $this->assertUser($userId);
        return $this->repository->clearHistory($userId);
    }

    private function assertUser(int $userId): void
    {
        if ($userId <= 0) throw new \InvalidArgumentException('用户标识无效');
    }

    private function limit(int $limit): int
    {
        return max(1, min(100, $limit));
    }

    /**
     * @param array{items: array<int, array<string, mixed>>, next_cursor: ?string, has_more: bool} $result
     * @return array{data: array<int, mixed>, next_cursor: ?string, has_more: bool}
     */
    private function page(array $result): array
    {
        return CursorPage::make($result['items'], $result['next_cursor'], $result['has_more']);
    }
}
