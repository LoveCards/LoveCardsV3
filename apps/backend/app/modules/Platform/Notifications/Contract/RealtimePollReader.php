<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Contract;

/**
 * Gateway source-poll 回源读取（契约 §4.5，Redis 断链时的补偿通道）。
 * 只返回游标与计数事实，不返回业务内容。
 */
interface RealtimePollReader
{
    /**
     * @return array{latest_cursor: ?string, newer: int}
     */
    public function since(int $uid, ?string $cursor, int $limit): array;
}
