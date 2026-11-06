<?php
declare(strict_types=1);

return [
    'facilities' => [
        ['name' => 'eventing.wakeup', 'mode' => env('LC_EVENTING_WAKEUP_MODE', 'auto'), 'configured' => (bool) env('LC_REDIS_HOST', false), 'provider' => 'redis', 'fallback' => 'mysql-poll'],
        ['name' => 'eventing.worker', 'mode' => env('LC_WORKER_MODE', 'auto'), 'configured' => (bool) env('LC_WORKER_ENABLED', false), 'provider' => 'php-worker', 'fallback' => 'request'],
        ['name' => 'realtime.gateway', 'mode' => env('LC_REALTIME_MODE', 'auto'), 'configured' => (bool) env('LC_REALTIME_GATEWAY_URL', false), 'provider' => 'external-gateway', 'fallback' => 'polling'],
    ],
];
