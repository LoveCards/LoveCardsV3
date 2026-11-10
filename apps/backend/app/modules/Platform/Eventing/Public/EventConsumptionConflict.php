<?php
declare(strict_types=1);

namespace app\modules\Platform\Eventing\Public;

final class EventConsumptionConflict extends \RuntimeException
{
    public function __construct(private string $reasonCode, string $message)
    {
        parent::__construct($message);
    }

    public function reasonCode(): string
    {
        return $this->reasonCode;
    }
}
