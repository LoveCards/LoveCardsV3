<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use app\modules\Operations\SystemLifecycle\Domain\ScheduledUpgradeProof;

$now = new DateTimeImmutable('2026-11-20T06:30:00Z');
$state = ['state' => 'active', 'mode' => 'scheduled', 'scheduled_trigger' => 'cli'];
$success = [
    'run_id' => 10, 'trigger_source' => 'cli', 'release_id' => 'release-old',
    'outcome' => 'ok', 'finished_at' => '2026-11-20 06:29:00',
];
$paused = [
    'run_id' => 11, 'worker_id' => 'upgrade-pause-plan-1', 'trigger_source' => 'cli',
    'release_id' => 'release-old', 'outcome' => 'paused', 'finished_at' => '2026-11-20 06:29:30',
];
$reject = static function (callable $action, string $label): void {
    try { $action(); } catch (LogicException) { return; }
    throw new RuntimeException($label . ' must reject');
};
$proof = ScheduledUpgradeProof::capture($state, $success, 'release-old', $now);
ScheduledUpgradeProof::assertForActivation($state, $paused, $proof, 'release-old', 'plan-1', $now);
$reject(static fn() => ScheduledUpgradeProof::capture($state, array_replace($success, ['outcome' => 'failed']), 'release-old', $now), 'failed source run');
$reject(static fn() => ScheduledUpgradeProof::capture($state, array_replace($success, ['release_id' => 'other']), 'release-old', $now), 'wrong source release');
$reject(static fn() => ScheduledUpgradeProof::assertForActivation($state, array_replace($paused, ['outcome' => 'failed']), $proof, 'release-old', 'plan-1', $now), 'failed pause');
$reject(static fn() => ScheduledUpgradeProof::assertForActivation($state, array_replace($paused, ['worker_id' => 'upgrade-pause-other']), $proof, 'release-old', 'plan-1', $now), 'wrong plan');
$reject(static fn() => ScheduledUpgradeProof::assertForActivation($state, array_replace($paused, ['finished_at' => '2026-11-20 05:00:00']), $proof, 'release-old', 'plan-1', $now), 'stale pause');
$reject(static fn() => ScheduledUpgradeProof::assertForActivation(array_replace($state, ['scheduled_trigger' => 'http']), $paused, $proof, 'release-old', 'plan-1', $now), 'changed trigger');
$unmanagedProof = ScheduledUpgradeProof::capture($state, array_replace($success, ['release_id' => 'legacy']), null, $now);
ScheduledUpgradeProof::assertForActivation($state, array_replace($paused, ['release_id' => '']), $unmanagedProof, null, 'plan-1', $now);
fwrite(STDOUT, "PASS scheduled upgrade proof baseline\n");
