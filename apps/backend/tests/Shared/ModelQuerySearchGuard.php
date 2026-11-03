<?php
declare(strict_types=1);

// ============================================================
// ModelQuery 搜索字段守卫（源码契约断言）
//
// 分层：格式白名单在 PaginationInput::searchKey（422，见
// tests/Administration/AdminMutationValidationBaseline.php 的行为断言）；
// 查询层兜底要求 search() 的每个字段分支先与模型真实列求交集，
// 未知列不得直达 SQL（历史上产生可控 500 噪音）。
//
// 说明：search() 为私有方法且依赖活库连接，无法在不重写实现的前提下
// 注入 fake 连接做行为测试，故本文件锁定源码结构契约；端到端行为由
// 管理端列表搜索 e2e（search 用例）覆盖。
// ============================================================

$root = dirname(__DIR__, 2);
$src = (string) file_get_contents($root . '/app/shared/Infrastructure/Persistence/ModelQuery.php');

$failures = 0;
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures++;
        fwrite(STDERR, "FAIL {$message}\n");
    }
};

$assert(str_contains($src, 'private function availableSearchFields'), 'availableSearchFields guard must exist');
$assert(str_contains($src, 'getTableFields'), 'guard must resolve real table columns via getTableFields');
$assert(str_contains($src, 'array_intersect($search_keys, $columns)'), 'guard must intersect requested keys with actual columns');
$assert(preg_match('/\$fields\s*=\s*\$this->availableSearchFields\(/', $src) === 1, 'null-value search branch must pass through the guard');
$assert(preg_match('/\$search_keys\s*=\s*\$this->availableSearchFields\(\$search_keys\);\s*\n\s*if \(\$search_keys\)/', $src) === 1, 'fuzzy multi-field branch must pass through the guard before whereLike');

if ($failures > 0) exit(1);
fwrite(STDOUT, "ModelQuery search field guard contract passed.\n");
