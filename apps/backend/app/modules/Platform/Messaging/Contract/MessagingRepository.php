<?php
declare(strict_types=1);

namespace app\modules\Platform\Messaging\Contract;

interface MessagingRepository
{
    /** @return array<int,array<string,mixed>> */
    public function conversationsForUser(int $userId): array;

    /** @return array{items:array<int,array<string,mixed>>,next_cursor:?string,has_more:bool} */
    public function messagesForUser(int $userId, int $conversationId, ?string $cursor, int $limit): array;

    /** @return array{id:int,conversation_id:int}|null */
    public function findMessageByClientId(int $senderId, int $recipientId, string $clientMessageId): ?array;

    public function findOrCreateDirectConversation(int $firstUserId, int $secondUserId, \DateTimeImmutable $at): int;

    public function createMessage(int $conversationId, int $senderId, string $clientMessageId, string $body, \DateTimeImmutable $at): int;

    public function touchConversation(int $conversationId, \DateTimeImmutable $at): void;

    /** 仅游标真正推进时返回 true。 */
    public function markReadThrough(int $userId, int $conversationId, int $messageId): bool;

    /** @return int[] */
    public function participantIds(int $conversationId): array;
}
