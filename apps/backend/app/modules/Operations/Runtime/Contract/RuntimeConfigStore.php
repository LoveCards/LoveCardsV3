<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Contract;

/**
 * 运行时通信配置的持久化端口。
 *
 * 单例行（id=1）乐观并发：compareAndSwap 以 revision 为 CAS 凭据，
 * 表缺失或不可读时 current() 返回 null（Resolver 走 fail-safe 派生）。
 */
interface RuntimeConfigStore
{
    /**
     * @return array{desired_mode:string,settings:array,revision:int,last_apply_state:string,last_apply_reason:?string}|null
     */
    public function current(): ?array;

    /**
     * CAS 写入。$then 在同一事务内执行（用于同事务审计）。
     *
     * @param array<string,mixed> $settings
     */
    public function compareAndSwap(int $expectedRevision, string $desiredMode, array $settings, ?int $actorId, ?callable $then = null): bool;

    /** 记录校验拒绝/失败结果快照（不递增 revision）。 */
    public function recordApply(string $state, ?string $reason): void;
}
