<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use app\modules\Platform\Auth\Public\InstallationAccount;

$seed = file_get_contents(dirname(__DIR__, 2) . '/database/seed.sql');
if (!is_string($seed) || !str_contains($seed, "'admin@lovecards.cn', '', '超级管理员', '!'")) {
    throw new RuntimeException('root seed must use a non-login password placeholder');
}
if (password_verify('123456', '!')) {
    throw new RuntimeException('root seed placeholder unexpectedly permits default login');
}
foreach (['', '123456', str_repeat('a', 129)] as $invalid) {
    try {
        InstallationAccount::setRootPassword($invalid);
    } catch (InvalidArgumentException) {
        continue;
    }
    throw new RuntimeException('invalid initial root password was accepted');
}
echo "PASS initial root password requires an explicit non-default credential\n";
