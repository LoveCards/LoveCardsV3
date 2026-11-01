<?php

namespace app\modules\Platform\Notifications\Infrastructure;

use app\modules\Platform\Notifications\Contract\NotificationRepository;
use app\modules\Platform\Notifications\Contract\NotificationTypeCatalog;
use app\modules\Platform\Notifications\Domain\NotificationIntent;
use think\facade\Db;
use app\modules\Content\Cards\Public\CardAccess as CardVisibilityPolicy;
use app\modules\Platform\User\Public\UserStatus;

final class ThinkOrmNotificationRepository implements NotificationRepository
{
    public function recipientCanReceive(int $userId, string $type): bool
    {
        $status = Db::table('users')
            ->where('id', $userId)
            ->whereNull('deleted_at')
            ->value('status');
        if ($status === null || !UserStatus::isKnown((int) $status)) return false;

        if (str_starts_with($type, 'system.')) return true;
        if (!UserStatus::canAuthenticate((int) $status)) return false;
        $enabled = Db::table('notification_preferences')
            ->where(['user_id' => $userId, 'notification_type' => $type])
            ->value('enabled');
        return $enabled === null || (bool) $enabled;
    }

    public function create(NotificationIntent $intent): int
    {
        $existing = Db::table('notifications')
            ->where('recipient_id', $intent->recipientId())
            ->where('dedupe_key', $intent->dedupeKey())
            ->value('id');
        if ($existing !== null) return (int) $existing;

        $resource = $intent->resource();
        try {
            return (int) Db::table('notifications')->insertGetId([
                'recipient_id' => $intent->recipientId(),
                'dedupe_key' => $intent->dedupeKey(),
                'type' => $intent->type(),
                'actor_id' => $intent->actorId(),
                'resource_type' => $resource?->type(),
                'resource_id' => $resource?->id(),
                'payload' => json_encode($intent->data(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'read_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $exception) {
            $existing = Db::table('notifications')
                ->where('recipient_id', $intent->recipientId())
                ->where('dedupe_key', $intent->dedupeKey())
                ->value('id');
            if ($existing !== null) return (int) $existing;
            throw $exception;
        }
    }

    public function listForUser(
        int $userId,
        ?string $cursor,
        int $limit,
        bool $unreadOnly,
        ?string $type,
        ?string $category
    ): array
    {
        $query = Db::table('notifications')
            ->where('recipient_id', $userId)
            ->whereIn('type', NotificationTypeCatalog::types());
        $cursorAnchor = $this->cursorAnchor($userId, $cursor);
        if ($cursorAnchor !== null) {
            if ($cursorAnchor['legacy_id'] !== null) {
                $query->where('id', '<', $cursorAnchor['legacy_id']);
            } else {
                $query->where(function ($cursorQuery) use ($cursorAnchor): void {
                    $cursorQuery->where('created_at', '<', $cursorAnchor['created_at'])
                        ->whereOr(function ($sameTimestamp) use ($cursorAnchor): void {
                            $sameTimestamp
                                ->where('created_at', '=', $cursorAnchor['created_at'])
                                ->where('id', '<', $cursorAnchor['id']);
                        });
                });
            }
        }
        if ($unreadOnly) $query->whereNull('read_at');
        if ($type !== null) $query->where('type', NotificationTypeCatalog::normalizeType($type));
        if ($category !== null) $query->whereIn('type', NotificationTypeCatalog::typesForListCategory($category));

        $query->order('created_at', 'desc')->order('id', 'desc')->limit($limit + 1);

        $rows = $query->select()->toArray();
        $hasMore = count($rows) > $limit;
        if ($hasMore) array_pop($rows);
        $next = $hasMore && $rows !== [] ? $this->encodeCursor(
            (string) (end($rows)['created_at'] ?? ''),
            (int) (end($rows)['id'] ?? 0)
        ) : null;
        $rows = $this->hydrateReadModel($userId, $rows);
        return ['items' => $rows, 'next_cursor' => $next, 'has_more' => $hasMore];
    }

    /**
     * 将通知页装配成前端可直接消费的读取模型。所有关联数据均按本页批量读取，
     * 查询数量不会随通知条数增长。
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function hydrateReadModel(int $userId, array $rows): array
    {
        if ($rows === []) return [];

        $actorIds = [];
        $resourceIds = ['user' => [], 'card' => [], 'comment' => [], 'message' => []];
        foreach ($rows as &$row) {
            $row['payload'] = $this->payload($row['payload'] ?? null);
            $row['actor_id'] = isset($row['actor_id']) ? (int) $row['actor_id'] : null;
            $row['resource_type'] = isset($row['resource_type']) && trim((string) $row['resource_type']) !== ''
                ? strtolower(trim((string) $row['resource_type']))
                : null;
            $row['resource_id'] = isset($row['resource_id']) && trim((string) $row['resource_id']) !== ''
                ? trim((string) $row['resource_id'])
                : null;

            if (($row['actor_id'] ?? null) > 0) $actorIds[] = (int) $row['actor_id'];
            $resourceType = $row['resource_type'];
            if ($resourceType !== null
                && isset($resourceIds[$resourceType])
                && $row['resource_id'] !== null
                && ctype_digit($row['resource_id'])
                && (int) $row['resource_id'] > 0) {
                $resourceIds[$resourceType][] = (int) $row['resource_id'];
            }
        }
        unset($row);

        foreach ($resourceIds as &$ids) $ids = $this->uniqueIds($ids);
        unset($ids);
        $actorIds = $this->uniqueIds($actorIds);

        $users = $this->usersById(array_merge($actorIds, $resourceIds['user']));
        $userStates = $this->userResourceStates($userId, $resourceIds['user'], $users);
        $comments = $this->commentsById($resourceIds['comment']);

        $cardIds = $resourceIds['card'];
        foreach ($comments as $comment) {
            $cardId = (int) ($comment['pid'] ?? 0);
            if ($cardId > 0) $cardIds[] = $cardId;
        }
        $cards = $this->cardsById($cardIds);
        $messages = $this->messagesById($resourceIds['message']);
        $messageMembership = $this->messageMembership($userId, $messages);

        foreach ($rows as &$row) {
            $type = strtolower((string) ($row['type'] ?? ''));
            $actorId = $row['actor_id'];
            $actor = $actorId !== null ? ($users[$actorId] ?? null) : null;
            if ($actor !== null && $this->activeUser($actor)) {
                $username = trim((string) ($actor['username'] ?? ''));
                if ($username !== '') {
                    $row['actor'] = [
                        'uid' => (string) ($actor['uid'] ?? ''),
                        'username' => $username,
                        'avatar' => $this->nullableString($actor['avatar'] ?? null),
                    ];
                    $row['actor_state'] = 'active';
                } else {
                    $row['actor'] = null;
                    $row['actor_state'] = 'unavailable';
                }
            } else {
                $row['actor'] = null;
                $row['actor_state'] = $actorId === null && str_starts_with($type, 'system.')
                    ? 'system'
                    : 'unavailable';
            }

            [$resourceState, $actionUrl] = $this->resolveResource(
                $row,
                $userStates,
                $users,
                $cards,
                $comments,
                $messages,
                $messageMembership,
                $userId
            );
            $row['category'] = NotificationTypeCatalog::categoryFor($type);
            $row['resource_state'] = $resourceState;
            $row['action_url'] = $actionUrl;
        }
        unset($row);

        return $rows;
    }

    /** @return array<string, mixed> */
    private function payload(mixed $payload): array
    {
        if (is_array($payload)) return $payload;
        if (!is_string($payload) || $payload === '') return [];
        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @param array<int, int> $ids @return array<int, int> */
    private function uniqueIds(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
    }

    /** @param array<int, int> $ids @return array<int, array<string, mixed>> */
    private function usersById(array $ids): array
    {
        $ids = $this->uniqueIds($ids);
        if ($ids === []) return [];
        $rows = Db::table('users')->whereIn('id', $ids)->field('id,uid,username,avatar,status,deleted_at')->select()->toArray();
        $result = [];
        foreach ($rows as $row) $result[(int) $row['id']] = $row;
        return $result;
    }

    /**
     * @param array<int, int> $ids
     * @param array<int, array<string, mixed>> $users
     * @return array<int, string>
     */
    private function userResourceStates(int $viewerId, array $ids, array $users): array
    {
        $ids = $this->uniqueIds($ids);
        if ($ids === []) return [];

        $preferences = [];
        $preferenceRows = Db::table('privacy_preferences')
            ->whereIn('user_id', $ids)
            ->field('user_id,profile_visibility')
            ->select()
            ->toArray();
        foreach ($preferenceRows as $row) $preferences[(int) $row['user_id']] = (string) $row['profile_visibility'];

        $following = $this->idSet(Db::table('user_follows')
            ->where('follower_id', $viewerId)
            ->whereIn('followee_id', $ids)
            ->column('followee_id'));
        $blockedByViewer = $this->idSet(Db::table('user_safety_relations')
            ->where(['actor_id' => $viewerId, 'relation' => 'block'])
            ->whereIn('subject_id', $ids)
            ->column('subject_id'));
        $viewerBlockedBy = $this->idSet(Db::table('user_safety_relations')
            ->where(['subject_id' => $viewerId, 'relation' => 'block'])
            ->whereIn('actor_id', $ids)
            ->column('actor_id'));

        $states = [];
        foreach ($ids as $id) {
            $user = $users[$id] ?? null;
            if ($user === null || !empty($user['deleted_at'])) {
                $states[$id] = 'deleted';
                continue;
            }
            if ((int) ($user['status'] ?? -1) !== 0) {
                $states[$id] = 'restricted';
                continue;
            }
            if (isset($blockedByViewer[$id]) || isset($viewerBlockedBy[$id])) {
                $states[$id] = 'restricted';
                continue;
            }

            $visibility = $preferences[$id] ?? 'public';
            $states[$id] = $id === $viewerId
                || $visibility === 'public'
                || ($visibility === 'followers' && isset($following[$id]))
                ? 'available'
                : 'restricted';
        }
        return $states;
    }

    /** @param array<int, mixed> $ids @return array<int, true> */
    private function idSet(array $ids): array
    {
        $set = [];
        foreach ($this->uniqueIds($ids) as $id) $set[$id] = true;
        return $set;
    }

    /** @param array<int, int> $ids @return array<int, array<string, mixed>> */
    private function commentsById(array $ids): array
    {
        $ids = $this->uniqueIds($ids);
        if ($ids === []) return [];
        $rows = Db::table('comments')->whereIn('id', $ids)->field('id,pid,status,deleted_at')->select()->toArray();
        $result = [];
        foreach ($rows as $row) $result[(int) $row['id']] = $row;
        return $result;
    }

    /** @param array<int, int> $ids @return array<int, array<string, mixed>> */
    private function cardsById(array $ids): array
    {
        $ids = $this->uniqueIds($ids);
        if ($ids === []) return [];
        $rows = Db::table('cards')->whereIn('id', $ids)->field('id,status,deleted_at')->select()->toArray();
        $result = [];
        foreach ($rows as $row) $result[(int) $row['id']] = $row;
        return $result;
    }

    /** @param array<int, int> $ids @return array<int, array<string, mixed>> */
    private function messagesById(array $ids): array
    {
        $ids = $this->uniqueIds($ids);
        if ($ids === []) return [];
        $rows = Db::table('messages')->whereIn('id', $ids)->field('id,conversation_id,deleted_at')->select()->toArray();
        $result = [];
        foreach ($rows as $row) $result[(int) $row['id']] = $row;
        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, true>
     */
    private function messageMembership(int $userId, array $messages): array
    {
        $conversationIds = $this->uniqueIds(array_map(
            static fn(array $message): int => (int) ($message['conversation_id'] ?? 0),
            $messages
        ));
        if ($conversationIds === []) return [];
        return $this->idSet(Db::table('conversation_members')
            ->where('user_id', $userId)
            ->whereIn('conversation_id', $conversationIds)
            ->column('conversation_id'));
    }

    /**
     * @param array<string, mixed> $notification
     * @param array<int, string> $userStates
     * @param array<int, array<string, mixed>> $users
     * @param array<int, array<string, mixed>> $cards
     * @param array<int, array<string, mixed>> $comments
     * @param array<int, array<string, mixed>> $messages
     * @param array<int, true> $messageMembership
     * @return array{0:string,1:?string}
     */
    private function resolveResource(
        array $notification,
        array $userStates,
        array $users,
        array $cards,
        array $comments,
        array $messages,
        array $messageMembership,
        int $viewerId = 0
    ): array {
        $notificationType = strtolower((string) ($notification['type'] ?? ''));
        $resourceType = $notification['resource_type'] ?? null;
        $resourceId = $notification['resource_id'] ?? null;

        if (str_starts_with($notificationType, 'system.')) {
            return ['available', $this->safeInternalPath($notification['payload']['action_url'] ?? null)];
        }
        if (!is_string($resourceType) || !is_string($resourceId) || !ctype_digit($resourceId) || (int) $resourceId < 1) {
            return ['unavailable', null];
        }
        $id = (int) $resourceId;

        if ($resourceType === 'user') {
            $state = $userStates[$id] ?? 'deleted';
            $uid = trim((string) ($users[$id]['uid'] ?? ''));
            return [$state, $state === 'available' && $uid !== '' ? '/user/' . rawurlencode($uid) : null];
        }

        if ($resourceType === 'card') {
            $state = $this->publicationState($cards[$id] ?? null);
            if ($state === 'available' && !CardVisibilityPolicy::canRead($cards[$id] ?? [], $viewerId)) $state = 'restricted';
            return [$state, $state === 'available' ? '/post/' . $id : null];
        }

        if ($resourceType === 'comment') {
            $comment = $comments[$id] ?? null;
            $state = $this->publicationState($comment);
            $cardId = (int) ($comment['pid'] ?? 0);
            if ($state === 'available') {
                $cardState = $this->publicationState($cards[$cardId] ?? null);
                if ($cardState !== 'available') $state = $cardState;
                elseif (!CardVisibilityPolicy::canRead($cards[$cardId] ?? [], $viewerId)) $state = 'restricted';
            }
            return [
                $state,
                $state === 'available' && $cardId > 0 ? '/post/' . $cardId . '/thread/' . $id : null,
            ];
        }

        if ($resourceType === 'message') {
            $message = $messages[$id] ?? null;
            if ($message === null || !empty($message['deleted_at'])) return ['deleted', null];
            $conversationId = (int) ($message['conversation_id'] ?? 0);
            if ($conversationId < 1) return ['unavailable', null];
            if (!isset($messageMembership[$conversationId])) return ['restricted', null];
            return ['available', '/messages/' . $conversationId];
        }

        return ['unavailable', null];
    }

    /** @param array<string, mixed>|null $row */
    private function publicationState(?array $row): string
    {
        if ($row === null || !empty($row['deleted_at'])) return 'deleted';
        return (int) ($row['status'] ?? -1) === 0 ? 'available' : 'restricted';
    }

    /** @param array<string, mixed> $user */
    private function activeUser(array $user): bool
    {
        return UserStatus::isPublic((int) ($user['status'] ?? -1)) && empty($user['deleted_at']);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function safeInternalPath(mixed $value): ?string
    {
        if (!is_string($value)) return null;
        $value = trim($value);
        if ($value === '' || strlen($value) > 2048) return null;

        $decoded = rawurldecode($value);
        if (!str_starts_with($value, '/')
            || str_starts_with($value, '//')
            || str_contains($value, '\\')
            || str_contains($value, '://')
            || str_contains($decoded, '//')
            || str_contains($decoded, '\\')
            || str_contains($decoded, '://')
            || preg_match('/[\x00-\x1F\x7F]/', $value) === 1
            || preg_match('/[\x00-\x1F\x7F]/', $decoded) === 1) {
            return null;
        }

        $parts = parse_url($value);
        if ($parts === false
            || isset($parts['scheme'])
            || isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['port'])) {
            return null;
        }

        return $value;
    }

    public function markRead(int $userId, array $ids): int
    {
        return Db::table('notifications')->where('recipient_id', $userId)->whereIn('id', $ids)->whereNull('read_at')
            ->update(['read_at' => date('Y-m-d H:i:s')]);
    }

    public function markAllRead(int $userId, ?string $category = null): int
    {
        $query = Db::table('notifications')->where('recipient_id', $userId)->whereNull('read_at');
        if ($category !== null) $query->whereIn('type', NotificationTypeCatalog::typesForListCategory($category));
        return $query
            ->update(['read_at' => date('Y-m-d H:i:s')]);
    }

    public function unreadCount(int $userId, ?string $category = null): int
    {
        $query = Db::table('notifications')
            ->where('recipient_id', $userId)
            ->whereIn('type', NotificationTypeCatalog::types())
            ->whereNull('read_at');
        if ($category !== null) $query->whereIn('type', NotificationTypeCatalog::typesForListCategory($category));
        return (int) $query->count();
    }

    public function preferences(int $userId): array
    {
        $rows = Db::table('notification_preferences')->where('user_id', $userId)->column('enabled', 'notification_type');
        $result = [];
        foreach (NotificationTypeCatalog::configurableTypes() as $type) {
            $result[$type] = !array_key_exists($type, $rows) || (bool) $rows[$type];
        }
        return $result;
    }

    public function savePreferences(int $userId, array $preferences): void
    {
        Db::startTrans();
        try {
            $now = date('Y-m-d H:i:s');
            foreach ($preferences as $type => $enabled) {
                $key = ['user_id' => $userId, 'notification_type' => $type];
                $values = ['enabled' => $enabled ? 1 : 0, 'updated_at' => $now];
                if (Db::table('notification_preferences')->where($key)->value('user_id') !== null) {
                    Db::table('notification_preferences')->where($key)->update($values);
                } else {
                    Db::table('notification_preferences')->insert($key + $values);
                }
            }
            Db::commit();
        } catch (\Throwable $exception) {
            Db::rollback();
            throw $exception;
        }
    }

    /** @return array{created_at:string,id:int,legacy_id:?int}|null */
    private function cursorAnchor(int $userId, ?string $cursor): ?array
    {
        if ($cursor === null || trim($cursor) === '') return null;
        $cursor = trim($cursor);
        if (ctype_digit($cursor)) {
            $legacyId = (int) $cursor;
            if ($legacyId < 1) throw new \InvalidArgumentException('通知游标无效');
            $row = Db::table('notifications')
                ->where('recipient_id', $userId)
                ->where('id', $legacyId)
                ->field('id,created_at')
                ->find();
            if ($row === null) return ['created_at' => '', 'id' => 0, 'legacy_id' => $legacyId];
            return [
                'created_at' => (string) ($row['created_at'] ?? ''),
                'id' => $legacyId,
                'legacy_id' => null,
            ];
        }

        $padding = strlen($cursor) % 4;
        $encoded = strtr($cursor, '-_', '+/');
        if ($padding > 0) $encoded .= str_repeat('=', 4 - $padding);
        $decoded = base64_decode($encoded, true);
        $payload = is_string($decoded) ? json_decode($decoded, true) : null;
        $createdAt = is_array($payload) ? trim((string) ($payload['created_at'] ?? '')) : '';
        $id = is_array($payload) ? (int) ($payload['id'] ?? 0) : 0;
        if ($createdAt === '' || $id < 1 || preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $createdAt) !== 1) {
            throw new \InvalidArgumentException('通知游标无效');
        }
        return ['created_at' => $createdAt, 'id' => $id, 'legacy_id' => null];
    }

    private function encodeCursor(string $createdAt, int $id): string
    {
        $payload = json_encode(['created_at' => $createdAt, 'id' => $id], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }
}
