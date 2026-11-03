<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/app/modules/Content/Mentions/Domain/MentionParser.php';

use app\modules\Content\Mentions\Domain\MentionParser;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$parser = new MentionParser();
$handles = $parser->parse([
    '欢迎 @张三丰、@user_name 和 @dash-user，一封 mail@example.com 不应成为提及。',
    '重复的 @USER_NAME 不应产生第二项。',
]);

$assert($handles === ['张三丰', 'user_name', 'dash-user'], 'parser should keep valid unique handles in source order and exclude email addresses');
$assert($parser->parse(['@ab @abcdefghijklmn @valid12']) === ['valid12'], 'parser should enforce the 3..12 handle length rule');
$assert($parser->parse(['字母相连x@someone 不算，标点后：@someone 算。']) === ['someone'], 'parser should require a real mention boundary');
$supplementaryCjk = "\u{20000}\u{20001}\u{20002}";
$assert($parser->parse(['@' . $supplementaryCjk]) === [$supplementaryCjk], 'parser should accept the supplementary CJK range supported by chsDash');
$assert($parser->parse(["@\u{4DB6}\u{4DB7}\u{4DB8}"]) === [], 'parser should reject characters outside the canonical chsDash range');
$assert($parser->parse(['@abcé']) === [], 'parser should not truncate an invalid Unicode handle into a valid partial mention');
$occurrences = $parser->occurrences('前缀😀 @TestAdmin 和 @张三丰');
$assert($occurrences === [
    ['username' => 'TestAdmin', 'start' => 4, 'end' => 14],
    ['username' => '张三丰', 'start' => 17, 'end' => 21],
], 'occurrence offsets should use Unicode code points and include the @ token');

fwrite(STDOUT, "PASS mention parser baseline\n");
