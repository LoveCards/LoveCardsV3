<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Contract;

use app\modules\Operations\SystemLifecycle\Domain\BackfillBatch;
use app\modules\Operations\SystemLifecycle\Domain\MigrationDescriptor;

/**
 * PHP-native Migration 必须幂等：进程可能在副作用完成、账本落盘前退出。
 * 数据库或文件系统依赖应通过 Migration 实现的构造函数注入。
 */
interface Migration
{
    public function descriptor(): MigrationDescriptor;
    public function preflight(): void;
    public function expand(): void;
    public function backfill(?string $cursor): BackfillBatch;
    public function verify(): void;
    public function contract(): void;
}
