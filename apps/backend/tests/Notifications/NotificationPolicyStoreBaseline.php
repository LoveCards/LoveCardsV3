<?php
declare(strict_types=1);

namespace think\facade {
    /** 用内存事务注入审计失败和提交后并发写，直接验证生产 Store 的边界。 */
    final class Db
    {
        public static array $tables = [];
        public static bool $inTransaction = false;
        public static bool $failRead = false;
        public static ?\Closure $afterCommit = null;
        public static function table(string $name): PolicyQuery { return new PolicyQuery($name); }
        public static function transaction(callable $action): mixed
        {
            $before = self::$tables;
            self::$inTransaction = true;
            try { $result = $action(); }
            catch (\Throwable $error) { self::$tables = $before; throw $error; }
            finally { self::$inTransaction = false; }
            if (self::$afterCommit !== null) (self::$afterCommit)();
            return $result;
        }
    }
    final class PolicyQuery
    {
        private array $conditions = [];
        public function __construct(private string $table) {}
        public function where(string $key, mixed $value): self { $this->conditions[$key] = $value; return $this; }
        public function lock(bool $lock): self { return $this; }
        public function order(string $key, string $direction): self { return $this; }
        private function matches(array $row): bool
        {
            foreach ($this->conditions as $key => $value) if (($row[$key] ?? null) !== $value) return false;
            return true;
        }
        public function select(): object
        {
            if (Db::$failRead) throw new \RuntimeException('injected read failure');
            $rows = array_values(array_filter(Db::$tables[$this->table], fn(array $row): bool => $this->matches($row)));
            return new class($rows) {
                public function __construct(private array $rows) {}
                public function toArray(): array { return $this->rows; }
            };
        }
        public function find(): ?array { return $this->select()->toArray()[0] ?? null; }
        public function value(string $key): mixed { return $this->find()[$key] ?? null; }
        public function max(string $key): mixed { $values = array_column($this->select()->toArray(), $key); return $values === [] ? null : max($values); }
        public function update(array $changes): int
        {
            $count = 0;
            foreach (Db::$tables[$this->table] as &$row) {
                if ($this->matches($row)) { $row = array_replace($row, $changes); $count++; }
            }
            unset($row);
            return $count;
        }
        public function insert(array $row): int { Db::$tables[$this->table][] = $row; return 1; }
    }
}

namespace {
    $root = dirname(__DIR__, 2);
    require $root . '/app/modules/Platform/Notifications/Contract/NotificationPolicyStore.php';
    require $root . '/app/modules/Platform/Notifications/Infrastructure/ThinkDbNotificationPolicyStore.php';
    require $root . '/app/modules/Platform/Notifications/Application/NotificationPolicyGate.php';
    require $root . '/app/modules/Platform/Eventing/Contract/PolicyVersionSource.php';
    require $root . '/app/modules/Platform/Notifications/Infrastructure/ThinkDbNotificationPolicyVersionSource.php';

    use app\modules\Platform\Notifications\Infrastructure\ThinkDbNotificationPolicyStore;
    use app\modules\Platform\Notifications\Application\NotificationPolicyGate;
    use think\facade\Db;

    $assert = static function (bool $value, string $message): void { if (!$value) throw new RuntimeException($message); };
    $rejects = static function (callable $action, string $message) use ($assert): void {
        try { $action(); } catch (Throwable) { return; }
        $assert(false, $message);
    };
    $rule = ['enabled' => true, 'locked' => false, 'user_disableable' => true, 'notification_type' => 'content.card_liked'];
    $initial = [
        'notification_policy_versions' => [['policy_revision' => 1, 'snapshot_json' => json_encode(['content.liked' => $rule])]],
        'notification_policies' => [$rule + ['event_type' => 'content.liked', 'event_version' => 1, 'rule_key' => 'notify', 'policy_revision' => 1]],
    ];
    Db::$tables = $initial;
    $store = new ThinkDbNotificationPolicyStore();
    $assert($store->listCurrent()[0]['recipient_rule'] === 'handler', 'removed storage column must preserve management API recipient rule');
    Db::$tables['notification_policies'][0]['recipient_rule'] = 'custom';
    $rejects(fn() => $store->listCurrent(), 'unsupported historical recipient rule must not be displayed as handler');
    Db::$tables = $initial;
    $gate = new NotificationPolicyGate($store);
    $change = ['event_type' => 'content.liked', 'event_version' => 1, 'rule_key' => 'notify', 'enabled' => false];
    $noop = static function (): void {};

    foreach ([['event_version' => 999], ['rule_key' => 'unknown'], ['enabled' => 'false']] as $invalid) {
        $rejects(fn() => $store->applyChanges([array_replace($change, $invalid)], 1, 7, null, $noop), 'invalid identity must fail');
        $assert(Db::$tables === $initial, 'invalid identity must preserve rows and snapshot');
    }
    $rejects(fn() => $store->applyChanges([$change, $change], 1, 7, null, $noop), 'duplicate event must fail');
    $rejects(fn() => $store->applyChanges([$change], 1, 7, null, static function (): void { throw new RuntimeException('audit unavailable'); }), 'audit error must propagate');
    $assert(Db::$tables === $initial, 'audit error rolls back policy rows and snapshot');

    Db::$afterCommit = static function (): void { Db::$tables['notification_policies'][0]['enabled'] = true; Db::$tables['notification_policies'][0]['policy_revision'] = 3; };
    $result = $store->applyChanges([$change], 1, 7, null, static function (array $before, array $after, int $revision) use ($assert): void {
        $assert(Db::$inTransaction, 'audit must execute inside policy transaction');
        $assert($before[0]['enabled'] && !$after[0]['enabled'] && $revision === 2, 'audit must capture exact committed change');
    });
    $assert($result['revision'] === 2 && $result['policies'][0]['policy_revision'] === 2 && !$result['policies'][0]['enabled'], 'response must not read a later committed revision');
    Db::$afterCommit = null;
    Db::$tables = $initial;

    $rejects(fn() => $gate->decide('content.liked', 99), 'missing frozen revision cannot enable notification');
    foreach (['{', '[]', '{"content.liked":{"enabled":"false"}}', '{"content.liked":null}'] as $corrupt) {
        Db::$tables['notification_policy_versions'][0]['snapshot_json'] = $corrupt;
        $rejects(fn() => $gate->decide('content.liked', 1), 'corrupt frozen snapshot cannot enable notification');
    }
    Db::$failRead = true;
    $rejects(fn() => $gate->decide('content.liked', 1), 'snapshot read failure cannot enable notification');
    $rejects(fn() => $store->currentRevision(), 'revision read failure cannot create legacy null deliveries');
    $rejects(fn() => (new \app\modules\Platform\Notifications\Infrastructure\ThinkDbNotificationPolicyVersionSource())->currentRevision(), 'outbox source must not disguise query failure as legacy null');
    $assert($gate->decide('content.liked', null) === 'not-configured', 'legacy null remains independent of unavailable snapshot storage');

    Db::$failRead = false;
    $systemRow = ['event_type' => 'system.notification.issued', 'event_version' => 1, 'rule_key' => 'notify',
        'enabled' => true, 'locked' => true, 'user_disableable' => false,
        'notification_type' => 'system.moderation', 'recipient_rule' => 'handler', 'policy_revision' => 1];
    $systemSnapshot = ['enabled' => true, 'locked' => true, 'user_disableable' => false, 'notification_type' => 'system.*'];
    Db::$tables = ['notification_policy_versions' => [['policy_revision' => 1, 'snapshot_json' => json_encode(['system.notification.issued' => $systemSnapshot])]],
        'notification_policies' => [$systemRow]];
    $systemResult = $store->applyChanges([], 1, 7, null, $noop);
    $assert($systemResult['revision'] === 2, 'reviewed system wildcard snapshot remains applicable');
    Db::$tables['notification_policy_versions'][1]['snapshot_json'] = json_encode(['system.notification.issued' => array_replace($systemSnapshot, ['notification_type' => 'system.unknown'])]);
    $rejects(fn() => $store->applyChanges([], 2, 7, null, $noop), 'unreviewed notification type drift must fail');
    fwrite(STDOUT, "PASS notification policy persistence boundary baseline\n");
}
