<?php

declare(strict_types=1);

namespace app\modules\Operations\Runtime\Infrastructure;

use app\modules\Operations\Runtime\Contract\StreamTicketStore;
use think\facade\Db;

/**
 * stream_tickets 表访问：只存 sha256，verify 即原子置 used_at（一次性）。
 */
final class ThinkDbStreamTicketStore implements StreamTicketStore
{
    private const TTL_SECONDS = 60;

    public function issue(int $uid, string $sessionPublicId, string $ticketPlain): bool
    {
        if ($uid < 1 || strlen($ticketPlain) < 16) {
            return false;
        }
        $now = gmdate('Y-m-d H:i:s');
        $expires = gmdate('Y-m-d H:i:s', time() + self::TTL_SECONDS);
        return Db::table('stream_tickets')->insert([
            'ticket_hash' => hash('sha256', $ticketPlain),
            'uid' => $uid,
            'session_public_id' => $sessionPublicId,
            'expires_at' => $expires,
            'used_at' => null,
            'created_at' => $now,
        ]) === 1;
    }

    public function consume(string $ticketPlain): ?array
    {
        if (strlen($ticketPlain) < 16 || strlen($ticketPlain) > 128) {
            return null;
        }
        $hash = hash('sha256', $ticketPlain);
        $now = gmdate('Y-m-d H:i:s');
        // 原子“领取”：并发重复 verify 最多一个成功。
        $claimed = Db::table('stream_tickets')
            ->where('ticket_hash', $hash)
            ->whereNull('used_at')
            ->where('expires_at', '>', $now)
            ->update(['used_at' => $now]);
        if ($claimed !== 1) {
            return null;
        }
        $row = Db::table('stream_tickets')->where('ticket_hash', $hash)->find();
        if (!is_array($row) || (int) ($row['uid'] ?? 0) < 1) {
            return null;
        }
        return ['uid' => (int) $row['uid'], 'session_public_id' => $row['session_public_id'] ?? null];
    }
}
