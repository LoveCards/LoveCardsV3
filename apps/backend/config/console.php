<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
        'lc:eventing:work' => app\command\EventingWorker::class,
        'lc:system:migrate' => app\command\SystemMigrate::class,
        'lc:runtime:status' => app\command\RuntimeStatus::class,
        'lc:scheduler:run-once' => app\command\SchedulerRunOnce::class,
        'lc:system:upgrade' => app\command\SystemUpgrade::class,
        'lc:release:build' => app\command\BuildRelease::class,
        'lc:guest:prune' => app\command\GuestPrune::class,
    ],
];
