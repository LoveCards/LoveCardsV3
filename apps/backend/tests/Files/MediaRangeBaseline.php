<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

use app\frontend\response\LocalFileResponse;

$path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lc-media-range-' . bin2hex(random_bytes(6));
file_put_contents($path, '0123456789');
$etag = '"range-test"';
$failures = 0;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$body = static function (LocalFileResponse $response): string {
    $method = new ReflectionMethod($response, 'sendData');
    $method->setAccessible(true);
    ob_start();
    try { $method->invoke($response, ''); return (string) ob_get_contents(); }
    finally { ob_end_clean(); }
};

$tests = [
    'full response advertises byte ranges' => static function () use ($path, $etag, $assert, $body): void {
        $response = new LocalFileResponse($path, 'video/mp4', $etag);
        $assert($response->getCode() === 200, 'full response status mismatch');
        $assert($response->getHeader('Accept-Ranges') === 'bytes', 'Accept-Ranges missing');
        $assert($response->getHeader('Content-Length') === '10', 'full length mismatch');
        $assert($body($response) === '0123456789', 'full body mismatch');
    },
    'explicit range streams only the selected bytes' => static function () use ($path, $etag, $assert, $body): void {
        $response = new LocalFileResponse($path, 'video/mp4', $etag, false, 'bytes=2-5');
        $assert($response->getCode() === 206, 'range status mismatch');
        $assert($response->getHeader('Content-Range') === 'bytes 2-5/10', 'Content-Range mismatch');
        $assert($response->getHeader('Content-Length') === '4', 'range length mismatch');
        $assert($body($response) === '2345', 'range body mismatch');
    },
    'suffix and HEAD ranges keep correct headers without excess output' => static function () use ($path, $etag, $assert, $body): void {
        $suffix = new LocalFileResponse($path, 'video/mp4', $etag, false, 'bytes=-3');
        $assert($suffix->getHeader('Content-Range') === 'bytes 7-9/10', 'suffix range mismatch');
        $assert($body($suffix) === '789', 'suffix body mismatch');
        $head = new LocalFileResponse($path, 'video/mp4', $etag, true, 'bytes=0-1');
        $assert($head->getCode() === 206 && $body($head) === '', 'HEAD range emitted a body');
    },
    'invalid or multi ranges fail closed with 416' => static function () use ($path, $etag, $assert, $body): void {
        foreach (['bytes=20-30', 'bytes=7-2', 'bytes=0-1,3-4'] as $range) {
            $response = new LocalFileResponse($path, 'video/mp4', $etag, false, $range);
            $assert($response->getCode() === 416, 'invalid range was accepted: ' . $range);
            $assert($response->getHeader('Content-Range') === 'bytes */10', '416 range size missing');
            $assert($body($response) === '', '416 response emitted a body');
        }
    },
];

try {
    foreach ($tests as $name => $test) {
        try { $test(); fwrite(STDOUT, "PASS {$name}\n"); }
        catch (Throwable $exception) { $failures++; fwrite(STDERR, "FAIL {$name}: {$exception->getMessage()}\n"); }
    }
} finally {
    @unlink($path);
}

if ($failures > 0) exit(1);
fwrite(STDOUT, count($tests) . " media range tests passed.\n");
