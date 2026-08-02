<?php

namespace app\common\contract {
    require_once dirname(__DIR__, 2) . '/modules/Platform/Auth/Contract/TokenService.php';

    /** @deprecated use app\modules\Platform\Auth\Contract\TokenService. */
    interface TokenService extends \app\modules\Platform\Auth\Contract\TokenService {}
}
