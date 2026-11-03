<?php
declare(strict_types=1);

namespace app\shared\Observability;

interface LogContextProvider
{
    public function current(): LogContext;
}
