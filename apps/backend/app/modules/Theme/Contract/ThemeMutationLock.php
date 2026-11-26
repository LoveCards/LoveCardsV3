<?php
declare(strict_types=1);

namespace app\modules\Theme\Contract;

/** 串行主题文件与绑定变更，锁范围必须覆盖数据库事务的提交。 */
interface ThemeMutationLock
{
    public function run(callable $operation): mixed;
}
