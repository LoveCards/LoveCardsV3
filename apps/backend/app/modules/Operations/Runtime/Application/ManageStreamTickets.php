<?php

declare(strict_types=1);

namespace app\modules\Operations\Runtime\Application;

use app\modules\Operations\Runtime\Contract\StreamTicketStore;
use app\modules\Platform\Auth\Public\SessionAccess;

/**
 * 浏览器 realtime 会话支撑：一次性 ticket 签发/消费。
 * ticket 明文 26-hex（13 熵字节），60s TTL，verify 即消费（契约 §4.4）。
 */
final class ManageStreamTickets
{
    public const TTL_SECONDS = 60;
    public const HEARTBEAT_SECONDS = 15;

    public function __construct(
        private StreamTicketStore $store,
        private SessionAccess $sessions,
        private \Closure $selectedMode
    ) {
    }

    /** @return array{ticket: string, expires_in: int, connect_url: string, heartbeat_seconds: int} */
    public function issue(int $uid, string $sessionPublicId): array
    {
        if (($this->selectedMode)() !== 'realtime' || $this->sessions->remainingSeconds($uid, $sessionPublicId) < 1) {
            throw new \DomainException('实时会话不可用');
        }
        $ticket = bin2hex(random_bytes(13));
        if (!$this->store->issue($uid, $sessionPublicId, $ticket)) {
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

    /** @return array{uid: int, cursor_seed: null, connection_ttl_seconds: int}|null */
    public function verify(string $ticket): ?array
    {
        $row = $this->store->consume($ticket);
        if ($row === null || ($this->selectedMode)() !== 'realtime') return null;
        $ttl = $this->sessions->remainingSeconds((int) $row['uid'], (string) ($row['session_public_id'] ?? ''));
        if ($ttl < 1) return null;
        return ['uid' => (int) $row['uid'], 'cursor_seed' => null, 'connection_ttl_seconds' => min(60, $ttl)];
    }

}
