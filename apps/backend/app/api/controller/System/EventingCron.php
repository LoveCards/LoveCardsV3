<?php
declare(strict_types=1);

namespace app\api\controller\System;

use app\api\ApiException;
use app\api\controller\BaseController;
use app\modules\Platform\Eventing\Public\EventConsumptionConflict;
use app\modules\Platform\Eventing\Public\ScheduledEventConsumption;
use app\modules\Platform\Eventing\Transport\Api\ScheduledRunResource;

/** 外部定时器适配器；不实现第二套消费逻辑。 */
final class EventingCron extends BaseController
{
    public function runOnce()
    {
        try {
            $claimed = app(ScheduledEventConsumption::class)->run('http');
        } catch (EventConsumptionConflict $exception) {
            throw new ApiException($exception->getMessage(), ApiException::CODE_CONFIG_CONFLICT,
                ['reason_code' => $exception->reasonCode()]);
        }
        return $this->respond(ScheduledRunResource::make(['claimed' => $claimed]))
            ->header(['Cache-Control' => 'no-store']);
    }
}
