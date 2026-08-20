<?php
declare(strict_types=1);

namespace app\modules\Platform\Messaging\Infrastructure;

use app\modules\Platform\Messaging\Contract\MessagingRepository;
use think\facade\Db;

final class ThinkDbMessagingRepository implements MessagingRepository
{
    public function conversationsForUser(int $userId): array
    {
        $rows = Db::query(
            'SELECT c.id, c.updated_at, peer.user_id AS recipient_id, u.number, u.username, u.avatar, '
            . 'last_message.id AS last_message_id, last_message.sender_id AS last_sender_id, '
            . 'last_message.body AS last_message_body, last_message.created_at AS last_message_at, '
            . 'SUM(CASE WHEN m.id > COALESCE(self_member.last_read_message_id, 0) AND m.sender_id <> ? THEN 1 ELSE 0 END) AS unread_count '
            . 'FROM conversation_members self_member '
            . 'JOIN conversations c ON c.id = self_member.conversation_id '
            . 'JOIN conversation_members peer ON peer.conversation_id = c.id AND peer.user_id <> self_member.user_id '
            . 'JOIN users u ON u.id = peer.user_id AND u.deleted_at IS NULL '
            . 'LEFT JOIN messages m ON m.conversation_id = c.id AND m.deleted_at IS NULL '
            . 'LEFT JOIN messages last_message ON last_message.id = (SELECT lm.id FROM messages lm WHERE lm.conversation_id = c.id AND lm.deleted_at IS NULL ORDER BY lm.id DESC LIMIT 1) '
            . 'WHERE self_member.user_id = ? '
            . 'GROUP BY c.id, c.updated_at, peer.user_id, u.number, u.username, u.avatar, last_message.id, last_message.sender_id, last_message.body, last_message.created_at '
            . 'ORDER BY c.updated_at DESC, c.id DESC',
            [$userId, $userId]
        );
        foreach ($rows as &$row) $row['unread_count'] = (int) $row['unread_count'];
        unset($row);
        return $rows;
    }

    public function messagesForUser(int $userId, int $conversationId, ?string $cursor, int $limit): array
    {
        $this->assertMember($userId, $conversationId);
        $query = Db::table('messages')
            ->where('conversation_id', $conversationId)
            ->whereNull('deleted_at')
            ->order('id', 'desc')
            ->limit($limit + 1);
        if ($cursor !== null && ctype_digit($cursor)) $query->where('id', '<', (int) $cursor);
        $rows = $query->select()->toArray();
        $hasMore = count($rows) > $limit;
        if ($hasMore) array_pop($rows);
        return [
            'items' => $rows,
            'next_cursor' => $hasMore && $rows !== [] ? (string) end($rows)['id'] : null,
            'has_more' => $hasMore,
        ];
    }

    public function findMessageIdByClientId(int $senderId, int $recipientId, string $clientMessageId): ?int
    {
        $id = Db::table('messages')->alias('m')
            ->join('conversation_members member', 'member.conversation_id = m.conversation_id')
            ->where('m.sender_id', $senderId)
            ->where('m.client_message_id', $clientMessageId)
            ->where('member.user_id', $recipientId)
            ->value('m.id');
        return $id === null ? null : (int) $id;
    }

    public function findOrCreateDirectConversation(int $firstUserId, int $secondUserId, \DateTimeImmutable $at): int
    {
        $key = $this->directKey($firstUserId, $secondUserId);
        $existing = Db::table('conversations')->where('direct_key', $key)->value('id');
        if ($existing !== null) return (int) $existing;

        $legacy = Db::query(
            'SELECT first_member.conversation_id FROM conversation_members first_member '
            . 'JOIN conversation_members second_member ON second_member.conversation_id = first_member.conversation_id '
            . 'WHERE first_member.user_id = ? AND second_member.user_id = ? '
            . 'AND (SELECT COUNT(*) FROM conversation_members members WHERE members.conversation_id = first_member.conversation_id) = 2 '
            . 'ORDER BY first_member.conversation_id ASC LIMIT 1',
            [$firstUserId, $secondUserId]
        );
        if ($legacy !== []) {
            $conversationId = (int) $legacy[0]['conversation_id'];
            try {
                Db::table('conversations')->where('id', $conversationId)->whereNull('direct_key')->update(['direct_key' => $key]);
            } catch (\Throwable $exception) {
                $winner = Db::table('conversations')->where('direct_key', $key)->value('id');
                if ($winner === null) throw $exception;
                return (int) $winner;
            }
            return $conversationId;
        }

        $timestamp = $this->utc($at);
        try {
            $conversationId = (int) Db::table('conversations')->insertGetId([
                'direct_key' => $key,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        } catch (\Throwable $exception) {
            $winner = Db::table('conversations')->where('direct_key', $key)->lock(true)->value('id');
            if ($winner === null) throw $exception;
            return (int) $winner;
        }

        // 只有 conversation 唯一键竞争可以回退到 winner。成员写入失败必须
        // 向上传播并回滚，不能提交一个没有完整双方成员的会话。
        Db::table('conversation_members')->insertAll([
            ['conversation_id' => $conversationId, 'user_id' => $firstUserId, 'last_read_message_id' => null, 'created_at' => $timestamp],
            ['conversation_id' => $conversationId, 'user_id' => $secondUserId, 'last_read_message_id' => null, 'created_at' => $timestamp],
        ]);
        return $conversationId;
    }

    public function createMessage(int $conversationId, int $senderId, string $clientMessageId, string $body, \DateTimeImmutable $at): int
    {
        return (int) Db::table('messages')->insertGetId([
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'client_message_id' => $clientMessageId,
            'body' => $body,
            'created_at' => $this->utc($at),
            'deleted_at' => null,
        ]);
    }

    public function touchConversation(int $conversationId, \DateTimeImmutable $at): void
    {
        Db::table('conversations')->where('id', $conversationId)->update(['updated_at' => $this->utc($at)]);
    }

    public function markReadThrough(int $userId, int $conversationId, int $messageId): void
    {
        Db::table('conversation_members')
            ->where('conversation_id', $conversationId)
            ->where('user_id', $userId)
            ->where(function ($query) use ($messageId): void {
                $query->whereNull('last_read_message_id')->whereOr('last_read_message_id', '<', $messageId);
            })
            ->update(['last_read_message_id' => $messageId]);
    }

    private function assertMember(int $userId, int $conversationId): void
    {
        if (Db::table('conversation_members')->where(['conversation_id' => $conversationId, 'user_id' => $userId])->find() === null) {
            throw new \DomainException('无权访问会话');
        }
    }

    private function directKey(int $firstUserId, int $secondUserId): string
    {
        return hash('sha256', min($firstUserId, $secondUserId) . ':' . max($firstUserId, $secondUserId));
    }

    private function utc(\DateTimeImmutable $value): string
    {
        return $value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
