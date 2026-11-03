<?php

declare(strict_types=1);

namespace app\modules\Operations\Runtime\Public;

interface WorkerHeartbeatStore
{
    public function record(WorkerHeartbeat $heartbeat): void;
}
