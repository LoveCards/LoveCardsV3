<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

use app\modules\Operations\SystemLifecycle\Application\MigrationPlanner;
use app\modules\Operations\SystemLifecycle\Application\MigrationRunner;
use app\modules\Operations\SystemLifecycle\Application\SystemLifecycleCoordinator;
use app\modules\Operations\SystemLifecycle\Contract\Migration;
use app\modules\Operations\SystemLifecycle\Contract\MigrationLedger;
use app\modules\Operations\SystemLifecycle\Contract\MigrationSource;
use app\modules\Operations\SystemLifecycle\Contract\UpgradeLease;
use app\modules\Operations\SystemLifecycle\Contract\UpgradePlanRepository;
use app\modules\Operations\SystemLifecycle\Domain\BackfillBatch;
use app\modules\Operations\SystemLifecycle\Domain\MigrationDescriptor;
use app\modules\Operations\SystemLifecycle\Domain\MigrationPhase;
use app\modules\Operations\SystemLifecycle\Domain\MigrationProgress;
use app\modules\Operations\SystemLifecycle\Domain\MigrationStatus;
use app\modules\Operations\SystemLifecycle\Domain\LifecycleLease;
use app\modules\Operations\SystemLifecycle\Domain\StoredUpgradePlan;
use app\modules\Operations\SystemLifecycle\Exception\UnsettledUpgradePlanException;
use app\modules\Operations\SystemLifecycle\Exception\UpgradeLeaseUnavailableException;
use app\modules\Operations\SystemLifecycle\Infrastructure\SqlFileMigration;

final class SystemLifecycleInMemoryLedger implements MigrationLedger
{
    /** @var array<string, MigrationProgress> */
    private array $records = [];

    /** @var int[] */
    public array $saveExpectations = [];

    public int $initializeCalls = 0;

    public function all(): array
    {
        return array_values($this->records);
    }

    public function allReadOnly(): array { return $this->all(); }

    public function find(string $migrationId): ?MigrationProgress
    {
        return $this->records[$migrationId] ?? null;
    }

    public function initialize(MigrationDescriptor $descriptor): MigrationProgress
    {
        $this->initializeCalls++;

        return $this->records[$descriptor->id()]
            ??= MigrationProgress::pending($descriptor);
    }

    public function save(MigrationProgress $progress, int $expectedRevision): MigrationProgress
    {
        $current = $this->records[$progress->id()] ?? null;
        if (
            $current === null
            || $current->revision() !== $expectedRevision
            || $progress->revision() !== $expectedRevision
        ) {
            throw new RuntimeException('fake ledger CAS conflict');
        }

        $this->saveExpectations[] = $expectedRevision;
        $saved = $progress->withRevision($expectedRevision + 1);
        $this->records[$progress->id()] = $saved;

        return $saved;
    }
}

class SystemLifecycleFakeMigration implements Migration
{
    private MigrationDescriptor $descriptor;

    /** @var BackfillBatch[] */
    private array $backfillBatches;

    public int $preflightCalls = 0;
    public int $expandCalls = 0;
    /** @var array<int, string|null> */
    public array $backfillCursors = [];
    public int $verifyCalls = 0;
    public int $contractCalls = 0;

    /** @param BackfillBatch[] $backfillBatches */
    public function __construct(string $id, bool $hasContract = false, array $backfillBatches = [])
    {
        $this->descriptor = MigrationDescriptor::fromSource(
            $id,
            'Fake migration ' . $id,
            'source:' . $id,
            $hasContract
        );
        $this->backfillBatches = $backfillBatches ?: [BackfillBatch::complete()];
    }

    public function descriptor(): MigrationDescriptor
    {
        return $this->descriptor;
    }

    public function preflight(): void
    {
        $this->preflightCalls++;
    }

    public function expand(): void
    {
        $this->expandCalls++;
    }

    public function backfill(?string $cursor): BackfillBatch
    {
        $this->backfillCursors[] = $cursor;
        $batch = array_shift($this->backfillBatches);
        if (!$batch instanceof BackfillBatch) {
            throw new RuntimeException('fake migration has no backfill batch');
        }

        return $batch;
    }

    public function verify(): void
    {
        $this->verifyCalls++;
    }

    public function contract(): void
    {
        $this->contractCalls++;
    }
}

final class SystemLifecycleFailingMigration extends SystemLifecycleFakeMigration
{
    public function expand(): void
    {
        parent::expand();
        throw new RuntimeException('fixture expand failure');
    }
}

final class SystemLifecycleInMemorySource implements MigrationSource
{
    /** @param Migration[] $migrations */
    public function __construct(private array $migrations)
    {
    }

    public int $discoverCalls = 0;

    public function discover(string $directory): array
    {
        $this->discoverCalls++;

        return $this->migrations;
    }
}

final class SystemLifecycleInMemoryLease implements UpgradeLease
{
    /** @var bool[] */
    private array $renewResults;

    /** @var array<int, array{string, DateTimeImmutable, int}> */
    public array $acquireCalls = [];

    /** @var array<int, array{string, string, DateTimeImmutable, int}> */
    public array $renewCalls = [];

    /** @var array<int, array{string, string}> */
    public array $releaseCalls = [];

    /** @param bool[] $renewResults */
    public function __construct(array $renewResults = [])
    {
        $this->renewResults = $renewResults;
    }

    public function acquire(string $leaseName, DateTimeImmutable $now, int $ttlSeconds): ?string
    {
        $this->acquireCalls[] = [$leaseName, $now, $ttlSeconds];

        return 'holder-token';
    }

    public function renew(
        string $leaseName,
        string $holderToken,
        DateTimeImmutable $now,
        int $ttlSeconds
    ): bool {
        $this->renewCalls[] = [$leaseName, $holderToken, $now, $ttlSeconds];

        return $this->renewResults === [] ? true : (bool) array_shift($this->renewResults);
    }

    public function release(string $leaseName, string $holderToken): void
    {
        $this->releaseCalls[] = [$leaseName, $holderToken];
    }
}

final class SystemLifecycleFakeUpgradePlans implements UpgradePlanRepository
{
    public function __construct(public bool $unsettled = false) {}
    public function create(StoredUpgradePlan $plan): StoredUpgradePlan { throw new LogicException('not used'); }
    public function find(string $planId): ?StoredUpgradePlan { return null; }
    public function recent(int $limit = 50): array { return []; }
    public function unsettled(bool $lock = false): ?StoredUpgradePlan { foreach ($this->recent() as $stored) if (!in_array($stored->status(), [UpgradePlan::COMPLETED, StoredUpgradePlan::RECOVERED], true)) return $stored; return null; }
    public function existsUnsettled(?string $excludingPlanId = null): bool { return $this->unsettled; }
    public function save(StoredUpgradePlan $plan, int $expectedRevision): StoredUpgradePlan { throw new LogicException('not used'); }
}

$tests = [];
$failures = 0;

$test = static function (string $name, callable $callback) use (&$tests): void {
    $tests[$name] = $callback;
};

$assertSame = static function (mixed $expected, mixed $actual, string $message = ''): void {
    if ($expected !== $actual) {
        throw new RuntimeException(
            ($message === '' ? 'assertion failed' : $message)
            . ': expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true)
        );
    }
};

$assertThrows = static function (string $class, callable $callback, string $message = ''): Throwable {
    try {
        $callback();
    } catch (Throwable $exception) {
        if (!$exception instanceof $class) {
            throw new RuntimeException('unexpected exception: ' . get_class($exception) . ': ' . $exception->getMessage());
        }
        if ($message !== '' && !str_contains($exception->getMessage(), $message)) {
            throw new RuntimeException('unexpected exception message: ' . $exception->getMessage());
        }

        return $exception;
    }

    throw new RuntimeException('expected exception: ' . $class);
};

$readSqlPhases = static function (SqlFileMigration $migration): array {
    $property = new ReflectionProperty(SqlFileMigration::class, 'phases');
    $property->setAccessible(true);

    return $property->getValue($migration);
};

$withSqlFile = static function (string $source, callable $callback): mixed {
    $path = tempnam(sys_get_temp_dir(), 'lc-system-lifecycle-');
    if ($path === false || file_put_contents($path, $source) === false) {
        throw new RuntimeException('failed to create temporary SQL fixture');
    }

    try {
        return $callback($path);
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
};

$test('MigrationRunner refuses ambiguous running ledger without replaying side effects', static function () use ($assertSame): void {
    $ledger = new SystemLifecycleInMemoryLedger();
    $migration = new SystemLifecycleFakeMigration('20991230000001');
    $initial = $ledger->initialize($migration->descriptor());
    $ledger->save($initial->started(), $initial->revision());
    $rejected = false;
    try { (new MigrationRunner($ledger))->runNext($migration, false, true); }
    catch (LogicException $e) { $rejected = str_contains($e->getMessage(), '未确认结束'); }
    $assertSame(true, $rejected);
    $assertSame(0, $migration->preflightCalls);
});

$test('MigrationRunner advances one phase per call and preserves CAS revisions', static function () use ($assertSame): void {
    $ledger = new SystemLifecycleInMemoryLedger();
    $runner = new MigrationRunner($ledger);
    $migration = new SystemLifecycleFakeMigration(
        '20260819000100_runner',
        false,
        [BackfillBatch::pending('page-1'), BackfillBatch::complete()]
    );

    $assertSame(MigrationPhase::EXPAND, $runner->runNext($migration)->phase());
    $assertSame(MigrationPhase::BACKFILL, $runner->runNext($migration)->phase());
    $firstBatch = $runner->runNext($migration);
    $assertSame(MigrationPhase::BACKFILL, $firstBatch->phase());
    $assertSame('page-1', $firstBatch->cursor());
    $assertSame(MigrationPhase::VERIFY, $runner->runNext($migration)->phase());
    $complete = $runner->runNext($migration);

    $assertSame(MigrationPhase::COMPLETE, $complete->phase());
    $assertSame(MigrationStatus::COMPLETE, $complete->status());
    $assertSame(1, $migration->preflightCalls);
    $assertSame(1, $migration->expandCalls);
    $assertSame([null, 'page-1'], $migration->backfillCursors);
    $assertSame(1, $migration->verifyCalls);
    $assertSame(0, $migration->contractCalls);
    $assertSame(range(0, 9), $ledger->saveExpectations, 'runner must use the latest persisted revision for every CAS save');
    $assertSame(10, $complete->revision());
});

$test('MigrationRunner keeps contract work waiting until explicitly authorized', static function () use ($assertSame): void {
    $ledger = new SystemLifecycleInMemoryLedger();
    $runner = new MigrationRunner($ledger);
    $migration = new SystemLifecycleFakeMigration('20260819000200_contract', true);

    $runner->runNext($migration);
    $runner->runNext($migration);
    $runner->runNext($migration);
    $waiting = $runner->runNext($migration, false);

    $assertSame(MigrationPhase::CONTRACT, $waiting->phase());
    $assertSame(MigrationStatus::WAITING, $waiting->status());
    $assertSame(0, $migration->contractCalls);
    $saveCount = count($ledger->saveExpectations);

    $stillWaiting = $runner->runNext($migration, false);
    $assertSame($waiting->toArray(), $stillWaiting->toArray());
    $assertSame($saveCount, count($ledger->saveExpectations), 'waiting contract must not churn the ledger');
    $assertSame(0, $migration->contractCalls);

    $complete = $runner->runNext($migration, true);
    $assertSame(MigrationStatus::COMPLETE, $complete->status());
    $assertSame(1, $migration->contractCalls);
});

$test('MigrationRunner persists failure code and requires explicit retry', static function () use ($assertSame, $assertThrows): void {
    $ledger = new SystemLifecycleInMemoryLedger();
    $runner = new MigrationRunner($ledger);
    $migration = new SystemLifecycleFailingMigration('20260819000250_failed');

    $runner->runNext($migration);
    $assertThrows(RuntimeException::class, static fn() => $runner->runNext($migration), 'fixture expand failure');
    $failed = $ledger->find($migration->descriptor()->id());
    $assertSame(MigrationStatus::FAILED, $failed?->status());
    $assertSame(RuntimeException::class, $failed?->failureCode());
    $assertSame(2, $failed?->attempts(), 'preflight and expand must be counted before failure');
    $assertSame(MigrationStatus::FAILED, $runner->runNext($migration)->status(), 'scheduler retry must not repeat failed work');
    $assertThrows(RuntimeException::class, static fn() => $runner->runNext($migration, false, true), 'fixture expand failure');
    $assertSame(MigrationStatus::FAILED, $ledger->find($migration->descriptor()->id())?->status());
});

$test('Coordinator settles multiple migrations and renews around every advance', static function () use ($assertSame): void {
    $ledger = new SystemLifecycleInMemoryLedger();
    $first = new SystemLifecycleFakeMigration('20260819000300_first');
    $second = new SystemLifecycleFakeMigration('20260819000400_second');
    $source = new SystemLifecycleInMemorySource([$first, $second]);
    $lease = new SystemLifecycleInMemoryLease();
    $coordinator = new SystemLifecycleCoordinator(
        new MigrationPlanner($ledger),
        new MigrationRunner($ledger),
        $source,
        $lease,
        new SystemLifecycleFakeUpgradePlans()
    );

    $rows = $coordinator->runUntilSettled('/not-used');

    $assertSame(8, count($rows));
    $assertSame(MigrationStatus::COMPLETE, $ledger->find('20260819000300_first')?->status());
    $assertSame(MigrationStatus::COMPLETE, $ledger->find('20260819000400_second')?->status());
    $assertSame(9, $source->discoverCalls, 'coordinator must perform a final no-work plan');
    $assertSame(17, count($lease->renewCalls), 'each completed advance must renew before and after work');
    $assertSame(1, count($lease->acquireCalls));
    $assertSame(1, count($lease->releaseCalls));
    $assertSame('holder-token', $lease->releaseCalls[0][1]);
    foreach ($lease->renewCalls as $renewCall) {
        $assertSame(LifecycleLease::NAME, $renewCall[0]);
        $assertSame('holder-token', $renewCall[1]);
        $assertSame(900, $renewCall[3]);
    }
    foreach ([$first, $second] as $migration) {
        $assertSame(1, $migration->preflightCalls);
        $assertSame(1, $migration->expandCalls);
        $assertSame([null], $migration->backfillCursors);
        $assertSame(1, $migration->verifyCalls);
    }
});

$test('Coordinator phase fence advances exactly one matching migration unit', static function () use ($assertSame, $assertThrows): void {
    $ledger = new SystemLifecycleInMemoryLedger();
    $migration = new SystemLifecycleFakeMigration(
        '20260819000450_phase_fence',
        true,
        [BackfillBatch::pending('page-1'), BackfillBatch::complete()]
    );
    $coordinator = new SystemLifecycleCoordinator(
        new MigrationPlanner($ledger),
        new MigrationRunner($ledger),
        new SystemLifecycleInMemorySource([$migration]),
        new SystemLifecycleInMemoryLease(),
        new SystemLifecycleFakeUpgradePlans()
    );

    $assertSame(1, count($coordinator->runPhase('/not-used', MigrationPhase::PREFLIGHT)));
    $assertSame(1, $migration->preflightCalls);
    $assertSame(0, $migration->expandCalls);
    $assertSame([], $coordinator->runPhase('/not-used', MigrationPhase::PREFLIGHT));
    $assertSame(1, count($coordinator->runPhase('/not-used', MigrationPhase::EXPAND)));
    $assertSame(1, $migration->expandCalls);
    $assertSame(1, count($coordinator->runPhase('/not-used', MigrationPhase::BACKFILL)));
    $assertSame([null], $migration->backfillCursors);
    $assertSame(1, count($coordinator->runPhase('/not-used', MigrationPhase::BACKFILL)));
    $assertSame([null, 'page-1'], $migration->backfillCursors);
    $assertSame(1, count($coordinator->runPhase('/not-used', MigrationPhase::VERIFY)));
    $assertSame(1, $migration->verifyCalls);
    $assertThrows(
        LogicException::class,
        static fn() => $coordinator->runPhase('/not-used', MigrationPhase::CONTRACT),
        '显式批准'
    );
    $assertSame(1, count($coordinator->runPhase('/not-used', MigrationPhase::CONTRACT, true)));
    $assertSame(1, $migration->contractCalls);
});

$test('Coordinator aborts immediately when its lease is lost', static function () use ($assertSame, $assertThrows): void {
    $ledger = new SystemLifecycleInMemoryLedger();
    $migration = new SystemLifecycleFakeMigration('20260819000500_lease_loss');
    $source = new SystemLifecycleInMemorySource([$migration]);
    $lease = new SystemLifecycleInMemoryLease([true, false]);
    $coordinator = new SystemLifecycleCoordinator(
        new MigrationPlanner($ledger),
        new MigrationRunner($ledger),
        $source,
        $lease,
        new SystemLifecycleFakeUpgradePlans()
    );

    $assertThrows(
        UpgradeLeaseUnavailableException::class,
        static fn() => $coordinator->runUntilSettled('/not-used'),
        'Lease'
    );

    $assertSame(1, $migration->preflightCalls);
    $assertSame(0, $migration->expandCalls, 'no later phase may run after lease loss');
    $assertSame(1, $source->discoverCalls);
    $assertSame(2, count($lease->renewCalls));
    $assertSame(1, count($lease->releaseCalls), 'lease release remains best-effort in finally');
});

$test('Manual coordinator rejects an unsettled upgrade plan inside the lifecycle lease', static function () use ($assertSame, $assertThrows): void {
    $ledger = new SystemLifecycleInMemoryLedger();
    $migration = new SystemLifecycleFakeMigration('20260819000550_manual_plan_fence');
    $source = new SystemLifecycleInMemorySource([$migration]);
    $lease = new SystemLifecycleInMemoryLease();
    $plans = new SystemLifecycleFakeUpgradePlans(true);
    $coordinator = new SystemLifecycleCoordinator(
        new MigrationPlanner($ledger),
        new MigrationRunner($ledger),
        $source,
        $lease,
        $plans
    );

    $assertThrows(
        UnsettledUpgradePlanException::class,
        static fn() => $coordinator->run('/not-used'),
        '未完成的升级计划'
    );
    $assertThrows(
        UnsettledUpgradePlanException::class,
        static fn() => $coordinator->runUntilSettled('/not-used'),
        '未完成的升级计划'
    );
    $assertSame(0, $source->discoverCalls, 'manual migration must stop before reading or executing migration work');
    $assertSame(2, count($lease->acquireCalls), 'the conflict check must happen after acquiring the shared lease');
    $assertSame(2, count($lease->releaseCalls), 'the shared lease must be released after a rejected manual migration');

    // The public phase executor is a manual entry point and must obey the same
    // unsettled-plan fence as run()/runUntilSettled(). UpgradeCoordinator uses
    // runPhaseWithLease() while it owns the active plan lease.
    $assertThrows(
        UnsettledUpgradePlanException::class,
        static fn() => $coordinator->runPhase('/not-used', MigrationPhase::PREFLIGHT),
        '未完成的升级计划'
    );
    $assertSame(0, $migration->preflightCalls, 'manual phase execution must stop before migration work');
});

$test('Release migration adapter preserves the plan fence and retries only with explicit authority', static function () use ($assertSame, $assertThrows): void {
    $ledger = new SystemLifecycleInMemoryLedger();
    $migration = new class('20261111000100_retry') extends SystemLifecycleFakeMigration {
        public bool $broken = true;
        public function expand(): void {
            parent::expand();
            if ($this->broken) throw new RuntimeException('temporary expand failure');
        }
    };
    $coordinator = new SystemLifecycleCoordinator(
        new MigrationPlanner($ledger), new MigrationRunner($ledger),
        new SystemLifecycleInMemorySource([$migration]), new SystemLifecycleInMemoryLease(),
        new SystemLifecycleFakeUpgradePlans(true)
    );
    $adapter = new \app\modules\Operations\SystemLifecycle\Infrastructure\SystemLifecycleMigrationExecutor($coordinator);
    $adapter->advancePhase('/not-used', MigrationPhase::PREFLIGHT, false, 'plan-holder');
    $assertThrows(RuntimeException::class, static fn() => $adapter->advancePhase('/not-used', MigrationPhase::EXPAND, false, 'plan-holder'), 'temporary expand failure');
    $failed = $ledger->find($migration->descriptor()->id());
    $assertSame(MigrationStatus::FAILED, $failed->status());
    $assertSame($failed->toArray(), $adapter->inspectPhase('/not-used', MigrationPhase::EXPAND), 'audit preview must use actual failed ledger identity');
    $assertSame($failed->revision(), $ledger->find($migration->descriptor()->id())->revision(), 'audit preview must not reset failed ledger');
    $migration->broken = false;
    $row = $adapter->advancePhase('/not-used', MigrationPhase::EXPAND, false, 'plan-holder');
    $assertSame(MigrationStatus::FAILED, $row['status'], 'ordinary advances cannot reset a failed ledger');
    $assertSame(1, $migration->expandCalls);
    $assertThrows(UnsettledUpgradePlanException::class, static fn() => $coordinator->run('/not-used', false, true));
    $row = $adapter->advancePhase('/not-used', MigrationPhase::EXPAND, false, 'plan-holder', true);
    $assertSame(MigrationPhase::BACKFILL, $row['phase']);
    $assertSame(2, $migration->expandCalls);
    $assertSame($failed->attempts() + 1, $row['attempts']);
    $assertSame($failed->checksum(), $row['checksum']);
    $assertSame(null, $adapter->inspectPhase('/not-used', MigrationPhase::EXPAND), 'completed phase preview must not select another phase');
    $assertThrows(LogicException::class, static fn() => $adapter->advancePhase('/not-used', MigrationPhase::CONTRACT, false, 'plan-holder', true), '显式批准');
});

$test('SqlFileMigration treats an unmarked legacy file as expand SQL', static function () use ($assertSame, $readSqlPhases, $withSqlFile): void {
    $source = "CREATE TABLE `legacy_table` (`id` BIGINT NOT NULL);\n";
    $withSqlFile($source, static function (string $path) use ($assertSame, $readSqlPhases, $source): void {
        $migration = new SqlFileMigration('20260819000600_legacy', $path, 'Legacy SQL');
        $phases = $readSqlPhases($migration);

        $assertSame($source, $phases['expand']);
        $assertSame('', $phases['preflight']);
        $assertSame('', $phases['backfill']);
        $assertSame('', $phases['verify']);
        $assertSame('', $phases['contract']);
        $assertSame(false, $migration->descriptor()->hasContract());
    });
});

$test('SqlFileMigration recognizes an explicit contract phase', static function () use ($assertSame, $readSqlPhases, $withSqlFile): void {
    $source = implode("\n", [
        '-- migration metadata comment',
        '-- @lc-phase expand',
        'CREATE TABLE `staged_table` (`id` BIGINT NOT NULL);',
        '-- @lc-phase contract',
        'DROP TABLE `old_table`;',
    ]);
    $withSqlFile($source, static function (string $path) use ($assertSame, $readSqlPhases): void {
        $migration = new SqlFileMigration('20260819000700_marked', $path, 'Marked SQL');
        $phases = $readSqlPhases($migration);

        $assertSame(true, $migration->descriptor()->hasContract());
        $assertSame("CREATE TABLE `staged_table` (`id` BIGINT NOT NULL);\n", $phases['expand']);
        $assertSame("DROP TABLE `old_table`;\n", $phases['contract']);
    });
});

$test('SqlFileMigration rejects executable SQL before the first phase marker', static function () use ($assertThrows, $withSqlFile): void {
    $source = "SET @unsafe = 1;\n-- @lc-phase expand\nSELECT 1;\n";
    $withSqlFile($source, static function (string $path) use ($assertThrows): void {
        $assertThrows(
            InvalidArgumentException::class,
            static fn() => new SqlFileMigration('20260819000800_invalid', $path, 'Invalid SQL'),
            '首个 @lc-phase'
        );
    });
});

foreach ($tests as $name => $callback) {
    try {
        $callback();
        fwrite(STDOUT, "PASS {$name}\n");
    } catch (Throwable $exception) {
        $failures++;
        fwrite(STDERR, "FAIL {$name}: {$exception->getMessage()}\n");
    }
}

if ($failures > 0) {
    exit(1);
}

fwrite(STDOUT, count($tests) . " SystemLifecycle behavior tests passed.\n");
