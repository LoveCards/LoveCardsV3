<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

use app\modules\Operations\SystemLifecycle\Domain\BackupArtifact;
use app\modules\Operations\SystemLifecycle\Infrastructure\LocalBackupArtifactStore;
use app\modules\Platform\Storage\Infrastructure\Driver\LocalDriver;
use app\shared\SystemPaths\LocalStoragePath;
use app\shared\SystemPaths\SystemPaths;
use think\file\UploadedFile;

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lc-storage-backup-' . bin2hex(random_bytes(6));
$code = $tmp . DIRECTORY_SEPARATOR . 'code';
$instance = $tmp . DIRECTORY_SEPARATOR . 'instance';
mkdir($code, 0700, true);
$paths = new SystemPaths($instance, $code);
$localChannel = static fn(): array => [
    'slug' => 'local',
    'type' => 'local',
    'root' => '../ignored-in-lifecycle',
    'url_prefix' => '/storage',
    'max_file_size' => 1048576,
    'allow_mime_types' => 'text/plain',
];
$failures = 0;
$tests = [];

$tests['lifecycle local driver writes and deletes in shared storage'] = static function () use ($paths, $code, $tmp, $localChannel): void {
    putenv('LC_INSTANCE_ROOT=' . $paths->instanceRoot());
    $source = $tmp . DIRECTORY_SEPARATOR . 'upload.txt';
    file_put_contents($source, 'persistent-media');
    $driver = new LocalDriver('local', $localChannel(), $code, $paths);
    $result = $driver->upload(new UploadedFile($source, 'upload.txt', 'text/plain', UPLOAD_ERR_OK, true), '2026/08/item.txt');
    $stored = $paths->storageRoot() . DIRECTORY_SEPARATOR . '2026' . DIRECTORY_SEPARATOR . '08' . DIRECTORY_SEPARATOR . 'item.txt';
    if (!is_file($stored) || file_get_contents($stored) !== 'persistent-media') throw new RuntimeException('upload did not reach shared storage');
    if ($result->url !== '/storage/2026/08/item.txt') throw new RuntimeException('local URL is not stable and relative');
    if ($driver->resolveLocalFile('../item.txt') !== null) throw new RuntimeException('traversal path resolved');
    if (!$driver->delete('2026/08/item.txt') || file_exists($stored)) throw new RuntimeException('shared storage delete failed');
};

$tests['non-empty local files backup verifies and restores atomically'] = static function () use ($paths, $code, $localChannel): void {
    putenv('LC_INSTANCE_ROOT=' . $paths->instanceRoot());
    $storage = $paths->storageRoot();
    mkdir($storage . DIRECTORY_SEPARATOR . 'nested', 0750, true);
    file_put_contents($storage . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'one.txt', 'one');
    file_put_contents($storage . DIRECTORY_SEPARATOR . 'two.bin', "two\0bytes");
    $store = new LocalBackupArtifactStore($paths, $localChannel, $code);
    $artifact = $store->files('plan-files');
    $store->verify($artifact);
    if (($artifact->metadata()['entries'] ?? 0) !== 2) throw new RuntimeException('non-empty backup entry count is wrong');

    file_put_contents($storage . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'one.txt', 'changed');
    file_put_contents($storage . DIRECTORY_SEPARATOR . 'stale.txt', 'stale');
    $store->restoreFiles($artifact);
    if (file_get_contents($storage . DIRECTORY_SEPARATOR . 'nested' . DIRECTORY_SEPARATOR . 'one.txt') !== 'one') throw new RuntimeException('original file was not restored');
    if (file_get_contents($storage . DIRECTORY_SEPARATOR . 'two.bin') !== "two\0bytes") throw new RuntimeException('binary file was not restored');
    if (file_exists($storage . DIRECTORY_SEPARATOR . 'stale.txt')) throw new RuntimeException('restore did not replace the directory snapshot');
};

$tests['tampered archive path is rejected even with refreshed artifact checksum'] = static function () use ($paths, $code, $localChannel): void {
    putenv('LC_INSTANCE_ROOT=' . $paths->instanceRoot());
    $store = new LocalBackupArtifactStore($paths, $localChannel, $code);
    $artifact = $store->files('plan-tampered');
    $zip = new ZipArchive();
    if ($zip->open($artifact->location()) !== true) throw new RuntimeException('cannot open fixture backup');
    $zip->addFromString('../escape.txt', 'escape');
    $zip->close();
    $tampered = new BackupArtifact(
        $artifact->backupId(),
        'files',
        $artifact->driver(),
        $artifact->location(),
        hash_file('sha256', $artifact->location()),
        filesize($artifact->location()),
        $artifact->metadata()
    );
    try {
        $store->verify($tampered);
        throw new RuntimeException('unsafe archive path accepted');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'unsafe archive path accepted') throw $exception;
    }
    if (file_exists($paths->instanceRoot() . DIRECTORY_SEPARATOR . 'escape.txt')) throw new RuntimeException('archive escaped during verification');
};

$tests['remote default storage declares files backup unsupported and blocks it'] = static function () use ($paths, $code): void {
    putenv('LC_INSTANCE_ROOT=' . $paths->instanceRoot());
    $remote = new LocalBackupArtifactStore($paths, static fn(): array => ['slug' => 'oss', 'type' => 'oss'], $code);
    $capabilities = $remote->capabilities();
    if (($capabilities['files']['backup'] ?? true) !== false || ($capabilities['database']['restore'] ?? true) !== false) {
        throw new RuntimeException('backup/restore capability boundary is inaccurate');
    }
    try {
        $remote->files('remote-files');
        throw new RuntimeException('remote storage produced a fake local backup');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'remote storage produced a fake local backup') throw $exception;
    }
};

$tests['legacy development root remains inside public storage without lifecycle environment'] = static function () use ($paths, $code): void {
    putenv('LC_INSTANCE_ROOT');
    $resolved = LocalStoragePath::root($paths, $code, ['root' => 'public/storage']);
    $expected = $code . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'storage';
    if ($resolved !== $expected) throw new RuntimeException('legacy development storage root changed');
    try {
        LocalStoragePath::root($paths, $code, ['root' => '../outside']);
        throw new RuntimeException('unsafe configured root accepted');
    } catch (RuntimeException $exception) {
        if ($exception->getMessage() === 'unsafe configured root accepted') throw $exception;
    }
};

try {
    foreach ($tests as $name => $test) {
        try {
            $test();
            fwrite(STDOUT, "PASS {$name}\n");
        } catch (Throwable $exception) {
            $failures++;
            fwrite(STDERR, "FAIL {$name}: {$exception->getMessage()}\n");
        }
    }
} finally {
    putenv('LC_INSTANCE_ROOT');
    $cleanup = static function (string $path) use (&$cleanup): void {
        if (is_link($path) || is_file($path)) { @unlink($path); return; }
        if (!is_dir($path)) return;
        foreach (scandir($path) ?: [] as $name) {
            if ($name === '.' || $name === '..') continue;
            $cleanup($path . DIRECTORY_SEPARATOR . $name);
        }
        @rmdir($path);
    };
    $cleanup($tmp);
}

if ($failures > 0) exit(1);
fwrite(STDOUT, count($tests) . " storage lifecycle tests passed.\n");
