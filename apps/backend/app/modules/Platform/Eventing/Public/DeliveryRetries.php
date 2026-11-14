<?php
declare(strict_types=1);

namespace app\modules\Platform\Eventing\Public;

use app\modules\Platform\Eventing\Infrastructure\ThinkDbDeliveryRepository;

/** 人工重试的唯一写入入口；一次授权只允许新增一次尝试。 */
final class DeliveryRetries
{
    /** @param callable(array,array):void $audit 与授权写入同事务执行。 */
    public static function retry(int $deliveryId, callable $audit): bool
    {
        return (new ThinkDbDeliveryRepository())->retryDead($deliveryId, $audit);
    }
}
