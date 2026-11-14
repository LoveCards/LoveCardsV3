<?php
declare(strict_types=1);

namespace think\facade {
    final class Db
    {
        public static array $tables = [];
        public static array $locks = [];
        public static function table(string $table): EligibilityQuery { return new EligibilityQuery($table); }
        public static function transaction(callable $callback): mixed {
            $before = self::$tables;
            try { return $callback(); }
            catch (\Throwable $error) { self::$tables = $before; throw $error; }
        }
    }
    final class EligibilityQuery
    {
        private array $filters = [];
        public function __construct(private string $table) {}
        public function where(string $key, mixed $value): self { $this->filters[$key] = $value; return $this; }
        public function lock(bool $lock): self { Db::$locks[] = $this->table; return $this; }
        public function find(): ?array {
            foreach (Db::$tables[$this->table] ?? [] as $row) {
                foreach ($this->filters as $key => $value) if (($row[$key] ?? null) !== $value) continue 2;
                return $row;
            }
            return null;
        }
        public function update(array $values): int {
            $row = $this->find();
            if ($row === null) return 0;
            Db::$tables[$this->table][$row['id']] = array_replace($row, $values);
            return 1;
        }
        public function insertGetId(array $row): int {
            $id = count(Db::$tables[$this->table] ?? []) + 1;
            Db::$tables[$this->table][$id] = $row + ['id' => $id];
            return $id;
        }
    }
}

namespace {
    require dirname(__DIR__, 2) . '/vendor/autoload.php';
    use app\modules\Platform\Eventing\Contract\SignalModePort;
    use app\modules\Platform\Eventing\Domain\DeliveryDefinition;
    use app\modules\Platform\Eventing\Infrastructure\ThinkDbDeliveryRepository;
    use app\modules\Platform\Eventing\Infrastructure\ThinkDbSignalPolicyStore;
    use app\modules\Platform\Eventing\Public\DeliveryContext;
    use app\modules\Platform\Eventing\Public\HandlerRegistry;
    use app\modules\Platform\Eventing\Public\SignalTransportUnavailable;
    use think\facade\Db;

    $registry = \app\EventHandlers::create();
    $mode = new class implements SignalModePort {
        public string $value = 'realtime';
        public function selectedModeForEnqueue(): string { Db::$locks[] = 'runtime_config_state'; return $this->value; }
        public function signalDeliveryState(): string { return $this->value === 'realtime' ? 'deliver' : 'skip'; }
    };
    function app(string $class): object {
        global $mode, $registry;
        return match ($class) { SignalModePort::class => $mode, HandlerRegistry::class => $registry,
            default => throw new RuntimeException('Unexpected binding: ' . $class) };
    }
    $count = 0;
    $assert = static function (bool $ok, string $message) use (&$count): void {
        $count++;
        if (!$ok) throw new RuntimeException($message);
    };
    $rejects = static function (callable $callback, string $message) use ($assert): void {
        try { $callback(); } catch (Throwable) { $assert(true, $message); return; }
        $assert(false, $message);
    };
    Db::$tables['signal_policy_state'][1] = ['id' => 1, 'revision' => 1, 'overrides_json' => '{}', 'generations_json' => '{}'];
    $store = new ThinkDbSignalPolicyStore();
    $repository = new ThinkDbDeliveryRepository();
    $catalog = $registry->signalCatalog();
    $signals = array_column($catalog, null, 'key');
    $audit = static function (): void {};
    $change = static function (string $key, bool $enabled) use ($store, $catalog, $audit): void {
        $store->applyChanges($catalog, [['key' => $key, 'enabled' => $enabled]], $store->catalog($catalog)['revision'], 1, $audit);
    };
    $enqueue = static function (string $handler, ?array $origin = null) use ($registry, $repository): int {
        return $repository->enqueue(10, [DeliveryDefinition::fromRegistration($registry->find($handler), $origin)], new DateTimeImmutable('now', new DateTimeZone('UTC')))[0];
    };
    $claim = static function (int $id): DeliveryContext {
        $row = &Db::$tables['event_deliveries'][$id];
        $row['status'] = 'processing';
        $row['claim_token'] = 'token-' . $id;
        $row['lease_expires_at'] = gmdate('Y-m-d H:i:s', time() + 60);
        return new DeliveryContext($id, 10, $row['handler_key'], 1, null, 'test', $row['claim_token']);
    };

    $feed = $enqueue('realtime.card-published');
    $message = $enqueue('realtime.message-sent');
    $feedContext = $claim($feed);
    Db::$locks = [];
    $assert($store->authorize($signals['feed.published'], $feedContext), 'enabled root obtains eligibility');
    $assert(Db::$locks === ['runtime_config_state', 'signal_policy_state', 'event_deliveries'], 'authorization locks mode then signal then delivery');
    $origin = $store->continueSignal($signals['feed.published'], $feedContext, static fn(array $origin): array => $origin);
    $child = $enqueue('realtime.card-feed-batch', $origin);
    $assert(Db::$tables['event_deliveries'][$child]['signal_generation'] === 1, 'continuation inherits root generation');
    $rejects(static fn() => $enqueue('realtime.message-sent', $origin), 'different signal cannot reuse root eligibility');

    $change('feed.published', false);
    $feedContext->assertSignalWindow();
    $assert(true, 'already authorized batch may finish its bounded window');
    $called = false;
    $store->continueSignal($signals['feed.published'], $feedContext, static function () use (&$called): void { $called = true; });
    $assert(!$called, 'disable stops further continuation registration');
    $disabledId = $enqueue('realtime.card-published');
    $assert(Db::$tables['event_deliveries'][$disabledId]['policy_decision'] === 'skipped', 'disabled registration is permanently skipped');
    $change('feed.published', true);
    $assert(!$store->authorize($signals['feed.published'], $claim($feed)), 'root cannot regain eligibility after reopen');
    $assert(!$store->authorize($signals['feed.published'], $claim($child)), 'continuation cannot regain eligibility after reopen');
    $assert(!$store->authorize($signals['feed.published'], $claim($disabledId)), 'disabled registration remains skipped even if manually made processing');
    $assert($store->authorize($signals['messages.new'], $claim($message)), 'unrelated signal generation is unaffected');
    $newId = $enqueue('realtime.card-published');
    $assert(Db::$tables['event_deliveries'][$newId]['signal_generation'] === 2, 'new root freezes next generation');
    $newContext = $claim($newId);
    $assert($store->authorize($signals['feed.published'], $newContext), 'new generation may send');
    $before = Db::$tables;
    $rejects(static fn() => $store->applyChanges($catalog, [['key' => 'feed.published', 'enabled' => false]], $store->catalog($catalog)['revision'], 1,
        static function (): void { throw new RuntimeException('audit failed'); }), 'audit failure must fail update');
    $assert(Db::$tables === $before, 'audit failure rolls back switch and eligibility generation');
    Db::$tables['event_deliveries'][$newId]['claim_token'] = 'replacement';
    $rejects(static fn() => $store->authorize($signals['feed.published'], $newContext), 'stale token cannot send');
    $newContext = $claim($newId);
    Db::$tables['event_deliveries'][$newId]['lease_expires_at'] = gmdate('Y-m-d H:i:s', time() - 1);
    $rejects(static fn() => $store->authorize($signals['feed.published'], $newContext), 'expired lease cannot send');
    $newContext->grantSignal('feed.published', 2, 0.000001);
    usleep(100);
    $rejects(static fn() => $newContext->assertSignalWindow(), 'expired authorization window cannot publish');
    $mode->value = 'polling';
    $assert(!$store->authorize($signals['feed.published'], $claim($newId)), 'polling never grants send eligibility');
    fwrite(STDOUT, "PASS signal eligibility baseline ({$count} assertions)\n");
}
