<?php

namespace app\shared\Transaction;

interface TransactionManager
{
    public function run(callable $callback): mixed;
}
