<?php
declare(strict_types=1);

namespace app\modules\Platform\Messaging\Contract;

interface MessagingRepository
{
    /** @return array<int,array<string,mixed>> */
    public function conversationsForUser(int $userId): array;

    /** @return array{items:array<int,array<string,mixed>>,next_cursor:?string,has_more:bool} */
    public function messagesForUser(int $userId, int $conversationId, ?string $cursor, int $limit): array;

    public function findMessageIdByClientId(int $senderId, int $recipientId, string $clientMessageId): ?int;

    public function findOrCreateDirectConversation(int $firstUserId, int $secondUserId, \DateTimeImmutable $at): int;

    public function createMessage(int $conversationId, int $senderId, string $clientMessageId, string $body, \DateTimeImmutable $at): int;

    public function touchConversation(int $conversationId, \DateTimeImmutable $at): void;

    public function markReadThrough(int $userId, int $conversationId, int $messageId): void;
}
