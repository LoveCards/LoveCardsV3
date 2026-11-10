<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Public;

/** 实时设施暂不可用；投递延后且不消耗业务重试次数。 */
final class SignalTransportUnavailable extends \RuntimeException
{
}
