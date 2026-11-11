<?php
declare(strict_types=1);

namespace think\facade {
    final class Db
    {
        public static array $rows = [];
        public static array $locks = [];
        public static bool $transaction = false;
        public static ?\Closure $afterCommit = null;
        public static function table(string $name): ConfigQuery { return new ConfigQuery(); }
        public static function transaction(callable $action): mixed
        {
            $before = self::$rows;
            self::$transaction = true;
            try { $result = $action(); }
            catch (\Throwable $error) { self::$rows = $before; throw $error; }
            finally { self::$transaction = false; }
            if (self::$afterCommit !== null) (self::$afterCommit)();
            return $result;
        }
    }
    final class ConfigQuery
    {
        private array $where = [];
        public function where(string $key, mixed $operator, mixed $value = null): self
        {
            $this->where[] = [$key, func_num_args() === 2 ? '=' : $operator, func_num_args() === 2 ? $operator : $value];
            return $this;
        }
        public function lock(bool $lock): self { Db::$locks[] = $this->where; return $this; }
        public function order(string $field, string $direction = 'asc'): self { return $this; }
        private function matches(array $row): bool
        {
            foreach ($this->where as [$key, $operator, $value]) {
                if ($operator === '=' && ($row[$key] ?? null) !== $value) return false;
                if ($operator === '<>' && ($row[$key] ?? null) === $value) return false;
            }
            return true;
        }
        public function select(): object
        {
            $rows = array_values(array_filter(Db::$rows, fn($row) => $this->matches($row)));
            return new class($rows) { public function __construct(private array $rows) {} public function toArray(): array { return $this->rows; } };
        }
        public function find(): ?array { return $this->select()->toArray()[0] ?? null; }
        public function insert(array $row): int { Db::$rows[] = $row; return 1; }
        public function update(array $changes): int
        {
            $count = 0;
            foreach (Db::$rows as &$row) if ($this->matches($row)) { $row = array_replace($row, $changes); $count++; }
            return $count;
        }
        public function delete(): int
        {
            $before = count(Db::$rows);
            Db::$rows = array_values(array_filter(Db::$rows, fn($row) => !$this->matches($row)));
            return $before - count(Db::$rows);
        }
    }
}

namespace {
    function env(string $key): mixed { return $GLOBALS['configAtomicEnv'][$key] ?? null; }
    require dirname(__DIR__, 2) . '/app/api/ApiException.php';
    require dirname(__DIR__, 2) . '/app/modules/Platform/Config/Infrastructure/ConfigService.php';
    use app\modules\Platform\Config\Infrastructure\ConfigService;
    use think\facade\Db;
    $assert = static function (bool $condition, string $message): void { if (!$condition) throw new \RuntimeException($message); };
    $rejects = static function (callable $action, string $message) use ($assert): void {
        try { $action(); } catch (\Throwable) { return; }
        $assert(false, $message);
    };
    ConfigService::register('redis', ['host' => ['type' => 'string', 'default' => 'old'], 'port' => ['type' => 'int', 'default' => 6379]]);
    $snapshot = ConfigService::getSnapshot('redis');
    $assert($snapshot['revision'] > 0 && !isset($snapshot['values']['__revision']), 'metadata belongs only to the snapshot envelope');
    $assert(!isset(ConfigService::getSchema('redis')['__revision']), 'metadata must not become driver schema');
    ConfigService::register('user', ['captcha' => ['type' => 'bool', 'default' => true]]);
    $GLOBALS['configAtomicEnv']['USER_CAPTCHA'] = false;
    $assert(ConfigService::get('user.captcha') === false, 'boolean false from the process environment remains a valid override');
    unset($GLOBALS['configAtomicEnv']);
    $before = Db::$rows;
    Db::$locks = [];
    $rejects(fn() => ConfigService::compareAndSwapGroups(['redis' => ['host' => 'new']], ['redis' => $snapshot['revision'] + 1]), 'stale version must reject');
    $assert(Db::$rows === $before, 'stale version changes no rows');
    $result = ConfigService::compareAndSwapGroups(['redis' => ['host' => 'new', 'port' => 6380]], ['redis' => $snapshot['revision']]);
    $assert($result['redis']['values'] === ['host' => 'new', 'port' => 6380], 'the complete committed group is returned');
    $assert($result['redis']['revision'] === $snapshot['revision'] + 1, 'one group write advances revision once');
    ConfigService::register('other', ['name' => ['default' => 'before']]);
    Db::$locks = [];
    $before = Db::$rows;
    $rejects(fn() => ConfigService::compareAndSwapGroups(['redis' => ['host' => 'partial'], 'other' => ['name' => 'partial']], ['redis' => $result['redis']['revision'], 'other' => 999]), 'a second group conflict must reject all groups');
    $assert(Db::$rows === $before, 'multi-group conflict rolls back all changes');
    $assert(Db::$locks[0][0] === ['group', '=', 'other'], 'group locks use stable lexical order');
    Db::$afterCommit = static function (): void {
        foreach (Db::$rows as &$row) if ($row['group'] === 'redis' && $row['key'] === 'host') $row['value'] = 'later-writer';
    };
    $result = ConfigService::compareAndSwapGroups(['redis' => ['host' => 'captured']], ['redis' => $result['redis']['revision']]);
    Db::$afterCommit = null;
    $assert($result['redis']['values']['host'] === 'captured', 'response is captured before another post-commit writer');
    $rejects(fn() => ConfigService::set('redis.__revision', 1), 'ordinary writes cannot reset metadata');
    $rejects(fn() => ConfigService::deleteKey('redis', '__revision'), 'ordinary deletes cannot remove metadata');
    $rejects(fn() => ConfigService::register('redis', ['__revision' => ['default' => 1]]), 'schema registration cannot overwrite metadata');
    $revision = ConfigService::getSnapshot('redis')['revision'];
    ConfigService::deleteGroup('redis');
    ConfigService::register('redis', ['host' => ['default' => 'rebuilt']]);
    $assert(ConfigService::getSnapshot('redis')['revision'] > $revision, 'delete and rebuild preserve version monotonicity');
    $assert(ConfigService::getGroup('redis') === ['host' => 'rebuilt'], 'business reads omit revision tombstones');
    ConfigService::setGroup('redis', ['host' => 'internal']);
    $assert(ConfigService::getSnapshot('redis')['revision'] > $revision + 1, 'internal writes advance the same revision');
    echo "Config atomic baseline passed.\n";
}
