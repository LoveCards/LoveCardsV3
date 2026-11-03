<?php
declare(strict_types=1);

// ============================================================
// 评论置顶/取消置顶 行为级权限回归（审计 A7/T7）
//
// PermissionBoundaryBaseline 对 pin 只有源码字符串断言（"代码里有
// Gate/Scope 字样"），不证明"无权限者真的被拦下"。本文件真调用
// Comments::pinComment / pinOwnComment / unpinComment / unpinOwnComment，
// 断言异常业务码、数据写入与审计流水。授权对象 GrantSet/Scope/Grant
// 与 ApiException 均为真实类；仅数据库查询层用桩替换。
// ============================================================

namespace think\facade {
    class Db
    {
        public static int $transCount = 0;
        public static int $commitCount = 0;
        public static int $rollbackCount = 0;
        public static function startTrans(): bool { self::$transCount++; return true; }
        public static function commit(): bool { self::$commitCount++; return true; }
        public static function rollback(): bool { self::$rollbackCount++; return true; }
        public static function reset(): void { self::$transCount = 0; self::$commitCount = 0; self::$rollbackCount = 0; }
    }
}

namespace app\pin\fixture {
    #[\AllowDynamicProperties]
    class Row
    {
        public function __construct(array $attributes)
        {
            foreach ($attributes as $key => $value) {
                $this->{$key} = $value;
            }
        }
        public function isEmpty(): bool { return false; }
    }
    class EmptyRow
    {
        public function isEmpty(): bool { return true; }
    }
    class FakeQuery
    {
        /** @var list<array{table:string,data:array}> */
        public static array $updates = [];
        public function __construct(private string $table, private ?object $row) {}
        public function where(...$args): self { return $this; }
        public function field(...$args): self { return $this; }
        public function lock(...$args): self { return $this; }
        public function findOrEmpty(): object { return $this->row ?? new EmptyRow(); }
        public function find(): ?object { return $this->row; }
        public function update(array $data): int
        {
            self::$updates[] = ['table' => $this->table, 'data' => $data];
            return 1;
        }
        public static function reset(): void { self::$updates = []; }
    }
    class FakeAudit
    {
        /** @var list<array> */
        public static array $calls = [];
        public function recordChanges(int $actorId, string $action, string $target, array $before, array $after, string $ip = ''): void
        {
            self::$calls[] = compact('actorId', 'action', 'target', 'before', 'after', 'ip');
        }
        public static function reset(): void { self::$calls = []; }
    }
}

namespace app\modules\Content\Infrastructure\Persistence\Model {
    use app\pin\fixture\FakeQuery;
    class Cards
    {
        /** 当前场景下 id=cardId 的卡片行；null 表示查不到 */
        public static ?object $row = null;
        public static function where(...$args): FakeQuery { return new FakeQuery('cards', self::$row); }
    }
    class Comments
    {
        /** 当前场景下满足条件的评论行；null 表示查不到（含隐藏/回复场景） */
        public static ?object $row = null;
        public static function where(...$args): FakeQuery { return new FakeQuery('comments', self::$row); }
    }
}

namespace {
    use app\api\ApiException;
    use app\modules\Content\Comments\Service\Comments;
    use app\modules\Content\Infrastructure\Persistence\Model as ModelStub;
    use app\pin\fixture\FakeAudit;
    use app\pin\fixture\FakeQuery;
    use app\pin\fixture\Row;
    use app\shared\Authorization\Grant;
    use app\shared\Authorization\GrantSet;
    use app\shared\Authorization\Scope;
    use think\facade\Db;

    require dirname(__DIR__, 2) . '/app/api/ApiException.php';
    require dirname(__DIR__, 2) . '/app/shared/Authorization/Scope.php';
    require dirname(__DIR__, 2) . '/app/shared/Authorization/Grant.php';
    require dirname(__DIR__, 2) . '/app/shared/Authorization/GrantSet.php';
    require dirname(__DIR__, 2) . '/app/modules/Content/Comments/Service/Comments.php';

    if (!function_exists('app')) {
        function app(string $abstract) { return new FakeAudit(); }
    }

    $failures = 0;
    $assert = static function (bool $condition, string $message) use (&$failures): void {
        if (!$condition) {
            $failures++;
            fwrite(STDERR, "FAIL {$message}\n");
        }
    };
    $reset = static function (): void {
        Db::reset();
        FakeQuery::reset();
        FakeAudit::reset();
        ModelStub\Cards::$row = new Row(['id' => 7, 'user_id' => 42, 'status' => 0, 'pinned_comment_id' => 99]);
        ModelStub\Comments::$row = new Row(['id' => 55, 'aid' => 1, 'pid' => 7, 'parent_id' => 0, 'status' => 0]);
    };
    $grantSet = static fn(string $capability, string $scope): GrantSet => new GrantSet([new Grant($capability, $scope)]);
    $expectException = static function (callable $call, int $code, string $needle): ?ApiException {
        try {
            $call();
        } catch (ApiException $exception) {
            if ($exception->getCode() !== $code) {
                fwrite(STDERR, 'FAIL wrong code ' . $exception->getCode() . " expected {$code} message=" . $exception->getMessage() . "\n");
            }
            if (!str_contains($exception->getMessage(), $needle)) {
                fwrite(STDERR, "FAIL message '{$exception->getMessage()}' must contain '{$needle}'\n");
            }
            return $exception;
        }
        fwrite(STDERR, "FAIL expected ApiException code {$code}\n");
        $failures++;
        return null;
    };

    // 1) 无任何置顶权限 → 403，且不开事务、不写数据、不记审计
    $reset();
    $expectException(static fn() => Comments::pinComment(7, 55, 1, new GrantSet([])), 1101, '无权置顶');
    $assert(Db::$transCount === 0 && FakeQuery::$updates === [] && FakeAudit::$calls === [], 'denied pin must not touch data at all');

    // 2) 只有 OWN 授权调管理员置顶 → 403（scope 不满足）
    $reset();
    $expectException(static fn() => Comments::pinComment(7, 55, 42, $grantSet('comments.pin', Scope::OWN)), 1101, '无权置顶');

    // 3) ALL 授权置顶合规评论 → 写卡片指针 + 审计流水 + 提交事务
    $reset();
    Comments::pinComment(7, 55, 1, $grantSet('comments.pin', Scope::ALL), '10.0.0.1');
    $pinWrites = array_values(array_filter(FakeQuery::$updates, static fn($w) => $w['table'] === 'cards' && ($w['data']['pinned_comment_id'] ?? null) === 55));
    $assert($pinWrites !== [], 'authorized pin must write the card pointer');
    $assert(count(FakeAudit::$calls) === 1 && FakeAudit::$calls[0]['action'] === 'admin.comment.pin' && FakeAudit::$calls[0]['actorId'] === 1, 'pin must record one admin audit entry');
    $assert(FakeAudit::$calls[0]['before'] === [7 => ['card_id' => 7, 'comment_id' => 99]], 'audit must capture the previous pin');
    $assert(Db::$commitCount === 1, 'authorized pin must commit exactly one transaction');

    // 4) 卡片不存在/不可见 → 404
    $reset();
    ModelStub\Cards::$row = null;
    $expectException(static fn() => Comments::pinComment(7, 55, 1, $grantSet('comments.pin', Scope::ALL)), 9003, '卡片不存在');

    // 5) 评论不合规（回复/隐藏模拟为查不到）→ 400
    $reset();
    ModelStub\Comments::$row = null;
    $expectException(static fn() => Comments::pinComment(7, 55, 1, $grantSet('comments.pin', Scope::ALL)), 9002, '只能置顶当前卡片下可见的一级评论');

    // 6) 作者置顶完全无置顶授权 → 403
    $reset();
    $expectException(static fn() => Comments::pinOwnComment(7, 55, 42, new GrantSet([])), 1101, '无权置顶');
    // 说明：持有 ALL 授权时通过 OWN 检查是 GrantSet 的既定语义（ALL ⊇ OWN，
    // 高 scope 兼容低 scope 检查）；针对"有授权但越他人之卡"的防线在下一条归属校验。

    // 7) 有授权但卡片不是他的 → 403 归属拦截（核心越权场景）
    $reset();
    ModelStub\Cards::$row = new Row(['id' => 7, 'user_id' => 43, 'status' => 0, 'pinned_comment_id' => null]);
    $expectException(static fn() => Comments::pinOwnComment(7, 55, 42, $grantSet('comments.pin', Scope::OWN)), 1101, '只有卡片作者可以置顶评论');
    $assert(FakeQuery::$updates === [], 'foreign card pin attempt must not write anything');

    // 8) 卡片作者本人 + OWN → 成功写指针
    $reset();
    Comments::pinOwnComment(7, 55, 42, $grantSet('comments.pin', Scope::OWN));
    $ownWrites = array_values(array_filter(FakeQuery::$updates, static fn($w) => ($w['data']['pinned_comment_id'] ?? null) === 55));
    $assert($ownWrites !== [] && Db::$commitCount === 1, 'own-card pin must persist and commit');

    // 9) 管理员取消置顶：已有置顶 → 清空指针 + 审计
    $reset();
    Comments::unpinComment(7, 1, $grantSet('comments.pin', Scope::ALL), '10.0.0.1');
    $clearWrites = array_values(array_filter(FakeQuery::$updates, static fn($w) => $w['table'] === 'cards' && array_key_exists('pinned_comment_id', $w['data']) && $w['data']['pinned_comment_id'] === null));
    $assert($clearWrites !== [], 'authorized unpin must clear the card pointer');
    $assert(count(FakeAudit::$calls) === 1 && FakeAudit::$calls[0]['action'] === 'admin.comment.unpin', 'unpin must record one admin audit entry');

    // 10) 管理员取消不存在的置顶 → 幂等，无写入无审计但正常提交
    $reset();
    ModelStub\Cards::$row = new Row(['id' => 7, 'user_id' => 42, 'status' => 0, 'pinned_comment_id' => null]);
    Comments::unpinComment(7, 1, $grantSet('comments.pin', Scope::ALL));
    $assert(FakeQuery::$updates === [] && FakeAudit::$calls === [] && Db::$commitCount === 1, 'unpin without a pin must be idempotent');

    // 11) 作者取消置顶：非作者 403；作者成功清空
    $reset();
    $expectException(static fn() => Comments::unpinOwnComment(7, 43, $grantSet('comments.pin', Scope::OWN)), 1101, '只有卡片作者可以取消置顶评论');
    $reset();
    Comments::unpinOwnComment(7, 42, $grantSet('comments.pin', Scope::OWN));
    $ownClear = array_values(array_filter(FakeQuery::$updates, static fn($w) => ($w['data']['pinned_comment_id'] ?? null) === null));
    $assert($ownClear !== [] && Db::$commitCount === 1, 'own-card unpin must persist and commit');

    if ($failures > 0) exit(1);
    fwrite(STDOUT, "Comment pin authorization behavior passed.\n");
}
