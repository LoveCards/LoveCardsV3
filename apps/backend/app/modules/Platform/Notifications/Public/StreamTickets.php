<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Public;

use app\modules\Platform\Notifications\Application\ManageStreamTickets;
use app\modules\Platform\Notifications\Contract\RealtimePollReader;
use app\modules\Platform\Notifications\Contract\StreamTicketStore;

/**
 * realtime 流会话对外门面：签发/校验 stream ticket、Gateway 回源读取。
 */
final class StreamTickets
{
    private static function useCase(): ManageStreamTickets
    {
        return new ManageStreamTickets(app(StreamTicketStore::class), app(RealtimePollReader::class));
    }

    /** @return array{ticket: string, expires_in: int, connect_url: string, heartbeat_seconds: int} */
    public static function issue(int $uid): array
    {
        return self::useCase()->issue($uid);
    }

    /** @return array{uid: int, cursor_seed: ?string}|null */
    public static function verify(string $ticket): ?array
    {
        return self::useCase()->verify($ticket);
    }

    /** @return array{latest_cursor: ?string, newer: int} */
    public static function poll(int $uid, ?string $cursor, int $limit = 50): array
    {
        return self::useCase()->poll($uid, $cursor, $limit);
    }
}
