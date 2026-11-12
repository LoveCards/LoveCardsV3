<?php
declare(strict_types=1);

namespace app\shared\Infrastructure\Persistence;

use think\facade\Db;

/** 安装与已审核迁移共用的 SQL 执行器；不根据 schema 推断语句。 */
final class SqlStatementExecutor
{
    public static function execute(string $sql): void
    {
        if (trim($sql) === '') throw new \InvalidArgumentException('SQL 文件为空');
        foreach (self::splitStatements($sql) as $statement) {
            // SQL 文件不绑定参数；MySQL 不允许通过预处理协议执行 PREPARE/EXECUTE。
            $pdoStatement = Db::connect()->getPdo()->query($statement);
            $pdoStatement->closeCursor();
        }
    }

    /** 普通 SQL 分句；历史迁移的 DELIMITER 指令在此处理。 */
    public static function splitStatements(string $sql): array
    {
        $sql = preg_replace('/^\s*(?:--|#).*(?:\r?\n|$)/m', '', $sql) ?? $sql;
        $result = [];
        $buffer = '';
        $quote = null;
        $delimiter = ';';
        foreach (preg_split('/\R/u', $sql) ?: [] as $line) {
            if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $directive)) {
                $delimiter = $directive[1];
                continue;
            }
            $line .= "\n";
            $length = strlen($line);
            for ($i = 0; $i < $length; $i++) {
                $char = $line[$i];
                if ($quote === null && substr($line, $i, strlen($delimiter)) === $delimiter) {
                    $statement = trim($buffer);
                    if ($statement !== '') $result[] = $statement;
                    $buffer = '';
                    $i += strlen($delimiter) - 1;
                    continue;
                }
                if ($quote !== null) {
                    $buffer .= $char;
                    if ($char === '\\' && $i + 1 < $length) { $buffer .= $line[++$i]; continue; }
                    if ($char === $quote) $quote = null;
                    continue;
                }
                if ($char === "'" || $char === '"' || $char === '`') $quote = $char;
                $buffer .= $char;
            }
        }
        $statement = trim($buffer);
        if ($statement !== '') $result[] = $statement;
        return $result;
    }
}
