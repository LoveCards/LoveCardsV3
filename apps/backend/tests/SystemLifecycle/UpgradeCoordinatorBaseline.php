<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

use app\modules\Operations\SystemLifecycle\Application\UpgradeCoordinator;
use app\modules\Operations\SystemLifecycle\Contract\ReleaseCompatibility;
use app\modules\Operations\SystemLifecycle\Contract\ReleaseEnvironment;
use app\modules\Operations\SystemLifecycle\Contract\ReleaseMigrationExecutor;
use app\modules\Operations\SystemLifecycle\Contract\ReleasePackageImporter;
use app\modules\Operations\SystemLifecycle\Contract\ReleasePackageStore;
use app\modules\Operations\SystemLifecycle\Contract\ReleasePointer;
use app\modules\Operations\SystemLifecycle\Contract\ReleaseRepository;
use app\modules\Operations\SystemLifecycle\Contract\ReleaseRuntime;
use app\modules\Operations\SystemLifecycle\Contract\SystemBackupRepository;
use app\modules\Operations\SystemLifecycle\Contract\UpgradeBackupGate;
use app\modules\Operations\SystemLifecycle\Contract\UpgradeLease;
use app\modules\Operations\SystemLifecycle\Contract\UpgradePlanRepository;
use app\modules\Operations\SystemLifecycle\Domain\ReleaseManifest;
use app\modules\Operations\SystemLifecycle\Domain\MigrationPhase;
use app\modules\Operations\SystemLifecycle\Domain\StoredReleasePackage;
use app\modules\Operations\SystemLifecycle\Domain\StoredUpgradePlan;
use app\modules\Operations\SystemLifecycle\Domain\UpgradePlan;
use app\modules\Operations\SystemLifecycle\Domain\UpgradeStep;
use app\modules\Operations\SystemLifecycle\Domain\UpgradeStepState;
use app\modules\Operations\SystemLifecycle\Domain\VerifiedReleaseManifest;
use app\shared\SystemPaths\SystemPaths;

final class UpgradeCoordinatorFakeLease implements UpgradeLease
{
    public function acquire(string $leaseName, DateTimeImmutable $now, int $ttlSeconds): ?string { return 'upgrade-test-holder'; }
    public function renew(string $leaseName, string $holderToken, DateTimeImmutable $now, int $ttlSeconds): bool { return true; }
    public function release(string $leaseName, string $holderToken): void {}
}

final class UpgradeCoordinatorFakePackageStore implements ReleasePackageStore
{
    public function __construct(private string $packagePath) {}
    public function store(string $planId, string $sourcePath): StoredReleasePackage
    {
        if (!copy($sourcePath, $this->packagePath)) throw new RuntimeException('fake package copy failed');
        return new StoredReleasePackage($this->packagePath, hash_file('sha256', $this->packagePath), (int) filesize($this->packagePath));
    }
    public function remove(string $planId): void { @unlink($this->packagePath); }
}

final class UpgradeCoordinatorFakeImporter implements ReleasePackageImporter
{
    public function __construct(private ReleaseManifest $manifest) {}
    public function inspect(string $zipPath): ReleaseManifest { return $this->manifest; }
    public function verify(string $zipPath, int $currentSequence = 0, ?DateTimeImmutable $now = null, ?string $expectedPackageChecksum = null): VerifiedReleaseManifest
    { return new VerifiedReleaseManifest($this->manifest, $now ?? new DateTimeImmutable('now', new DateTimeZone('UTC'))); }
    public function import(string $zipPath, int $currentSequence = 0, ?DateTimeImmutable $now = null, ?string $expectedPackageChecksum = null): VerifiedReleaseManifest
    { return $this->verify($zipPath, $currentSequence, $now, $expectedPackageChecksum); }
}

final class UpgradeCoordinatorFakeReleaseRepository implements ReleaseRepository
{
    public array $staged = [];
    public array $active = [];
    public function sequenceFloor(?string $excludingReleaseId = null): int { return 0; }
    public function find(string $releaseId): ?array { return $this->staged[$releaseId] ?? null; }
    public function stage(VerifiedReleaseManifest $release): void { $this->staged[$release->manifest()->releaseId()] = ['status' => 'staged']; }
    public function activate(string $releaseId, ?string $previousReleaseId): void { $this->active = [$releaseId, $previousReleaseId]; }
    public function rollback(string $releaseId, ?string $restoredReleaseId): void { $this->active = [$restoredReleaseId, $releaseId]; }
}

final class UpgradeCoordinatorFakeCompatibility implements ReleaseCompatibility
{
    public function __construct(private array &$trace, private bool $contractRequired = true) {}
    public int $environmentChecks = 0;
    public int $stagedChecks = 0;
    public int $preContractChecks = 0;
    public int $migrationChecks = 0;
    public bool $failEventConsumption = false;
    public bool $failEventConsumerOperational = false;
    public function verifyEnvironment(ReleaseManifest $manifest): void { $this->environmentChecks++; }
    public function verifyStagedRelease(ReleaseManifest $manifest, string $releaseRoot): void { $this->stagedChecks++; $this->trace[] = 'schema_check'; }
    public function verifyRuntimeHandlerCatalog(ReleaseManifest $manifest): void { $this->trace[] = 'runtime_catalog'; }
    public function requiresContract(ReleaseManifest $manifest, string $releaseRoot): bool { return $this->contractRequired; }
    public function verifyPreContractMigrationsComplete(ReleaseManifest $manifest): void { $this->preContractChecks++; $this->trace[] = 'pre_contract_verified'; }
    public function verifyMigrationsComplete(ReleaseManifest $manifest): void { $this->migrationChecks++; $this->trace[] = 'contract_verified'; }
    public function verifyEventConsumptionReady(): void
    {
        $this->trace[] = 'event_consumer_ready';
        if ($this->failEventConsumption) throw new \LogicException('event consumer has no verified trigger');
    }
    public function verifyEventConsumptionOperational(string $activatedAt, string $releaseId): void
    {
        $this->trace[] = 'event_consumer_operational';
        if ($this->failEventConsumerOperational) throw new \LogicException('event consumer unavailable after activation');
    }
}

final class UpgradeCoordinatorFakeBackupGate implements UpgradeBackupGate
{
    public function __construct(private array &$trace) {}
    public int $prepareCalls = 0;
    public int $assertCalls = 0;
    public function prepare(string $planId, ?int $actorId = null): array { $this->prepareCalls++; $this->trace[] = 'backup'; return [['backup_id' => $planId . ':database', 'backup_type' => 'database', 'status' => 'verified']]; }
    public function assertReady(string $planId): void { $this->assertCalls++; }
    public function pointerBackup(string $planId): array { return ['backup_id' => $planId . ':pointer', 'location' => __FILE__, 'checksum' => hash_file('sha256', __FILE__)]; }
}

final class UpgradeCoordinatorFakeBackupRepository implements SystemBackupRepository
{
    public function reserve(string $backupId, ?string $planId, string $type, ?int $actorId): bool { return true; }
    public function complete(app\modules\Operations\SystemLifecycle\Domain\BackupArtifact $artifact, ?string $planId, ?int $actorId): void {}
    public function fail(string $backupId, string $failureCode): void {}
    public function forPlan(string $planId): array { return []; }
    public function markRestored(string $backupId): void {}
}

final class UpgradeCoordinatorFakeMigrations implements ReleaseMigrationExecutor
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $units;
    /** @var array<int, array{string, bool, string}> */
    public array $calls = [];
    public bool $failNextExpand = false;
    public bool $expandFailed = false;
    public array $retryRequests = [];

    public function __construct(private array &$trace)
    {
        $this->units = [
            MigrationPhase::PREFLIGHT => [['id' => '20260819000003', 'phase' => MigrationPhase::EXPAND]],
            MigrationPhase::EXPAND => [['id' => '20260819000003', 'phase' => MigrationPhase::BACKFILL]],
            MigrationPhase::BACKFILL => [
                ['id' => '20260819000003', 'phase' => MigrationPhase::BACKFILL, 'cursor' => 'batch-1'],
                ['id' => '20260819000003', 'phase' => MigrationPhase::VERIFY],
            ],
            MigrationPhase::VERIFY => [['id' => '20260819000003', 'phase' => MigrationPhase::CONTRACT]],
            MigrationPhase::CONTRACT => [['id' => '20260819000003', 'phase' => MigrationPhase::COMPLETE]],
        ];
    }

    public function advancePhase(
        string $directory,
        string $phase,
        bool $allowContract,
        string $lifecycleLeaseHolder,
        bool $retryFailed = false
    ): ?array {
        $this->calls[] = [$phase, $allowContract, $lifecycleLeaseHolder];
        $this->retryRequests[] = $retryFailed;
        if ($phase === MigrationPhase::EXPAND && $this->failNextExpand) {
            $this->failNextExpand = false;
            $this->expandFailed = true;
            throw new LogicException('fixture migration failed');
        }
        if ($phase === MigrationPhase::EXPAND && $this->expandFailed) {
            if (!$retryFailed) return ['id' => '20260819000003', 'phase' => $phase, 'status' => 'failed'];
            $this->expandFailed = false;
        }
        $row = array_shift($this->units[$phase]);
        if ($row === null) return null;
        $this->trace[] = 'migration:' . $phase;
        return $row;
    }

    public function inspectPhase(string $directory, string $phase): ?array
    {
        if (($this->units[$phase] ?? []) === [] && !($phase === MigrationPhase::EXPAND && $this->expandFailed)) return null;
        return ['id' => '20260819000003', 'checksum' => str_repeat('a', 64), 'phase' => $phase,
            'status' => $phase === MigrationPhase::EXPAND && $this->expandFailed ? 'failed' : 'pending',
            'cursor' => null, 'attempts' => $this->expandFailed ? 2 : 0, 'revision' => 3];
    }
}

final class UpgradeCoordinatorFakePointer implements ReleasePointer
{
    public function __construct(private array &$trace) {}
    public ?string $active = 'release-old-001';
    public function activeReleaseId(): ?string { return $this->active; }
    public function activate(string $releaseId): void { $this->trace[] = 'activate'; $this->active = $releaseId; }
    public function restore(string $backupPath, string $expectedChecksum): ?string { $this->trace[] = 'restore'; $this->active = 'release-old-001'; return $this->active; }
    public function recoveryState(): array { return ['active_release_id' => $this->active]; }
}

final class UpgradeCoordinatorFakeRuntime implements ReleaseRuntime
{
    public function __construct(private array &$trace) {}
    public bool $failHealth = false;
    public bool $failQuiescence = false;
    public function assertQuiescent(string $releaseId): void { if ($this->failQuiescence) throw new LogicException('consumer still running'); $this->trace[] = 'quiescent'; }
    public function canDrain(): bool { return true; }
    public function drain(string $releaseId): void { $this->trace[] = 'drain'; }
    public function canReload(): bool { return true; }
    public function reload(string $releaseId): void { $this->trace[] = 'reload:' . $releaseId; }
    public function healthCheck(string $releaseId): void
    {
        $this->trace[] = 'health:' . $releaseId;
        if ($this->failHealth) throw new LogicException('fixture health failure');
    }
}

final class UpgradeCoordinatorFakePlanRepository implements UpgradePlanRepository
{
    /** @var array<string, StoredUpgradePlan> */
    private array $rows = [];
    public function create(StoredUpgradePlan $plan): StoredUpgradePlan { $this->rows[$plan->plan()->planId()] = $plan; return $plan; }
    public function find(string $planId): ?StoredUpgradePlan { return $this->rows[$planId] ?? null; }
    public function recent(int $limit = 50): array { return array_values($this->rows); }
    public function unsettled(bool $lock = false): ?StoredUpgradePlan { foreach ($this->recent() as $stored) if (!in_array($stored->status(), [UpgradePlan::COMPLETED, StoredUpgradePlan::RECOVERED], true)) return $stored; return null; }
    public function existsUnsettled(?string $excludingPlanId = null): bool
    {
        foreach ($this->rows as $planId => $stored) {
            if ($excludingPlanId !== null && $planId === $excludingPlanId) continue;
            if (!in_array($stored->status(), [UpgradePlan::COMPLETED, StoredUpgradePlan::RECOVERED], true)) return true;
        }
        return false;
    }
    public function save(StoredUpgradePlan $plan, int $expectedRevision): StoredUpgradePlan
    {
        $current = $this->rows[$plan->plan()->planId()] ?? null;
        if ($current === null || $current->plan()->revision() !== $expectedRevision || $plan->plan()->revision() !== $expectedRevision) throw new RuntimeException('fake plan CAS conflict');
        $saved = $plan->withPlan($plan->plan()->withRevision($expectedRevision + 1));
        $this->rows[$plan->plan()->planId()] = $saved;
        return $saved;
    }
}

$manifestData = [
    'schema_version' => 1, 'release_id' => 'release-new-001', 'version' => '1.2.3', 'build' => 'build-1', 'channel' => 'stable', 'sequence' => 2,
    'issued_at' => '2026-08-19T00:00:00Z', 'expires_at' => '2026-08-20T00:00:00Z', 'minimum_php' => '8.1', 'minimum_mysql' => '5.7',
    'required_extensions' => [], 'optional_facilities' => [], 'source_schema_range' => ['min' => '0', 'max' => '20260819000003'], 'target_schema' => '20260819000003',
    'migration_checksums' => ['20260819000003' => str_repeat('a', 64)], 'handler_catalog_hash' => str_repeat('b', 64),
    'file_hashes' => ['app.txt' => str_repeat('c', 64)], 'signature_key_id' => 'test-key', 'signature' => base64_encode('fake-signature'),
];
$manifest = ReleaseManifest::fromArray($manifestData);
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lc-upgrade-coordinator-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700, true);
$source = $tmp . DIRECTORY_SEPARATOR . 'source.zip';
file_put_contents($source, 'fake package');
$paths = new SystemPaths($tmp . DIRECTORY_SEPARATOR . 'instance', $tmp . DIRECTORY_SEPARATOR . 'code');
@mkdir($paths->upgradeRoot(), 0700, true);
$packagePath = $paths->upgradeRoot() . DIRECTORY_SEPARATOR . 'package.zip';
$fixture = static function (bool $contractRequired = true) use ($packagePath, $manifest, $paths): object {
    $state = new stdClass();
    $state->trace = [];
    $state->plans = new UpgradeCoordinatorFakePlanRepository();
    $state->compatibility = new UpgradeCoordinatorFakeCompatibility($state->trace, $contractRequired);
    $state->backups = new UpgradeCoordinatorFakeBackupGate($state->trace);
    $state->migrations = new UpgradeCoordinatorFakeMigrations($state->trace);
    $state->pointer = new UpgradeCoordinatorFakePointer($state->trace);
    $state->releases = new UpgradeCoordinatorFakeReleaseRepository();
    $state->runtime = new UpgradeCoordinatorFakeRuntime($state->trace);
    $state->coordinator = new UpgradeCoordinator(
        $state->plans,
        new UpgradeCoordinatorFakeLease(),
        new UpgradeCoordinatorFakePackageStore($packagePath),
        new UpgradeCoordinatorFakeImporter($manifest),
        $state->releases,
        $state->compatibility,
        $state->backups,
        new UpgradeCoordinatorFakeBackupRepository(),
        $state->migrations,
        $state->pointer,
        $state->runtime,
        $paths
    );
    return $state;
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$assertThrows = static function (callable $callback, string $message): void {
    try { $callback(); }
    catch (LogicException $exception) {
        if (!str_contains($exception->getMessage(), $message)) {
            throw new RuntimeException('unexpected exception message: ' . $exception->getMessage());
        }
        return;
    }
    throw new RuntimeException('expected LogicException containing: ' . $message);
};
$migrationTraceCount = static fn(array $trace): int => count(array_filter(
    $trace,
    static fn(string $entry): bool => str_starts_with($entry, 'migration:')
));
$tests = [];
$tests['running plan with failed ledger stops once before explicit retry'] = static function () use ($fixture, $source, $assert, $assertThrows): void {
    $state = $fixture();
    $plan = $state->coordinator->createPlan($source, 7);
    $id = $plan->plan()->planId();
    while ($plan->plan()->current()?->name() !== UpgradeStep::EXPAND_MIGRATIONS) $plan = $state->coordinator->advance($id);
    $state->plans->save($plan->withPlan($plan->plan()->start(UpgradeStep::EXPAND_MIGRATIONS)), $plan->plan()->revision());
    $state->migrations->expandFailed = true;
    $assertThrows(static fn() => $state->coordinator->advance($id), '当前迁移已失败');
    $assert(end($state->migrations->retryRequests) === false, 'running plan cannot authorize ledger retry');
    $assert($state->coordinator->find($id)->plan()->current()?->state() === UpgradeStepState::FAILED, 'failed ledger must settle the plan as failed');
    $state->coordinator->advance($id);
    $assert(end($state->migrations->retryRequests) === true, 'second explicit advance must authorize retry');
};
$tests['audit captures the exact failed migration before changing either state'] = static function () use ($fixture, $source, $assert, $assertThrows): void {
    $state = $fixture();
    $plan = $state->coordinator->createPlan($source, 7);
    $id = $plan->plan()->planId();
    while ($plan->plan()->current()?->name() !== UpgradeStep::EXPAND_MIGRATIONS) $plan = $state->coordinator->advance($id);
    $state->migrations->failNextExpand = true;
    $assertThrows(static fn() => $state->coordinator->advance($id), 'fixture migration failed');
    $before = $state->coordinator->find($id)->toArray();
    $calls = count($state->migrations->calls);
    $captured = null;
    $audit = static function (array $metadata) use (&$captured): void { $captured = $metadata; throw new LogicException('fixture audit unavailable'); };
    $assertThrows(static fn() => $state->coordinator->advance($id, $audit), 'fixture audit unavailable');
    $assert($state->coordinator->find($id)->toArray() === $before, 'failed audit changed the plan');
    $assert(count($state->migrations->calls) === $calls && $state->migrations->expandFailed, 'failed audit reset or ran the migration');
    $assert(($captured['plan_id'] ?? null) === $id && ($captured['revision'] ?? null) === $before['revision'], 'audit must capture the original plan identity');
    $assert(($captured['step']['name'] ?? null) === UpgradeStep::EXPAND_MIGRATIONS && ($captured['step']['state'] ?? null) === 'failed', 'audit must capture original step');
    $assert(($captured['migration']['id'] ?? null) === '20260819000003'
        && ($captured['migration']['checksum'] ?? null) === str_repeat('a', 64)
        && ($captured['migration']['status'] ?? null) === 'failed'
        && ($captured['retry_failed_migration'] ?? false), 'audit must capture exact retry identity');
    $state->coordinator->advance($id, static function (array $metadata) use ($captured, $assert): void {
        $assert($metadata === $captured, 'retry snapshot changed without any mutation');
    });
    $assert(!$state->migrations->expandFailed, 'successful audit must permit one retry');
};
$tests['explicit advance retries a failed migration once using the existing plan lease'] = static function () use ($fixture, $source, $assert, $assertThrows): void {
    $state = $fixture();
    $plan = $state->coordinator->createPlan($source, 7);
    $planId = $plan->plan()->planId();
    for ($i = 0; $i < 30 && $plan->plan()->current()?->name() !== UpgradeStep::EXPAND_MIGRATIONS; $i++) {
        $plan = $state->coordinator->advance($planId);
    }
    $state->migrations->failNextExpand = true;
    $assertThrows(static fn() => $state->coordinator->advance($planId), 'fixture migration failed');
    $assert($state->coordinator->find($planId)->plan()->status() === UpgradePlan::FAILED, 'migration failure must stop the plan');
    $beforeCalls = count($state->migrations->calls);
    $plan = $state->coordinator->advance($planId);
    $assert(end($state->migrations->retryRequests) === true, 'explicit retry must reach the migration ledger');
    $assert(count($state->migrations->calls) === $beforeCalls + 1, 'retry must execute one work unit');
    $assert(($plan->context()['last_migration_advance']['phase'] ?? null) === MigrationPhase::BACKFILL, 'retry must advance the failed migration');
    $assert($plan->plan()->current()?->attempts() === 2, 'step retry must retain attempts');
    $state->coordinator->advance($planId);
    $assert(end($state->migrations->retryRequests) === false, 'retry authority must not leak into following advances');
};
$tests['each HTTP advance executes at most one migration unit and contract runs after activation health'] = static function () use (
    $fixture, $source, $assert, $assertThrows, $migrationTraceCount
): void {
    $state = $fixture();
    $plan = $state->coordinator->createPlan($source, 7);
    $planId = $plan->plan()->planId();
    $assertThrows(static fn() => $state->coordinator->approveContract($planId), '正在等待 Contract');

    for ($advance = 0; $advance < 100; $advance++) {
        $before = $migrationTraceCount($state->trace);
        $plan = $state->coordinator->advance($planId);
        $after = $migrationTraceCount($state->trace);
        $assert($after - $before <= 1, 'one advance executed more than one migration unit');
        if ($plan->plan()->status() === UpgradePlan::WAITING) break;
    }

    $current = $plan->plan()->current();
    $assert($plan->plan()->status() === UpgradePlan::WAITING, 'contract plan did not wait for approval');
    $assert($current?->name() === UpgradeStep::CONTRACT_MIGRATIONS, 'approval gate is not the contract_migrations step');
    $assert($current?->state() === UpgradeStepState::WAITING, 'contract step is not WAITING');
    $assert(!$plan->contractApproved(), 'contract was approved before the explicit action');
    $assert(!in_array('migration:' . MigrationPhase::CONTRACT, $state->trace, true), 'contract executed before approval');

    $preContract = array_search('pre_contract_verified', $state->trace, true);
    $backup = array_search('backup', $state->trace, true);
    $activate = array_search('activate', $state->trace, true);
    $reload = array_search('reload:release-new-001', $state->trace, true);
    $health = array_search('health:release-new-001', $state->trace, true);
    $firstMigration = null;
    foreach ($state->trace as $traceIndex => $traceEntry) {
        if (str_starts_with($traceEntry, 'migration:')) {
            $firstMigration = $traceIndex;
            break;
        }
    }
    $assert(is_int($preContract) && is_int($backup) && $backup < $preContract, 'pre-contract verification did not follow the backup gate');
    $assert(is_int($firstMigration) && is_int($backup) && $backup < $firstMigration, 'a migration ran before the backup gate');
    $assert(is_int($activate) && $backup < $activate, 'release activated before backup gate');
    $assert(is_int($reload) && $activate < $reload, 'runtime reloaded before release activation');
    $assert(is_int($health) && $reload < $health, 'health check did not follow runtime reload');

    $revision = $plan->plan()->revision();
    $stillWaiting = $state->coordinator->advance($planId);
    $assert($stillWaiting->plan()->revision() === $revision, 'waiting plan churned without approval');
    $approved = $state->coordinator->approveContract($planId);
    $assert($approved->contractApproved(), 'explicit contract approval was not persisted');

    for ($advance = 0; $advance < 20; $advance++) {
        $before = $migrationTraceCount($state->trace);
        $plan = $state->coordinator->advance($planId);
        $after = $migrationTraceCount($state->trace);
        $assert($after - $before <= 1, 'contract advance executed more than one migration unit');
        if ($plan->status() === UpgradePlan::COMPLETED) break;
    }
    $assert($plan->status() === UpgradePlan::COMPLETED, 'approved plan did not complete');
    $contract = array_search('migration:' . MigrationPhase::CONTRACT, $state->trace, true);
    $assert(is_int($contract) && $health < $contract, 'contract ran before the activated release passed health checks');
    foreach ($state->migrations->calls as $call) {
        $assert($call[2] === 'upgrade-test-holder', 'migration executor did not reuse the outer lifecycle lease holder');
    }
    $assert($state->releases->active === ['release-new-001', 'release-old-001'], 'release activation ledger mismatch');
    $assert($state->pointer->active === 'release-new-001', 'release pointer was not activated');
    $assertThrows(static fn() => $state->coordinator->recover($planId), 'Contract Migration 已开始');
};

$tests['recovery is one-shot, only after activation and before contract work'] = static function () use (
    $fixture, $source, $assert, $assertThrows
): void {
    $state = $fixture();
    $plan = $state->coordinator->createPlan($source, 7);
    $planId = $plan->plan()->planId();
    $assertThrows(static fn() => $state->coordinator->recover($planId), '尚未激活');
    $waiting = $state->coordinator->runUntilSettled($planId);
    $assert($waiting->plan()->status() === UpgradePlan::WAITING, 'fixture did not reach the contract gate');
    $recovered = $state->coordinator->recover($planId);
    $assert($recovered->status() === StoredUpgradePlan::RECOVERED, 'recovery status was not persisted');
    $assert(($recovered->toArray()['status'] ?? null) === StoredUpgradePlan::RECOVERED, 'public recovery status is incorrect');
    $assert($state->pointer->active === 'release-old-001', 'recovery did not restore the previous pointer');
    $assert(in_array('reload:release-old-001', $state->trace, true), 'recovery did not reload the restored release');
    $assert(in_array('health:release-old-001', $state->trace, true), 'recovery did not health-check the restored release');
    $assertThrows(static fn() => $state->coordinator->advance($planId), '不能继续推进');
    $assertThrows(static fn() => $state->coordinator->recover($planId), '不能重复执行');
};

$tests['health-check failure persists a failed upgrade step'] = static function () use (
    $fixture, $source, $assert, $assertThrows
): void {
    $state = $fixture();
    $state->runtime->failHealth = true;
    $plan = $state->coordinator->createPlan($source, 7);
    $planId = $plan->plan()->planId();
    $assertThrows(static fn() => $state->coordinator->runUntilSettled($planId, true), 'fixture health failure');
    $failed = $state->coordinator->find($planId);
    $assert($failed !== null && $failed->plan()->status() === UpgradePlan::FAILED, 'health failure was not persisted as failed plan');
    $current = $failed?->plan()->current();
    $assert($current?->name() === UpgradeStep::HEALTH_CHECK, 'health failure did not remain on health_check step');
    $assert($current?->state() === UpgradeStepState::FAILED, 'health failure step is not FAILED');
    $assert($current?->failureCode() === 'logicexception', 'health failure code was not persisted');
};

$tests['event consumer gate fails before release pointer activation'] = static function () use (
    $fixture, $source, $assert, $assertThrows
): void {
    $state = $fixture(false);
    $state->compatibility->failEventConsumption = true;
    $plan = $state->coordinator->createPlan($source, 7);
    $planId = $plan->plan()->planId();
    $assertThrows(static fn() => $state->coordinator->runUntilSettled($planId), 'event consumer has no verified trigger');
    $failed = $state->coordinator->find($planId);
    $assert($failed !== null && $failed->plan()->status() === UpgradePlan::FAILED, 'event consumer gate failure was not persisted');
    $assert($failed->plan()->current()?->name() === UpgradeStep::ACTIVATE_RELEASE, 'event consumer gate did not stop activation');
    $assert($state->pointer->active === 'release-old-001', 'pointer changed before event consumer gate passed');
    $assert(!in_array('activate', $state->trace, true), 'activation occurred after the event consumer gate failed');
};

$tests['event consumer health failure cannot complete upgrade'] = static function () use (
    $fixture, $source, $assert, $assertThrows
): void {
    $state = $fixture(false);
    $state->compatibility->failEventConsumerOperational = true;
    $plan = $state->coordinator->createPlan($source, 7);
    $planId = $plan->plan()->planId();
    $assertThrows(static fn() => $state->coordinator->runUntilSettled($planId), 'event consumer unavailable after activation');
    $failed = $state->coordinator->find($planId);
    $assert($failed !== null && $failed->plan()->status() === UpgradePlan::FAILED, 'consumer health failure was not persisted');
    $assert($failed->plan()->current()?->name() === UpgradeStep::HEALTH_CHECK, 'consumer health did not stop health_check');
    $assert($state->pointer->active === 'release-new-001', 'consumer health should run after pointer activation');
};

$tests['run contract flag is applied only after the explicit waiting gate'] = static function () use (
    $fixture, $source, $assert
): void {
    $state = $fixture();
    $plan = $state->coordinator->createPlan($source, 7);
    $complete = $state->coordinator->runUntilSettled($plan->plan()->planId(), true);
    $health = array_search('health:release-new-001', $state->trace, true);
    $contract = array_search('migration:' . MigrationPhase::CONTRACT, $state->trace, true);
    $assert($complete->status() === UpgradePlan::COMPLETED, 'run --contract did not complete');
    $assert(is_int($health) && is_int($contract) && $health < $contract, 'run --contract approved work before its gate');
};

$tests['only one unsettled upgrade plan may exist or advance'] = static function () use (
    $fixture, $source, $assert, $assertThrows
): void {
    $state = $fixture(false);
    $first = $state->coordinator->createPlan($source, 7);
    $firstId = $first->plan()->planId();
    $assert($state->coordinator->hasUnsettledPlan(), 'unsettled plan was not detected');
    $assertThrows(
        static fn() => $state->coordinator->createPlan($source, 8),
        '存在未完成的 Upgrade Plan'
    );

    $other = new StoredUpgradePlan(
        UpgradePlan::create('upgrade-forced-concurrent', 'release-forced-concurrent'),
        'release-old-001',
        false,
        [],
        8
    );
    $state->plans->create($other);
    $assertThrows(
        static fn() => $state->coordinator->advance($firstId),
        '存在其他未完成的 Upgrade Plan'
    );
};

$tests['v2 final health and quiescence are mandatory, legacy shape is explicit'] = static function () use ($fixture,$source,$assert,$assertThrows): void {
    $state=$fixture(); $plan=$state->coordinator->createPlan($source,7); $id=$plan->plan()->planId();
    $assert($plan->planVersion()===2 && $plan->toArray()['legacy_plan']===false,'new plans must use v2');
    $state->coordinator->runUntilSettled($id);
    $state->coordinator->approveContract($id);
    $state->runtime->failQuiescence=true;
    $assertThrows(fn()=>$state->coordinator->advance($id),'consumer still running');
    $assert(!in_array('migration:contract',$state->trace,true),'contract must not run without stop evidence');
    $state->runtime->failQuiescence=false;
    do { $plan=$state->coordinator->advance($id); } while ($plan->plan()->current()?->name()!==UpgradeStep::FINAL_HEALTH);
    $assert($plan->status()!==UpgradePlan::COMPLETED,'contract completion is not upgrade completion');
    $state->runtime->failHealth=true;
    $assertThrows(fn()=>$state->coordinator->advance($id),'fixture health failure');
    $assert($state->coordinator->find($id)->plan()->current()->name()===UpgradeStep::FINAL_HEALTH,'final health failure must remain final health');
    $state->runtime->failHealth=false;
    $assert($state->coordinator->advance($id)->status()===UpgradePlan::COMPLETED,'healthy final target completes');
    $legacy=new StoredUpgradePlan(UpgradePlan::create('legacy','release-new-001',UpgradeStep::ordered(1)),null,false,[]);
    $assert($legacy->planVersion()===1 && $legacy->toArray()['legacy_plan']===true,'persisted unversioned plans must remain explicitly legacy');
};
$tests['recovery records pointer restoration even when service recovery fails'] = static function () use ($fixture,$source,$assert,$assertThrows): void {
    $state=$fixture(); $plan=$state->coordinator->createPlan($source,7); $id=$plan->plan()->planId();
    $state->coordinator->runUntilSettled($id);
    $state->runtime->failHealth=true;
    $assertThrows(fn()=>$state->coordinator->recover($id),'fixture health failure');
    $failed=$state->coordinator->find($id);
    $assert(isset($failed->context()['pointer_restored_at']) && $failed->context()['service_recovered']===false,'pointer result must persist separately');
    $assert($failed->status()!==StoredUpgradePlan::RECOVERED,'failed health cannot report service recovered');
    $assertThrows(fn()=>$state->coordinator->advance($id),'不能继续推进');
    $state->runtime->failHealth=false;
    $assert($state->coordinator->recover($id)->status()===StoredUpgradePlan::RECOVERED,'operator may retry service recovery');
};
$tests['uncertain execution cannot be replayed on a renewed lease'] = static function () use ($fixture,$source,$assertThrows): void {
    $state=$fixture(); $plan=$state->coordinator->createPlan($source,7); $id=$plan->plan()->planId();
    $state->plans->save($plan->withContext(array_replace($plan->context(),['execution_in_progress'=>true]))->withPlan($plan->plan()->start($plan->plan()->current()->name())),$plan->plan()->revision());
    $assertThrows(fn()=>$state->coordinator->advance($id),'上次升级执行未确认结束');
};

$tests['v2 no-contract releases still require final health and v1 retains its old shape'] = static function () use ($fixture,$source,$assert): void {
    $state=$fixture(false);$plan=$state->coordinator->createPlan($source,7);$id=$plan->plan()->planId();
    $done=$state->coordinator->runUntilSettled($id);
    $assert($done->status()===UpgradePlan::COMPLETED && isset($done->context()['final_health_checked_at']),'no-contract still requires final health');
    $state=$fixture(false);$plan=$state->coordinator->createPlan($source,7);$id=$plan->plan()->planId();
    $context=$plan->context();unset($context['plan_version']);
    $legacy=$plan->withContext($context)->withPlan(UpgradePlan::create($id,$plan->plan()->releaseId(),UpgradeStep::ordered(1)));
    $state->plans->save($legacy,0);
    $done=$state->coordinator->runUntilSettled($id);
    $assert($done->status()===UpgradePlan::COMPLETED && $done->planVersion()===1,'legacy shape can be explicitly continued');
    $assert(!isset($done->context()['final_health_checked_at']),'legacy plan must not silently append v2 steps');
};

$failures = 0;
try {
    foreach ($tests as $name => $test) {
        try {
            $test();
            fwrite(STDOUT, "PASS {$name}\n");
        } catch (Throwable $exception) {
            $failures++;
            fwrite(STDERR, "FAIL {$name}: {$exception->getMessage()}\n{$exception->getTraceAsString()}\n");
        }
    }
} finally {
    @unlink($source);
    @unlink($packagePath);
    @rmdir($tmp . DIRECTORY_SEPARATOR . 'instance' . DIRECTORY_SEPARATOR . 'shared' . DIRECTORY_SEPARATOR . 'upgrade');
    @rmdir($tmp . DIRECTORY_SEPARATOR . 'instance' . DIRECTORY_SEPARATOR . 'shared');
    @rmdir($tmp . DIRECTORY_SEPARATOR . 'instance');
    @rmdir($tmp);
}
if ($failures > 0) exit(1);
fwrite(STDOUT, count($tests) . " UpgradeCoordinator tests passed.\n");
