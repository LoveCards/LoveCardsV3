<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

use app\shared\Transport\Http\UploadLimitDiagnostics;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$postExceeded = UploadLimitDiagnostics::detect(
    'file',
    [],
    ['CONTENT_LENGTH' => '8388609'],
    '8M',
    '8M'
);
$assert(($postExceeded['reason'] ?? null) === 'post_max_size', 'post_max_size truncation was not diagnosed');
$assert(($postExceeded['content_length'] ?? null) === 8388609, 'content length was not preserved in diagnostic');

$uploadExceeded = UploadLimitDiagnostics::detect(
    'file',
    ['file' => ['error' => UPLOAD_ERR_INI_SIZE]],
    ['CONTENT_LENGTH' => '100'],
    '8M',
    '4M'
);
$assert(($uploadExceeded['reason'] ?? null) === 'upload_max_filesize', 'upload_max_filesize error was not diagnosed');

$underLimit = UploadLimitDiagnostics::detect(
    'file',
    [],
    ['CONTENT_LENGTH' => '1024'],
    '8M',
    '4M'
);
$assert($underLimit === null, 'a body below the PHP limit was incorrectly rejected');

$assert(UploadLimitDiagnostics::parseBytes('512M') === 536870912, 'PHP size parser did not parse MiB shorthand');
$assert(UploadLimitDiagnostics::parseBytes('0') === null, 'unlimited zero directive should not produce a finite limit');

fwrite(STDOUT, "PASS oversized multipart requests receive actionable PHP limit diagnostics\n");
fwrite(STDOUT, "PASS upload-limit parser preserves finite and unlimited directive semantics\n2 upload-limit diagnostics tests passed.\n");
