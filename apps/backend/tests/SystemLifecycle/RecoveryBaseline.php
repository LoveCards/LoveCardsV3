<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

use app\modules\Operations\SystemLifecycle\Application\RecoveryKernel;
use app\shared\SystemPaths\SystemPaths;

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lc-recovery-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700, true);
$paths = new SystemPaths($tmp . DIRECTORY_SEPARATOR . 'instance', $tmp . DIRECTORY_SEPARATOR . 'code');
$releaseOld = $paths->releaseRoot('release-old');
$releaseNew = $paths->releaseRoot('release-new');
mkdir($releaseOld, 0750, true);
mkdir($releaseNew, 0750, true);
file_put_contents($releaseOld . DIRECTORY_SEPARATOR . 'release.json', '{"release_id":"release-old"}');
file_put_contents($releaseNew . DIRECTORY_SEPARATOR . 'release.json', '{"release_id":"release-new"}');
mkdir($paths->backupsRoot(), 0700, true);
$backup = $paths->backupsRoot() . DIRECTORY_SEPARATOR . 'plan-pointer.pointer';
file_put_contents($backup, "release-old\n");
$checksum = hash_file('sha256', $backup);
$kernel = new RecoveryKernel($paths);
$failures = 0;
try {
    $kernel->activate('release-new');
    if ($kernel->activeReleaseId() !== 'release-new') throw new RuntimeException('new release pointer was not activated');
    $restored = $kernel->restore($backup, $checksum);
    if ($restored !== 'release-old' || $kernel->activeReleaseId() !== 'release-old') throw new RuntimeException('pointer backup was not restored');
    try { $kernel->restore($backup, str_repeat('0', 64)); throw new RuntimeException('invalid checksum accepted'); }
    catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'invalid checksum accepted') throw $exception;
    }
    fwrite(STDOUT, "PASS RecoveryKernel atomically activates and verifies pointer restore\n1 RecoveryKernel test passed.\n");
} catch (Throwable $exception) {
    $failures++;
    fwrite(STDERR, "FAIL RecoveryKernel: {$exception->getMessage()}\n");
} finally {
    $cleanup = static function (string $directory) use (&$cleanup): void {
        if (!is_dir($directory) || is_link($directory)) { if (is_file($directory)) @unlink($directory); return; }
        foreach (scandir($directory) ?: [] as $name) {
            if ($name === '.' || $name === '..') continue;
            $path = $directory . DIRECTORY_SEPARATOR . $name;
            if (is_dir($path) && !is_link($path)) $cleanup($path); else @unlink($path);
        }
        @rmdir($directory);
    };
    $cleanup($tmp);
}
if ($failures > 0) exit(1);
