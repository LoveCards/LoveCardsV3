<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Infrastructure;

use app\modules\Operations\SystemLifecycle\Contract\UpgradeLease;
use think\facade\Db;

final class ThinkDbUpgradeLease implements UpgradeLease
{
    private bool $bootstrapped = false;
    /** 会话锁覆盖整个副作用执行区间；TTL 只用于账本，不授权并行重放。 */
    private array $executionLocks = [];

    public function acquire(string $leaseName, \DateTimeImmutable $now, int $ttlSeconds): ?string
    {
        $leaseName = $this->leaseName($leaseName);
        if (isset($this->executionLocks[$leaseName])) return null;
        $connection = Db::connect();
        $identity = $connection->query('SELECT CONNECTION_ID() AS id, DATABASE() AS db')[0] ?? [];
        $lockName = 'lc:lifecycle:' . substr(hash('sha256', (string) ($identity['db'] ?? '') . ':' . $leaseName), 0, 48);
        // GET_LOCK 对同一连接可重入；另一服务实例不能凭共享连接二次取得执行资格。
        $existing = $connection->query('SELECT IS_USED_LOCK(?) AS owner', [$lockName]);
        if (($existing[0]['owner'] ?? null) !== null) return null;
        $row = $connection->query('SELECT GET_LOCK(?, 0) AS acquired', [$lockName]);
        if ((int) ($row[0]['acquired'] ?? 0) !== 1) return null;
        try {
            $token = $this->acquireLease($leaseName, $now, $ttlSeconds);
            if ($token === null) {
                $connection->query('SELECT RELEASE_LOCK(?)', [$lockName]);
                return null;
            }
            $this->executionLocks[$leaseName] = ['connection'=>$connection, 'id'=>(int) $identity['id'], 'name'=>$lockName, 'token'=>$token];
            return $token;
        } catch (\Throwable $exception) {
            $connection->query('SELECT RELEASE_LOCK(?)', [$lockName]);
            throw $exception;
        }
    }

    private function ownsExecution(string $leaseName, string $token): bool
    {
        $lock = $this->executionLocks[$leaseName] ?? null;
        if ($lock === null || !hash_equals($lock['token'], $token)) return false;
        try {
            $row = $lock['connection']->query('SELECT CONNECTION_ID() AS id, IS_USED_LOCK(?) AS owner', [$lock['name']])[0] ?? [];
            return (int) ($row['id'] ?? 0) === $lock['id'] && (int) ($row['owner'] ?? 0) === $lock['id'];
        } catch (\Throwable $ignored) { return false; }
    }

    private function acquireLease(string $leaseName, \DateTimeImmutable $now, int $ttlSeconds): ?string
    {
        $leaseName = $this->leaseName($leaseName);
        if ($ttlSeconds < 5 || $ttlSeconds > 3600) {
            throw new \InvalidArgumentException('System Lease TTL 必须为 5..3600 秒');
        }
        $this->bootstrap();

        $token = bin2hex(random_bytes(32));
        $expiresAt = $now->modify('+' . $ttlSeconds . ' seconds')->format('Y-m-d H:i:s.u');
        $timestamp = $now->format('Y-m-d H:i:s.u');

        Db::startTrans();
        try {
            $row = Db::table('system_leases')->where('lease_name', $leaseName)->lock(true)->find();
            if ($row === null) {
                Db::table('system_leases')->insert([
                    'lease_name' => $leaseName,
                    'holder_token' => $token,
                    'lease_expires_at' => $expiresAt,
                    'revision' => 0,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
                Db::commit();
                return $token;
            }

            $currentExpiry = new \DateTimeImmutable((string) $row['lease_expires_at']);
            if ($currentExpiry > $now) {
                Db::rollback();
                return null;
            }

            Db::table('system_leases')->where('lease_name', $leaseName)->update([
                'holder_token' => $token,
                'lease_expires_at' => $expiresAt,
                'revision' => (int) $row['revision'] + 1,
                'updated_at' => $timestamp,
            ]);
            Db::commit();
            return $token;
        } catch (\Throwable $exception) {
            Db::rollback();
            throw $exception;
        }
    }

    public function release(string $leaseName, string $holderToken): void
    {
        $leaseName = $this->leaseName($leaseName);
        if (!preg_match('/^[a-f0-9]{64}$/', $holderToken)) {
            throw new \InvalidArgumentException('System Lease holder token 不合法');
        }
        $lock = $this->executionLocks[$leaseName] ?? null;
        if (!$this->ownsExecution($leaseName, $holderToken)) return;
        try {
            Db::table('system_leases')->where('lease_name', $leaseName)->where('holder_token', $holderToken)->delete();
        } finally {
            $lock['connection']->query('SELECT RELEASE_LOCK(?)', [$lock['name']]);
            unset($this->executionLocks[$leaseName]);
        }
    }

    public function renew(
        string $leaseName,
        string $holderToken,
        \DateTimeImmutable $now,
        int $ttlSeconds
    ): bool {
        $leaseName = $this->leaseName($leaseName);
        if (!preg_match('/^[a-f0-9]{64}$/', $holderToken)) {
            throw new \InvalidArgumentException('System Lease holder token 不合法');
        }
        if ($ttlSeconds < 5 || $ttlSeconds > 3600) {
            throw new \InvalidArgumentException('System Lease TTL 必须为 5..3600 秒');
        }
        if (!$this->ownsExecution($leaseName, $holderToken)) return false;
        $this->bootstrap();

        $timestamp = $now->format('Y-m-d H:i:s.u');
        $expiresAt = $now->modify('+' . $ttlSeconds . ' seconds')->format('Y-m-d H:i:s.u');
        $updated = Db::table('system_leases')
            ->where('lease_name', $leaseName)
            ->where('holder_token', $holderToken)
            ->where('lease_expires_at', '>', $timestamp)
            ->update([
                'lease_expires_at' => $expiresAt,
                'revision' => Db::raw('revision + 1'),
                'updated_at' => $timestamp,
            ]);
        return $updated === 1;
    }

    private function bootstrap(): void
    {
        if ($this->bootstrapped) {
            return;
        }
        Db::execute("CREATE TABLE IF NOT EXISTS `system_leases` (`lease_name` VARCHAR(128) NOT NULL, `holder_token` CHAR(64) NOT NULL, `lease_expires_at` DATETIME(6) NOT NULL, `revision` INT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME(6) NOT NULL, `updated_at` DATETIME(6) NOT NULL, PRIMARY KEY (`lease_name`), KEY `idx_system_leases_expiry` (`lease_expires_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->bootstrapped = true;
    }

    private function leaseName(string $leaseName): string
    {
        $leaseName = trim($leaseName);
        if (!preg_match('/^[a-z][a-z0-9_.:-]{0,127}$/', $leaseName)) {
            throw new \InvalidArgumentException('System Lease 名称不合法');
        }
        return $leaseName;
    }
}
