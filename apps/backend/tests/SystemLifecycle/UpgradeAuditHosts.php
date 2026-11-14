<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Application {
    final class UpgradeCoordinator {
        public int $executions = 0;
        public function find(string $id): self { return $this; }
        public function advance(string $id, ?callable $beforeAdvance = null): self {
            if ($beforeAdvance === null) throw new \RuntimeException('Host omitted audit callback');
            $beforeAdvance(['plan_id' => $id, 'release_id' => 'target', 'revision' => 9,
                'step' => ['name' => 'expand_migrations', 'state' => 'failed'],
                'migration' => ['id' => '20261111000001', 'checksum' => str_repeat('a', 64), 'phase' => 'expand', 'status' => 'failed'],
                'retry_failed_migration' => true]);
            $this->executions++;
            return $this;
        }
        public function runUntilSettled(string $id, bool $contract, int $max, ?callable $beforeAdvance = null): self {
            return $this->advance($id, $beforeAdvance);
        }
        public function plan(): self { return $this; }
        public function status(): string { return 'running'; }
        public function revision(): int { return 10; }
        public function toArray(): array { return ['revision' => 10]; }
    }
}
namespace app\api\controller {
    class BaseController { protected function respond(array $data): array { return $data; } }
}
namespace {
    require dirname(__DIR__, 2) . '/vendor/autoload.php';
    $coordinator = new \app\modules\Operations\SystemLifecycle\Application\UpgradeCoordinator();
    $logger = new class implements \app\shared\Observability\AuditLogger {
        public bool $fail = false;
        public array $records = [];
        public function record(?int $actorId, string $action, string $resourceType, ?string $resourceId, array $metadata = []): void {
            if ($this->fail) throw new \RuntimeException('fixture audit failure');
            $this->records[] = compact('actorId', 'action', 'resourceId', 'metadata');
        }
    };
    function app(string $id): object {
        global $coordinator, $logger;
        return $id === \app\shared\Observability\AuditLogger::class ? $logger : $coordinator;
    }
    function request(): object {
        return new class {
            public object $auth;
            public function __construct() { $this->auth = new class { public function uid(): int { return 17; } }; }
            public function ip(): string { return '127.0.0.1'; }
        };
    }
    $assert = static function (bool $condition, string $message): void { if (!$condition) throw new \RuntimeException($message); };
    foreach (['advance', 'run', 'http'] as $action) {
        $invoke = static function () use ($action): void {
            if ($action === 'http') {
                (new \app\api\controller\Platform\Administration())->advanceUpgrade('plan-1');
            } else {
                $command = new \app\command\SystemUpgrade();
                $command->run(new \think\console\Input([$action, 'plan-1']), new \think\console\Output('nothing'));
            }
        };
        $before = $coordinator->executions;
        $logger->fail = true;
        try { $invoke(); throw new \LogicException('audit failure did not abort Host'); }
        catch (\RuntimeException $exception) { $assert($exception->getMessage() === 'fixture audit failure', 'unexpected failure'); }
        $assert($coordinator->executions === $before, 'failed audit allowed execution');
        $logger->fail = false;
        $logger->records = [];
        $invoke();
        $record = $logger->records[0] ?? [];
        $assert(($record['action'] ?? '') === 'system.upgrade.advance.requested', 'missing pre-execution audit');
        $assert(($record['metadata']['source'] ?? '') === ($action === 'http' ? 'http' : 'cli'), 'wrong Host source');
        $assert(($record['actorId'] ?? null) === ($action === 'http' ? 17 : null), 'wrong actor identity');
        $assert(($record['metadata']['revision'] ?? 0) === 9 && ($record['metadata']['migration']['checksum'] ?? '') === str_repeat('a', 64), 'Host lost captured identity');
        $assert($coordinator->executions === $before + 1, 'Host must execute exactly once after audit');
    }
    echo "PASS CLI advance/run and HTTP preserve exact pre-execution audit and reject audit failures\n";
}
