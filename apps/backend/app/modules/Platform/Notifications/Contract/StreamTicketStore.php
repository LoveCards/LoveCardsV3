<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Contract;

/**
 * 一次性 stream ticket 存储（契约 §4.4）。
 * 明文票据只在签发响应中出现一次，存储侧只保留 sha256。
 */
interface StreamTicketStore
{
    /** 写入新 ticket（有效期 60s），返回是否成功。 */
    public function issue(int $uid, string $ticketPlain): bool;

    /**
     * 原子消费一次性 ticket：重复使用/过期返回 null。
     *
     * @return array{uid: int, cursor_seed: ?string}|null
     */
    public function consume(string $ticketPlain): ?array;
}
