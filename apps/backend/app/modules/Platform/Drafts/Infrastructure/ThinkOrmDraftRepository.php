<?php

declare(strict_types=1);

namespace app\modules\Platform\Drafts\Infrastructure;

use app\modules\Platform\Drafts\Contract\DraftRepository;
use think\facade\Db;

final class ThinkOrmDraftRepository implements DraftRepository
{
    public function save(int $userId, ?int $id, string $title, string $content, array $payload = []): int
    {
        $now = date('Y-m-d H:i:s');
        $data = [
            'user_id' => $userId,
            'title' => mb_substr(trim($title), 0, 255),
            'content' => $content,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'updated_at' => $now,
        ];

        if ($id) {
            $affected = Db::table('card_drafts')->where('id', $id)->where('user_id', $userId)
                ->whereNull('deleted_at')->update($data);
            if ($affected !== 1) {
                throw new \RuntimeException('草稿不存在');
            }
            return $id;
        }

        return (int) Db::table('card_drafts')->insertGetId($data + ['created_at' => $now]);
    }

    public function find(int $userId, int $id): ?array
    {
        return Db::table('card_drafts')->where('id', $id)->where('user_id', $userId)
            ->whereNull('deleted_at')->find() ?: null;
    }

    public function page(int $userId, ?string $cursor, int $limit): array
    {
        $limit = min(100, max(1, $limit));
        $query = Db::table('card_drafts')->where('user_id', $userId)->whereNull('deleted_at')
            ->order('updated_at', 'desc')->order('id', 'desc')->limit($limit + 1);

        if ($cursor !== null) {
            $anchor = $this->cursorAnchor($userId, $cursor);
            if ($anchor === null) {
                return ['items' => [], 'next_cursor' => null, 'has_more' => false];
            }
            [$updatedAt, $id] = $anchor;
            $query->where(function ($query) use ($updatedAt, $id): void {
                $query->where('updated_at', '<', $updatedAt)
                    ->whereOr(function ($sameTime) use ($updatedAt, $id): void {
                        $sameTime->where('updated_at', $updatedAt)->where('id', '<', $id);
                    });
            });
        }

        $items = $query->select()->toArray();
        $hasMore = count($items) > $limit;
        if ($hasMore) {
            array_pop($items);
        }
        $last = $hasMore ? end($items) : null;
        return [
            'items' => $items,
            'next_cursor' => $last ? $last['updated_at'] . '|' . $last['id'] : null,
            'has_more' => $hasMore,
        ];
    }

    private function cursorAnchor(int $userId, string $cursor): ?array
    {
        if (ctype_digit($cursor)) {
            $timestamp = Db::table('card_drafts')->where('user_id', $userId)
                ->where('id', (int) $cursor)->value('updated_at');
            return $timestamp ? [(string) $timestamp, (int) $cursor] : null;
        }

        if (!preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\|([1-9]\d*)$/D', $cursor, $match)) {
            return null;
        }
        $timestamp = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $match[1]);
        $id = filter_var($match[2], FILTER_VALIDATE_INT);
        if (!$timestamp || $timestamp->format('Y-m-d H:i:s') !== $match[1] || $id === false) {
            return null;
        }
        return [$match[1], $id];
    }

    public function delete(int $userId, int $id): bool
    {
        return Db::table('card_drafts')->where('id', $id)->where('user_id', $userId)
            ->whereNull('deleted_at')->update(['deleted_at' => date('Y-m-d H:i:s')]) === 1;
    }

    public function totals(): array
    {
        $row = Db::table('card_drafts')->field('COUNT(*) AS total, SUM(deleted_at IS NULL) AS active')->find();
        return ['total' => (int) ($row['total'] ?? 0), 'active' => (int) ($row['active'] ?? 0)];
    }
}
