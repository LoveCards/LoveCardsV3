<?php
declare(strict_types=1);

return [
    'facilities' => [
        ['name' => 'eventing.wakeup', 'mode' => env('LC_EVENTING_WAKEUP_MODE', 'auto'), 'configured' => (bool) env('LC_REDIS_HOST', false), 'provider' => 'redis', 'fallback' => 'mysql-poll'],
        ['name' => 'eventing.worker', 'mode' => env('LC_WORKER_MODE', 'auto'), 'configured' => (bool) env('LC_WORKER_ENABLED', false), 'provider' => 'php-worker', 'fallback' => 'request'],
        ['name' => 'realtime.sse', 'mode' => env('LC_SSE_MODE', 'auto'), 'configured' => true, 'provider' => 'fpm-sse', 'fallback' => 'refresh'],
    ],
];
