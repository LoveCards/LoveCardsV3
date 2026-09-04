<?php
declare(strict_types=1);

namespace app\modules\Platform\Messaging\Public;

use app\modules\Platform\Messaging\Application\Messaging as MessagingApplication;

final class Messaging
{
    private static function useCase(): MessagingApplication { return app(MessagingApplication::class); }
    public static function conversations(int $userId): array { return self::useCase()->conversations($userId); }
    public static function messages(int $userId, int $conversationId, ?string $cursor = null, int $limit = 30): array { return self::useCase()->messages($userId, $conversationId, $cursor, $limit); }
    /** @return array{id:int,conversation_id:int} */
    public static function send(int $userId, int $recipientId, string $body, ?string $clientMessageId = null): array { return self::useCase()->send($userId, $recipientId, $body, $clientMessageId); }
}
