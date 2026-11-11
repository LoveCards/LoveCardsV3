<?php
declare(strict_types=1);

namespace app\api\controller\Platform;

use app\api\ApiException;
use app\api\controller\BaseController;
use app\modules\Operations\Runtime\Public\StreamTickets;

/**
 * Realtime Gateway 的内部 ticket 消费端点（InternalTokenCheck 保护，非 JWT）。
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

}
