<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Infrastructure;

use app\modules\Platform\Eventing\Contract\FailureSanitizer;

final class ConservativeFailureSanitizer implements FailureSanitizer
{
    public function sanitize(\Throwable $exception): string
    {
        $message = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $exception->getMessage()) ?: 'handler failed';
        $message = preg_replace(
            '/\b(password|token|secret|authorization|cookie)\b\s*[:=]\s*[^\s,;]+/i',
            '$1=[REDACTED]',
            $message
        ) ?: 'handler failed';
        $class = str_replace("\0", '', get_class($exception));

        return substr($class . ': ' . trim($message), 0, 2048);
    }
}
