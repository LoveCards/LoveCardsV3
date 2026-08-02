<?php

namespace app\api\infrastructure\Sender\Contract;

interface SenderInterface
{
    public function send(Message $message): SendResult;

    public static function meta(): array;

    public static function supportedTypes(): array;
}
