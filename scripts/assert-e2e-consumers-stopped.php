<?php
declare(strict_types=1);

// 仅供隔离升级 E2E 使用；真实部署须由进程托管器停机并确认退出。
if (getenv('LOVECARDS_E2E_ISOLATED') !== '1'
    || !preg_match('/^lovecards_e2e_[a-z0-9_]+$/', (string) getenv('DATABASE_DATABASE'))
    || !in_array((string) getenv('DATABASE_HOSTNAME'), ['127.0.0.1', 'localhost', '::1'], true)) {
    fwrite(STDERR, "Refusing non-isolated drain fixture\n");
    exit(2);
}
$phase = (string) ($argv[1] ?? '');
// The reviewed compatibility Release predates phase substitution. During
// R2 activation it passes this placeholder literally; only the new Release
// may supply an actual contract phase and prove Web requests are quiesced.
if ($phase === '{phase}') $phase = 'activation';
if (!in_array($phase, ['activation', 'contract'], true)) {
    fwrite(STDERR, "Invalid isolated drain phase\n");
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
$proof = ['workers_stopped' => true, 'scheduled_trigger_stopped' => true, 'restart_disabled' => true];
// 隔离 E2E 没有外部 Web 流量；此证明仅用于覆盖 Contract 门禁协议。
if ($phase === 'contract') $proof['web_requests_quiesced'] = true;
echo json_encode($proof, JSON_THROW_ON_ERROR), PHP_EOL;
