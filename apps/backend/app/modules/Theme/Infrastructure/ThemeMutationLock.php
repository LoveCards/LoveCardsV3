<?php
declare(strict_types=1);

namespace app\modules\Theme\Infrastructure;

/** 主题写入频率低，统一互斥可同时保护跨 Host 绑定与 Release 文件。 */
final class ThemeMutationLock implements \app\modules\Theme\Contract\ThemeMutationLock
{
    public function run(callable $operation): mixed
    {
        $root = FilesystemThemeCatalog::root();
        if (!is_dir($root) && !mkdir($root, 0755, true) && !is_dir($root)) throw new \RuntimeException('无法创建主题锁目录');
        $path = $root . DIRECTORY_SEPARATOR . '.management.lock';
        if (is_link($path)) throw new \RuntimeException('主题锁路径不安全');
        $handle = fopen($path, 'c');
        if ($handle === false) throw new \RuntimeException('无法获取主题管理锁');
        $locked = false;
        try {
            $deadline = microtime(true) + 5;
            do {
                $locked = flock($handle, LOCK_EX | LOCK_NB);
                if ($locked) return $operation();
                usleep(20000);
            } while (microtime(true) < $deadline);
            throw new \RuntimeException('另一个主题操作正在执行，请稍后重试');
        } finally {
            if ($locked) flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
