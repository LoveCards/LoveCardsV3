<?php

declare(strict_types=1);

namespace app\modules\Operations\Runtime\Public;

use app\modules\Operations\Runtime\Application\ManageStreamTickets;
use app\modules\Operations\Runtime\Contract\StreamTicketStore;
use app\modules\Platform\Auth\Public\SessionAccess;

/**
 * realtime 流会话对外门面：签发/校验 stream ticket。
 */
final class StreamTickets
{
    private static function useCase(): ManageStreamTickets
    {
        return new ManageStreamTickets(app(StreamTicketStore::class), app(SessionAccess::class),
            static fn (): string => app(RuntimeCommunication::class)->selectedMode());
    }

    /** @return array{ticket: string, expires_in: int, connect_url: string, heartbeat_seconds: int} */
    public static function issue(int $uid, string $sessionPublicId): array
    {
        return self::useCase()->issue($uid, $sessionPublicId);
    }

    /** @return array{uid: int, cursor_seed: null, connection_ttl_seconds: int}|null */
    public static function verify(string $ticket): ?array
    {
        return self::useCase()->verify($ticket);
    }

}
