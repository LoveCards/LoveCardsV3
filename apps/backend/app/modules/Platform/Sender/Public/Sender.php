<?php

namespace app\modules\Platform\Sender\Public;

use app\modules\Platform\Sender\Service\Sender as SenderService;
use app\modules\Platform\Sender\Contract\SendResult;

final class Sender
{
    public static function notify(string $channelType, string $to, string $template, array $vars = [], ?string $driver = null): SendResult { return SenderService::notify($channelType, $to, $template, $vars, $driver); }
    public static function code(string $channelType, string $to, string $code, int $expire = 5, ?string $driver = null): SendResult { return SenderService::code($channelType, $to, $code, $expire, $driver); }
}
