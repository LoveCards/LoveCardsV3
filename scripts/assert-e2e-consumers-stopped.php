<?php
declare(strict_types=1);

// 仅供隔离升级 E2E 使用；真实部署须由进程托管器停机并确认退出。
if (getenv('LOVECARDS_E2E_ISOLATED') !== '1'
    || !preg_match('/^lovecards_e2e_[a-z0-9_]+$/', (string) getenv('DATABASE_DATABASE'))
    || !in_array((string) getenv('DATABASE_HOSTNAME'), ['127.0.0.1', 'localhost', '::1'], true)) {
    fwrite(STDERR, "Refusing non-isolated drain fixture\n");
    exit(2);
}
$dsn = 'mysql:host=' . getenv('DATABASE_HOSTNAME') . ';port=' . getenv('DATABASE_HOSTPORT')
    . ';dbname=' . getenv('DATABASE_DATABASE') . ';charset=utf8mb4';
$pdo = new PDO($dsn, (string) getenv('DATABASE_USERNAME'), (string) getenv('DATABASE_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach (["SELECT COUNT(*) FROM event_deliveries WHERE status = 'processing'",
    "SELECT COUNT(*) FROM scheduler_runs WHERE outcome = 'running'",
    "SELECT COUNT(*) FROM runtime_workers WHERE worker_type = 'eventing' AND state <> 'stopped'"] as $query) {
    if ((int) $pdo->query($query)->fetchColumn() !== 0) {
        fwrite(STDERR, "Isolated consumer still active\n");
        exit(1);
    }
}
echo json_encode(['workers_stopped' => true, 'scheduled_trigger_stopped' => true, 'restart_disabled' => true], JSON_THROW_ON_ERROR), PHP_EOL;
