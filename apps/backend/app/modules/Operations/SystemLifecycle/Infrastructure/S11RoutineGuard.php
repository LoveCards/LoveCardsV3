<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Infrastructure;

use app\modules\Operations\SystemLifecycle\Domain\MigrationDescriptor;
use app\shared\Infrastructure\Persistence\SqlStatementExecutor;
use think\facade\Db;

/** 在保持受审 SQL 字节和账本校验和的前提下兼容 MySQL 5.7 的过程归属检查。 */
final class S11RoutineGuard
{
    private const MIGRATIONS = [
        '20261112000001' => [
            'checksum' => 'ed3ab560fcce38071284d0c15219351bde51e19906fad8c8156f6c7535e8d0dd',
            'phases' => ['preflight', 'verify', 'contract'],
            'table' => 'lc_s11_owner_guard',
        ],
        '20261112000002' => [
            'checksum' => 'a1ee58d217861fe500b124c9e46504e7dbd19ddbd163f912cc4ff310ab6c3ae4',
            'phases' => ['contract'],
            'table' => 'lc_s12_owner_guard',
        ],
    ];

    public static function applies(string $id, string $phase): bool
    {
        return in_array($phase, self::MIGRATIONS[$id]['phases'] ?? [], true);
    }

    public static function execute(MigrationDescriptor $descriptor, string $phase, string $sql): void
    {
        $guard = self::MIGRATIONS[$descriptor->id()];
        if (!hash_equals($guard['checksum'], $descriptor->checksum())) {
            throw new \LogicException('S11 过程归属检查与受审迁移校验和不一致');
        }

        $statements = SqlStatementExecutor::splitStatements($sql);
        $insert = "INSERT INTO `{$guard['table']}` (`id`)";
        $prefix = $insert . "\nSELECT IF(EXISTS (";
        $guardStatements = array_filter($statements, static fn(string $statement): bool => str_starts_with($statement, $prefix));
        if (count($guardStatements) !== 1) {
            throw new \LogicException("S11 {$phase} 缺少唯一的过程归属检查");
        }
        $guardIndex = array_key_first($guardStatements);
        $connection = Db::connect()->getPdo();
        foreach ($statements as $index => $statement) {
            if ($index !== $guardIndex) {
                SqlStatementExecutor::executeStatement($connection, $statement);
                continue;
            }

            // MySQL 5.7 在 INSERT ... SELECT 查询 ROUTINES 时可能返回 1264。
            // 单独读取后写入同一个临时表，保留原有重复主键阻断语义。
            $query = $connection->query(substr($statement, strlen($insert) + 1));
            $value = $query->fetchColumn();
            $query->closeCursor();
            if (!in_array($value, [1, 2, '1', '2'], true)) {
                throw new \LogicException('S11 过程归属检查返回无效结果');
            }
            SqlStatementExecutor::executeStatement($connection, $insert . ' VALUES (?)', [(int) $value]);
        }
    }
}
