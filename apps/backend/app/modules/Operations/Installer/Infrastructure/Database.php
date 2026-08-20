<?php

namespace app\modules\Operations\Installer\Infrastructure;

use think\facade\Db;
use think\facade\Config;
use app\api\ApiException;
use app\shared\SystemPaths\SystemPaths;

class Database
{
    protected static function getErrorData($e)
    {
        $data = [
            'SQLSTATE' => null,
            'Code' => 0,
            'Message' => $e instanceof \Throwable ? $e->getMessage() : '数据库操作失败',
        ];
        if ($e instanceof \PDOException && is_array($e->errorInfo ?? null)) {
            $data['SQLSTATE'] = $e->errorInfo[0] ?? null;
            $data['Code'] = (int) ($e->errorInfo[1] ?? 0);
            $data['Message'] = (string) ($e->errorInfo[2] ?? $data['Message']);
            return $data;
        }
        if (is_object($e) && method_exists($e, 'getData')) {
            $payload = $e->getData();
            $pdoInfo = is_array($payload) ? ($payload['PDO Error Info'] ?? null) : null;
            if (is_array($pdoInfo)) {
                $data['SQLSTATE'] = $pdoInfo['SQLSTATE'] ?? $data['SQLSTATE'];
                $data['Code'] = (int) ($pdoInfo['Driver Error Code'] ?? $data['Code']);
                $data['Message'] = (string) ($pdoInfo['Driver Error Message'] ?? $data['Message']);
            }
        }
        return $data;
    }

    public static function Connect($hostname, $database, $username, $password, $hostport)
    {
        Config::set([
            'connections' => [
                'mysql' => [
                    'type' => 'mysql',
                    'hostname' => $hostname,
                    'database' => $database,
                    'username' => $username,
                    'password' => $password,
                    'hostport' => $hostport,
                    'params' => [\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true],
                    // Community data and migration metadata are utf8mb4; using
                    // the legacy three-byte charset makes valid Chinese input
                    // fail during installation/upgrade.
                    'charset' => 'utf8mb4',
                    'prefix' => '',
                    'deploy' => 0,
                    'rw_separate' => false,
                    'master_num' => 1,
                    'slave_no' => '',
                    'fields_strict' => true,
                    'break_reconnect' => false,
                    'trigger_sql' => true,
                    'fields_cache' => false,
                ]
            ]
        ], 'database');

        try {
            Db::connect()->query('SELECT 1');
            return true;
        } catch (\Exception $e) {
            $result = self::getErrorData($e);
            if ($result['Code'] == 1045) {
                throw ApiException::error('连接出错，请检查数据库信息是否存在错误', ApiException::CODE_SYSTEM_ERROR);
            }
            throw ApiException::error('数据库连接失败', ApiException::CODE_SYSTEM_ERROR);
        }
    }

    public static function Clear(): bool
    {
        $connection = Db::connect();
        $database = $connection->getConfig('database');
        $tables = $connection->getTables($database);
        try {
            foreach ($tables as $table) {
                Db::execute("DROP TABLE IF EXISTS `{$table}`");
            }
            return true;
        } catch (\Throwable $th) {
            throw ApiException::error('清空数据库失败', ApiException::CODE_SYSTEM_ERROR);
        }
    }

    public static function UpdataConfig($hostname, $database, $username, $password, $hostport, ?SystemPaths $paths = null): bool
    {
        try {
            $connection = self::normalizeConfiguration($hostname, $database, $username, $password, $hostport);
            $document = json_encode(
                ['schema_version' => 1, 'driver' => 'mysql', 'connection' => $connection],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ) . "\n";
            $paths = PersistentFile::paths($paths);
            PersistentFile::write($paths->configRoot(), 'database.json', $document, 0600);
            return true;
        } catch (\Throwable $th) {
            throw ApiException::error('配置文件写入失败', ApiException::CODE_SYSTEM_ERROR);
        }
    }

    /** @return array{hostname:string,database:string,username:string,password:string,hostport:string,charset:string} */
    private static function normalizeConfiguration($hostname, $database, $username, $password, $hostport): array
    {
        $hostname = trim((string) $hostname);
        $database = trim((string) $database);
        $username = trim((string) $username);
        $password = (string) $password;
        $hostport = trim((string) $hostport);
        foreach (['hostname' => $hostname, 'database' => $database, 'username' => $username] as $name => $value) {
            if ($value === '' || strlen($value) > 255 || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
                throw new \InvalidArgumentException($name . ' 不合法');
            }
        }
        if (strlen($password) > 4096 || str_contains($password, "\0")) throw new \InvalidArgumentException('password 不合法');
        if (preg_match('/^[0-9]{1,5}$/', $hostport) !== 1 || (int) $hostport < 1 || (int) $hostport > 65535) {
            throw new \InvalidArgumentException('hostport 不合法');
        }
        return [
            'hostname' => $hostname,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'hostport' => $hostport,
            'charset' => 'utf8mb4',
        ];
    }

    public static function ImportSQLFile($file): void
    {
        try {
            $sql = file_get_contents($file);
        } catch (\Exception $e) {
            throw ApiException::error('无法找到sql文件，请检查sql文件是否命名为"data.sql"并存在于程序根目录！', ApiException::CODE_PARAM_INVALID);
        }

        self::ImportSQL((string) $sql);
    }

    public static function ImportSQL(string $sql): void
    {
        try {
            foreach (self::splitStatements($sql) as $statement) {
                if ($statement !== '') {
                    $pdoStatement = Db::connect()->getPdo()->prepare($statement);
                    $pdoStatement->execute();
                    $pdoStatement->closeCursor();
                }
            }
        } catch (\Exception $e) {
            $errorData = self::getErrorData($e);
            throw ApiException::error($errorData['Message'], ApiException::CODE_SYSTEM_ERROR);
        }
    }

    /** Split ordinary SQL safely; DELIMITER directives are interpreted for legacy dumps. */
    private static function splitStatements(string $sql): array
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
                if ($char === "'" || $char === '"' || $char === '`') { $quote = $char; }
                $buffer .= $char;
            }
        }
        $statement = trim($buffer);
        if ($statement !== '') { $result[] = $statement; }
        return $result;
    }
}
