<?php

declare(strict_types=1);

namespace app\shared\Infrastructure\Concurrency;

use app\api\ApiException;
use think\facade\Db;

final class NamedLock
{
    public static function run(string $key, callable $operation): mixed
    {
        $connection = Db::connect();
        $name = hash('sha256', 'lovecards:' . $connection->getConfig('database') . ':' . $key);
        $rows = $connection->query('SELECT GET_LOCK(?, 2) AS acquired', [$name]);
        if ((int) ($rows[0]['acquired'] ?? 0) !== 1) {
            throw ApiException::tooManyRequests('操作正在处理，请稍后重试', 'operation-in-progress', 2);
        }

        try {
            return $operation();
        } finally {
            $connection->query('SELECT RELEASE_LOCK(?)', [$name]);
        }
    }
}
