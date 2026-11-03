<?php

namespace app\modules\Platform\Library\Infrastructure;

use app\modules\Platform\Library\Contract\LibraryRepository;
use app\shared\Resource\ResourceRef;
use think\facade\Db;
use app\modules\Content\Cards\Public\CardAccess;

final class ThinkOrmLibraryRepository implements LibraryRepository
{
    public function save(int $userId, ResourceRef $resource): bool
    {
        $query = Db::table('library_items')->where([
            'user_id' => $userId,
            'resource_type' => $resource->type(),
            'resource_id' => $resource->id(),
        ]);
        if ($query->value('id') !== null) return false;

        try {
            return Db::table('library_items')->insert([
                'user_id' => $userId,
                'resource_type' => $resource->type(),
                'resource_id' => $resource->id(),
                'created_at' => date('Y-m-d H:i:s'),
            ]) === 1;
        } catch (\Throwable $exception) {
            if ($query->value('id') !== null) return false;
            throw $exception;
        }
    }

    public function remove(int $userId, ResourceRef $resource): bool
    {
        return Db::table('library_items')->where([
            'user_id' => $userId,
            'resource_type' => $resource->type(),
            'resource_id' => $resource->id(),
        ])->delete() > 0;
    }

    public function recordView(int $userId, ResourceRef $resource): void
    {
        $where = [
            'user_id' => $userId,
            'resource_type' => $resource->type(),
            'resource_id' => $resource->id(),
        ];
        $now = date('Y-m-d H:i:s');
        $id = Db::table('view_history')->where($where)->value('id');
        if ($id !== null) {
            Db::table('view_history')->where('id', $id)->inc('view_count')->update([
                'last_viewed_at' => $now,
                'viewed_at' => $now,
            ]);
            return;
        }

        try {
            Db::table('view_history')->insert($where + [
                'view_count' => 1,
                'last_viewed_at' => $now,
                'viewed_at' => $now,
                'created_at' => $now,
            ]);
        } catch (\Throwable $exception) {
            $id = Db::table('view_history')->where($where)->value('id');
            if ($id === null) throw $exception;
            Db::table('view_history')->where('id', $id)->inc('view_count')->update([
                'last_viewed_at' => $now,
                'viewed_at' => $now,
            ]);
        }
    }

    public function listSaved(int $userId, ?string $cursor, int $limit): array
    {
        return $this->listByCursor('library_items', 'created_at', $userId, $cursor, $limit);
    }

    public function listHistory(int $userId, ?string $cursor, int $limit): array
    {
        return $this->listByCursor('view_history', 'viewed_at', $userId, $cursor, $limit);
    }

    public function clearHistory(int $userId): int
    {
        return Db::table('view_history')->where('user_id', $userId)->delete();
    }

    /** @return array{items: array<int, array<string, mixed>>, next_cursor: ?string, has_more: bool} */
    private function listByCursor(string $table, string $timeColumn, int $userId, ?string $cursor, int $limit): array
    {
        $scanCursor = ($cursor !== null && ctype_digit($cursor)) ? (int) $cursor : null;
        $rows = [];
        $hasMore = false;
        $batchSize = max($limit + 1, 50);
        while (count($rows) <= $limit) {
            $query = Db::table($table)
                ->where('user_id', $userId)
                ->field("id,resource_type,resource_id,{$timeColumn}")
                ->order('id', 'desc')
                ->limit($batchSize);
            if ($scanCursor !== null) $query->where('id', '<', $scanCursor);
            $batch = $query->select()->toArray();
            if ($batch === []) break;
            $scanCursor = (int) end($batch)['id'];
            $cardIds = array_values(array_unique(array_map('intval', array_filter(array_map(
                static fn(array $row): int => ($row['resource_type'] ?? '') === 'card' ? (int) ($row['resource_id'] ?? 0) : 0,
                $batch
            )))));
            $readable = array_fill_keys(CardAccess::readableIds($cardIds, $userId), true);
            foreach ($batch as $row) {
                if (($row['resource_type'] ?? '') === 'card') {
                    if (!isset($readable[(int) ($row['resource_id'] ?? 0)])) continue;
                }
                $rows[] = $row;
                if (count($rows) > $limit) {
                    $hasMore = true;
                    break 2;
                }
            }
            if (count($batch) < $batchSize) break;
        }
        if ($hasMore) array_pop($rows);
        $next = $hasMore && $rows !== [] ? (string) end($rows)['id'] : null;

        return ['items' => $rows, 'next_cursor' => $next, 'has_more' => $hasMore];
    }
}
