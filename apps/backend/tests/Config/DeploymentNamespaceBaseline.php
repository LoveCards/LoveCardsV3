<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use app\modules\Platform\Config\Public\DeploymentNamespace;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$reject = static function (string $id) use ($assert): void {
    try {
        DeploymentNamespace::fromId($id);
    } catch (InvalidArgumentException) {
        return;
    }
    $assert(false, 'invalid deployment identity must be rejected');
};

$first = DeploymentNamespace::fromId('fixture-deployment-0001');
$same = DeploymentNamespace::fromId('fixture-deployment-0001');
$second = DeploymentNamespace::fromId('fixture-deployment-0002');
$assert($first->realtimeChannel() === $same->realtimeChannel(), 'same site must retain its Redis channel');
$assert($first->realtimeChannel() !== $second->realtimeChannel(), 'different sites must use different Redis channels');
$assert($first->cachePrefix() !== $second->cachePrefix(), 'different sites must isolate Redis cache keys');
$assert($first->scheduledLock() !== $second->scheduledLock(), 'different sites must isolate MySQL cron locks');
$assert(preg_match('/^lc:[a-f0-9]{32}:realtime$/D', $first->realtimeChannel()) === 1, 'channel must match Gateway contract');
$assert(str_starts_with($first->cachePrefix(), 'lc:') && str_ends_with($first->cachePrefix(), ':cache:'), 'cache prefix must be site scoped');
$assert(strlen($first->scheduledLock()) <= 64, 'MySQL named lock must fit the 64-byte limit');
$assert(!str_contains($first->realtimeChannel(), 'fixture-deployment'), 'derived channel must not expose raw identity');

foreach (['', 'short', 'Fixture-deployment-0001', 'fixture/deployment/0001', str_repeat('a', 65)] as $invalid) {
    $reject($invalid);
}

putenv('LC_DEPLOYMENT_ID=fixture-deployment-0001');
$assert(DeploymentNamespace::current()->scheduledLock() === $first->scheduledLock(), 'environment identity must match explicit derivation');
putenv('LC_DEPLOYMENT_ID=');
$reject('');
putenv('LC_DEPLOYMENT_ID');

fwrite(STDOUT, "PASS deployment namespace baseline\n");
