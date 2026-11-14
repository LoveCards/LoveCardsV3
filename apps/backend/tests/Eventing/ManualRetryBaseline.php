<?php
declare(strict_types=1);

namespace think\facade {
    final class Db
    {
        public static array $row = [];
        public static bool $inside = false;
        public static function table(string $name): RetryQuery
        {
            if ($name !== 'event_deliveries') throw new \RuntimeException('Unexpected table');
            return new RetryQuery();
        }
        public static function transaction(callable $action): mixed
        {
            $before = self::$row;
            self::$inside = true;
            try { return $action(); }
            catch (\Throwable $error) { self::$row = $before; throw $error; }
            finally { self::$inside = false; }
        }
    }
    final class RetryQuery
    {
        private int $id = 0;
        public function where(string $key, int $value): self { $this->id = $value; return $this; }
        public function lock(bool $locked): self { return $this; }
        public function find(): ?array { return (Db::$row['id'] ?? 0) === $this->id ? Db::$row : null; }
        public function update(array $values): int { Db::$row = array_replace(Db::$row, $values); return 1; }
    }
}

namespace {
    $root = dirname(__DIR__, 2);
    require $root . '/vendor/autoload.php';
    use app\modules\Platform\Eventing\Public\DeliveryRetries;
    use think\facade\Db;

    $assert = static function (bool $value, string $message): void { if (!$value) throw new RuntimeException($message); };
    $original = ['id' => 5, 'event_id' => 8, 'handler_key' => 'notifications.social-followed',
        'status' => 'dead', 'attempt_count' => 5, 'policy_revision' => 7, 'policy_decision' => 'applied',
        'last_error' => 'original sanitized failure', 'retry_attempt_limit' => null];
    Db::$row = $original;
    $failed = false;
    try { DeliveryRetries::retry(5, static function (): void { throw new RuntimeException('audit unavailable'); }); }
    catch (RuntimeException) { $failed = true; }
    $assert($failed && Db::$row === $original, 'audit failure must roll back retry authorization');
    $auditCount = 0;
    $retried = DeliveryRetries::retry(5, static function (array $before, array $after) use ($assert, &$auditCount): void {
        $auditCount++;
        $assert(Db::$inside, 'retry audit must share the authorization transaction');
        $assert($before['attempt_count'] === 5 && $after['retry_attempt_limit'] === 6, 'authorization grants exactly one attempt');
        $assert($after['last_error'] === $before['last_error'] && $after['policy_revision'] === 7, 'retry preserves error and frozen decision');
    });
    $assert($retried && Db::$row['status'] === 'pending' && Db::$row['attempt_count'] === 5, 'retry must not reset cumulative attempts');
    $assert(!DeliveryRetries::retry(5, static function (): void { throw new RuntimeException('unexpected audit'); }), 'duplicate authorization cannot stack while pending');
    $assert($auditCount === 1, 'one successful retry has one audit');
    foreach (['pending', 'processing', 'completed'] as $status) {
        Db::$row = array_replace($original, ['status' => $status]);
        $assert(!DeliveryRetries::retry(5, static function (): void {}), 'only dead deliveries are eligible');
    }
    Db::$row = array_replace($original, ['policy_decision' => 'skipped']);
    $assert(!DeliveryRetries::retry(5, static function (): void {}), 'skipped decisions cannot be resurrected');
    Db::$row = $original;
    $assert(!DeliveryRetries::retry(99, static function (): void {}), 'unknown delivery cannot be retried');
    fwrite(STDOUT, "PASS manual retry authorization baseline\n");
}
