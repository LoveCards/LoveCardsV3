<?php

declare(strict_types=1);

namespace app\modules\Content\Mentions\Service;

use app\api\ApiException;
use app\modules\Content\Mentions\Domain\MentionParser;
use app\modules\Platform\Eventing\Public\DomainEventBus;
use think\facade\Db;

final class Mentions
{
    private const MAX_MENTIONS = 50;
    private const TYPES = ['card', 'comment'];

    private static function lowercase(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    /**
     * Build public, server-authenticated mention ranges for hydrated resources.
     *
     * @param array<int,array<string,mixed>> $resources
     * @return array<int,array<int,array{start:int,end:int,username:string,uid:?string,state:string,action_url:?string}>>
     */
    public static function projectionsForResources(string $resourceType, array $resources): array
    {
        if (!in_array($resourceType, self::TYPES, true) || $resources === []) return [];
        $resourcesById = [];
        foreach ($resources as $resource) {
            $id = (int) ($resource['id'] ?? 0);
            if ($id > 0) $resourcesById[$id] = (string) ($resource['content'] ?? '');
        }
        if ($resourcesById === []) return [];

        $rows = Db::table('content_mentions')
            ->where('resource_type', $resourceType)
            ->whereIn('resource_id', array_keys($resourcesById))
            ->field('resource_id,mentioned_user_id,mentioned_username')
            ->order('id', 'asc')
            ->select()
            ->toArray();
        if ($rows === []) return [];

        $userIds = array_values(array_unique(array_filter(array_map(
            static fn(array $row): int => (int) ($row['mentioned_user_id'] ?? 0),
            $rows
        ), static fn(int $id): bool => $id > 0)));
        $users = [];
        if ($userIds !== []) {
            foreach (Db::table('users')->whereIn('id', $userIds)->field('id,uid,status,deleted_at')->select()->toArray() as $user) {
                $users[(int) $user['id']] = $user;
            }
        }

        $resolved = [];
        foreach ($rows as $row) {
            $resourceId = (int) ($row['resource_id'] ?? 0);
            $username = trim((string) ($row['mentioned_username'] ?? ''));
            if (!isset($resourcesById[$resourceId]) || $username === '') continue;
            $user = $users[(int) ($row['mentioned_user_id'] ?? 0)] ?? null;
            $uid = trim((string) ($user['uid'] ?? ''));
            $active = $user !== null && (int) ($user['status'] ?? -1) === 0 && empty($user['deleted_at']) && $uid !== '';
            $resolved[$resourceId][self::lowercase($username)] = [
                'username' => $username,
                'uid' => $active ? $uid : null,
                'state' => $active ? 'active' : 'unavailable',
                'action_url' => $active ? '/user/' . rawurlencode($uid) : null,
            ];
        }

        $parser = new MentionParser();
        $result = [];
        foreach ($resourcesById as $resourceId => $content) {
            foreach ($parser->occurrences($content) as $occurrence) {
                $mention = $resolved[$resourceId][self::lowercase($occurrence['username'])] ?? null;
                if ($mention === null) continue;
                $result[$resourceId][] = array_merge([
                    'start' => $occurrence['start'],
                    'end' => $occurrence['end'],
                ], $mention);
            }
        }
        return $result;
    }

    /**
     * Persist newly observed mention relationships. Callers must invoke this
     * inside the content publication/update transaction.
     *
     * @return array<int, array{id:int,user_id:int,username:string}>
     */
    public static function capture(
        string $resourceType,
        int $resourceId,
        int $cardId,
        ?int $commentId,
        int $actorId,
        array $texts
    ): array {
        if (!in_array($resourceType, self::TYPES, true)
            || $resourceId < 1 || $cardId < 1 || $actorId < 1
            || ($resourceType === 'comment' && ($commentId ?? 0) < 1)) {
            throw new \InvalidArgumentException('提及来源无效');
        }

        $handles = (new MentionParser())->parse($texts);
        if (count($handles) > self::MAX_MENTIONS) {
            throw ApiException::badRequest('单条内容最多提及 ' . self::MAX_MENTIONS . ' 位用户');
        }
        if ($handles === []) return [];

        $users = Db::table('users')
            ->whereIn('username', $handles)
            ->where('status', 0)
            ->whereNull('deleted_at')
            ->field('id,username')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        $resolved = [];
        foreach ($users as $user) {
            $userId = (int) ($user['id'] ?? 0);
            $username = trim((string) ($user['username'] ?? ''));
            if ($userId < 1 || $userId === $actorId || $username === '') continue;
            $key = self::lowercase($username);
            if (isset($resolved[$key]) && $resolved[$key]['id'] !== $userId) {
                throw new \UnexpectedValueException('用户名必须保持唯一，无法解析提及');
            }
            $resolved[$key] = ['id' => $userId, 'username' => $username];
        }
        if ($resolved === []) return [];

        $userIds = array_values(array_unique(array_column($resolved, 'id')));
        $blocked = array_fill_keys(array_merge(
            Db::table('user_safety_relations')
                ->where(['actor_id' => $actorId, 'relation' => 'block'])
                ->whereIn('subject_id', $userIds)
                ->column('subject_id'),
            Db::table('user_safety_relations')
                ->where(['subject_id' => $actorId, 'relation' => 'block'])
                ->whereIn('actor_id', $userIds)
                ->column('actor_id')
        ), true);

        $eligible = [];
        foreach ($handles as $handle) {
            $user = $resolved[self::lowercase($handle)] ?? null;
            if ($user === null || isset($blocked[$user['id']])) continue;
            $eligible[$user['id']] = $user;
        }
        if ($eligible === []) return [];

        $existing = array_fill_keys(Db::table('content_mentions')
            ->where(['resource_type' => $resourceType, 'resource_id' => $resourceId])
            ->whereIn('mentioned_user_id', array_keys($eligible))
            ->column('mentioned_user_id'), true);

        $created = [];
        $now = date('Y-m-d H:i:s');
        foreach ($eligible as $userId => $user) {
            if (isset($existing[$userId])) continue;
            $row = [
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'card_id' => $cardId,
                'comment_id' => $commentId,
                'actor_id' => $actorId,
                'mentioned_user_id' => $userId,
                'mentioned_username' => $user['username'],
                'created_at' => $now,
            ];
            try {
                $id = (int) Db::table('content_mentions')->insertGetId($row);
            } catch (\Throwable $exception) {
                if (!self::isDuplicateKeyException($exception)) throw $exception;
                $id = (int) (Db::table('content_mentions')
                    ->where([
                        'resource_type' => $resourceType,
                        'resource_id' => $resourceId,
                        'mentioned_user_id' => $userId,
                    ])
                    ->lock(true)
                    ->value('id') ?? 0);
                if ($id > 0) continue;
                throw $exception;
            }
            $created[] = ['id' => $id, 'user_id' => $userId, 'username' => $user['username']];
        }

        return $created;
    }

    private static function isDuplicateKeyException(\Throwable $exception): bool
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            $sqlState = null;
            $driverCode = null;

            if ($current instanceof \PDOException && is_array($current->errorInfo ?? null)) {
                $sqlState = $current->errorInfo[0] ?? null;
                $driverCode = $current->errorInfo[1] ?? null;
            }
            if (is_callable([$current, 'getData'])) {
                $data = $current->getData();
                $pdoInfo = is_array($data) ? ($data['PDO Error Info'] ?? null) : null;
                if (is_array($pdoInfo)) {
                    $sqlState = $pdoInfo['SQLSTATE'] ?? $sqlState;
                    $driverCode = $pdoInfo['Driver Error Code'] ?? $driverCode;
                }
            }

            if ((string) $sqlState === '23000' && (int) $driverCode === 1062) return true;
        }
        return false;
    }

    /** Publish only newly persisted relations; retries remain business-key idempotent. */
    public static function publishAdded(
        string $resourceType,
        int $resourceId,
        int $cardId,
        ?int $commentId,
        int $actorId,
        array $mentions,
        array $suppressedRecipientIds = []
    ): void {
        if ($mentions === []) return;
        app(DomainEventBus::class)->publish(
            'content.mentions.added',
            $resourceType,
            (string) $resourceId,
            [
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'card_id' => $cardId,
                'comment_id' => $commentId,
                'actor_id' => $actorId,
                'mentions' => array_values($mentions),
                'suppressed_recipient_ids' => array_values(array_unique(array_map('intval', $suppressedRecipientIds))),
            ],
            $actorId
        );
    }
}
