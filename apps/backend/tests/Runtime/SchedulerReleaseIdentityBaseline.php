<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

use app\modules\Operations\Runtime\Infrastructure\SchedulerRunProbe;

$latest = [
    'trigger_source' => 'cli', 'release_id' => 'old-release', 'outcome' => 'ok',
    'finished_at' => '2026-11-11 04:05:00', 'jobs_processed' => 3,
];
$cutoff = '2026-11-11 04:00:00';
$probe = SchedulerRunProbe::assessLatest($latest, 'cli', $cutoff, 'new-release');
if ($probe['ready'] !== false || $probe['reason_code'] !== 'scheduler-release-mismatch') {
    throw new RuntimeException('旧 Release cron 即使跨激活后完成也不能证明新版本运行');
}
$latest['release_id'] = 'new-release';
$probe = SchedulerRunProbe::assessLatest($latest, 'cli', $cutoff, 'new-release');
if ($probe['ready'] !== true || $probe['note']['release_id'] !== 'new-release') {
    throw new RuntimeException('目标 Release cron 成功运行应提供明确身份');
}
$latest['finished_at'] = '2026-11-11 03:59:00';
if (SchedulerRunProbe::assessLatest($latest, 'cli', $cutoff, 'new-release')['ready'] !== false) {
    throw new RuntimeException('目标 Release 的过期记录不能代表当前就绪');
}
fwrite(STDOUT, "PASS scheduled consumer release identity baseline\n");
