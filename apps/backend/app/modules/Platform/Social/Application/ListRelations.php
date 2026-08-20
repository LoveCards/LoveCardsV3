<?php

namespace app\modules\Platform\Social\Application;

use app\modules\Platform\Social\Contract\SocialGraphRepository;
use app\shared\Transport\Http\CursorPage;

final class ListRelations
{
    public function __construct(private SocialGraphRepository $repository)
    {
    }

    /** @return array{data: array<int, mixed>, next_cursor: ?string, has_more: bool} */
    public function followers(int $userId, ?string $cursor = null, int $limit = 20): array
    {
        return $this->page($this->repository->listFollowers($userId, $cursor, $this->limit($limit)));
    }

    /** @return array{data: array<int, mixed>, next_cursor: ?string, has_more: bool} */
    public function following(int $userId, ?string $cursor = null, int $limit = 20): array
    {
        return $this->page($this->repository->listFollowing($userId, $cursor, $this->limit($limit)));
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
