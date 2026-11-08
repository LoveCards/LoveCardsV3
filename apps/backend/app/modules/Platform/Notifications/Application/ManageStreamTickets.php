<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Application;

use app\modules\Platform\Notifications\Contract\RealtimePollReader;
use app\modules\Platform\Notifications\Contract\StreamTicketStore;

/**
 * 浏览器 realtime 会话支撑：一次性 ticket 签发/消费 + Gateway 回源读取。
 * ticket 明文 26-hex（13 熵字节），60s TTL，verify 即消费（契约 §4.4）。
 */
final class ManageStreamTickets
{
    public const TTL_SECONDS = 60;
    public const HEARTBEAT_SECONDS = 15;

    public function __construct(
        private StreamTicketStore $store,
        private RealtimePollReader $pollReader
    ) {
    }

    /** @return array{ticket: string, expires_in: int, connect_url: string, heartbeat_seconds: int} */
    public function issue(int $uid): array
    {
        $ticket = bin2hex(random_bytes(13));
        if (!$this->store->issue($uid, $ticket)) {
            throw new \RuntimeException('stream ticket 签发失败');
        }
        $publicBase = rtrim((string) env('LC_REALTIME_GATEWAY_PUBLIC_URL', ''), '/');
        return [
            'ticket' => $ticket,
            'expires_in' => self::TTL_SECONDS,
            'connect_url' => $publicBase === '' ? '' : $publicBase . '/stream',
            'heartbeat_seconds' => self::HEARTBEAT_SECONDS,
        ];
    }

    /** @return array{uid: int, cursor_seed: ?string}|null */
    public function verify(string $ticket): ?array
    {
        return $this->store->consume($ticket);
    }

    /** @return array{latest_cursor: ?string, newer: int} */
    public function poll(int $uid, ?string $cursor, int $limit = 50): array
    {
        return $this->pollReader->since($uid, $cursor, $limit);
    }
}
