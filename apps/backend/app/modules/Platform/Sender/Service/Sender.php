<?php

namespace app\modules\Platform\Sender\Service;

use app\modules\Platform\Sender\Infrastructure\SenderFactory;
use app\modules\Platform\Sender\Contract\Message;
use app\modules\Platform\Sender\Contract\SendResult;

class Sender
{
    public static function codeAvailable(string $channelType): bool
    {
        try {
            $driver = SenderFactory::make(ChannelManager::getDefault($channelType));
        } catch (\app\api\ApiException) {
            return false;
        }
        return $driver::meta()['channelType'] === $channelType
            && in_array('code', $driver::supportedTypes(), true)
            && method_exists($driver, 'isConfigured') && $driver->isConfigured();
    }

    public static function code(
        string $channelType,
        string $to,
        string $code,
        int $expire = 5,
        ?string $driver = null
    ): SendResult {
        $message = Message::code($channelType, $to, $code, $expire);
        return self::dispatch($message, $driver);
    }

    public static function notify(
        string $channelType,
        string $to,
        string $template,
        array $vars = [],
        ?string $driver = null
    ): SendResult {
        $message = Message::notify($channelType, $to, $template, $vars);
        return self::dispatch($message, $driver);
    }

    private static function dispatch(Message $message, ?string $driverSlug): SendResult
    {
        if ($driverSlug) {
            $driver = SenderFactory::make($driverSlug);
        } else {
            $defaultSlug = ChannelManager::getDefault($message->channelType);
            $driver = SenderFactory::make($defaultSlug);
        }

        if ($driver::meta()['channelType'] !== $message->channelType || !in_array($message->type, $driver::supportedTypes(), true)) {
            return SendResult::fail(
                $message->channelType,
                "驱动不支持 {$message->type} 类型消息"
            );
        }

        return $driver->send($message);
    }
}
