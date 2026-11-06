<?php
declare(strict_types=1);

namespace app\api\controller\Platform;

use app\api\ApiException;
use app\api\controller\BaseController;
use app\modules\Platform\Notifications\Public\StreamTickets;

/**
 * Realtime Gateway 的内部对账端点（InternalTokenCheck 保护，非 JWT）。
 * 只提供 ticket 消费与游标回源，不提供业务内容读取。
 */
final class RealtimeInternal extends BaseController
{
    public function verifyTicket()
    {
        $result = StreamTickets::verify((string) ($this->validated()['ticket'] ?? ''));
        if ($result === null) {
            throw ApiException::unauthorized('stream ticket 无效、已过期或已使用');
        }
        return $this->respond($result);
    }

    public function notificationsSince()
    {
        $data = $this->validated();
        $cursor = isset($data['cursor']) && $data['cursor'] !== '' ? (string) $data['cursor'] : null;
        $limit = isset($data['limit']) ? (int) $data['limit'] : 50;
        return $this->respond(StreamTickets::poll((int) $data['uid'], $cursor, $limit));
    }
}
