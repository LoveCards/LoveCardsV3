<?php
declare(strict_types=1);

require __DIR__ . '/../../app/shared/Authorization/Scope.php';
require __DIR__ . '/../../app/shared/Authorization/Grant.php';
require __DIR__ . '/../../app/shared/Authorization/GrantSet.php';

use app\shared\Authorization\Grant;
use app\shared\Authorization\GrantSet;
use app\shared\Authorization\Scope;

$tests = [];
$test = static function (string $name, \Closure $callback) use (&$tests): void { $tests[$name] = $callback; };
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new \RuntimeException($message);
};

$test('own grant does not authorize all scope', static function () use ($assert): void {
    $grants = new GrantSet([new Grant('cards.update', Scope::OWN)]);
    $assert($grants->allows('cards.update', Scope::OWN), 'own scope should be allowed');
    $assert(!$grants->allows('cards.update', Scope::ALL), 'all scope should be denied');
});

$test('all grant includes own scope', static function () use ($assert): void {
    $grants = new GrantSet([new Grant('cards.update', Scope::ALL)]);
    $assert($grants->allows('cards.update', Scope::OWN), 'all scope should include own');
    $assert($grants->allows('cards.update', Scope::ALL), 'all scope should be allowed');
});

$test('database rows map to structured grants', static function () use ($assert): void {
    $grants = GrantSet::fromRows([
        ['capability' => 'cards.update', 'scope' => Scope::OWN],
        ['capability' => 'cards.read', 'scope' => Scope::ALL],
        ['capability' => 'config.update', 'scope' => Scope::GLOBAL],
    ]);
    $assert($grants->allows('cards.update', Scope::OWN), 'own row should be allowed');
    $assert($grants->allows('cards.read', Scope::ALL), 'all row should be allowed');
    $assert($grants->allows('config.update', Scope::GLOBAL), 'global row should be allowed');
});

$test('allOf and anyOf use explicit scope', static function () use ($assert): void {
    $grants = new GrantSet([
        new Grant('admin.access', Scope::GLOBAL),
        new Grant('cards.read', Scope::ALL),
    ]);
    $requirements = [
        ['capability' => 'admin.access', 'scope' => Scope::GLOBAL],
        ['capability' => 'cards.read', 'scope' => Scope::ALL],
    ];
    $assert($grants->allowsAll($requirements), 'all requirements should be allowed');
    $assert($grants->allowsAny([['capability' => 'cards.update', 'scope' => Scope::ALL], $requirements[0]]), 'one requirement should match');
});

$failures = 0;
foreach ($tests as $name => $callback) {
    try {
        $callback();
        fwrite(STDOUT, "PASS {$name}\n");
    } catch (\Throwable $exception) {
        $failures++;
        fwrite(STDERR, "FAIL {$name}: {$exception->getMessage()}\n");
    }
}

if ($failures > 0) exit(1);
fwrite(STDOUT, count($tests) . " GrantSet baseline tests passed.\n");
