<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Infrastructure;

use app\modules\Operations\SystemLifecycle\Contract\MigrationSource;

final class MigrationCatalog implements MigrationSource
{
    /** @return SqlFileMigration[] */
    public function discover(string $directory): array
    {
        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($files, SORT_STRING); $result = [];
        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            if (!preg_match('/^([0-9]{14}(?:_[a-z0-9][a-z0-9_-]{0,63})?)$/', $name, $m)) continue;
            $result[] = new SqlFileMigration($m[1], $file, 'SQL migration ' . $name);
        }
        return $result;
    }
}
