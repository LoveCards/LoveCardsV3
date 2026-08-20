<?php

namespace app\shared\Infrastructure\Persistence;

use app\shared\Transaction\TransactionManager;
use think\facade\Db;

final class ThinkOrmTransactionManager implements TransactionManager
{
    public function run(callable $callback): mixed
    {
        Db::startTrans();
        try {
            $result = $callback();
            Db::commit();
            return $result;
        } catch (\Throwable $exception) {
            Db::rollback();
            throw $exception;
        }
    }
}
