<?php
declare(strict_types=1);

namespace app\modules\Platform\Eventing\Public;

use app\modules\Platform\Eventing\Infrastructure\ThinkDbSignalPolicyStore;

/** 主动更新目录及开关的公开入口；目录只来自 HandlerRegistry。 */
final class SignalPolicies
{
    public static function catalog(): array
    {
        return (new ThinkDbSignalPolicyStore())->catalog(app(HandlerRegistry::class)->signalCatalog());
    }

    public static function enabled(string $key): bool
    {
        return (new ThinkDbSignalPolicyStore())->enabled($key, app(HandlerRegistry::class)->signalCatalog());
    }

    /** 只授权当前持租约批次；发布必须在返回后进行，不把网络 I/O 放入事务。 */
    public static function authorize(string $key, DeliveryContext $context): bool
    {
        return (new ThinkDbSignalPolicyStore())->authorize(self::registration($key), $context);
    }

    public static function continueSignal(string $key, DeliveryContext $context, callable $record): mixed
    {
        return (new ThinkDbSignalPolicyStore())->continueSignal(self::registration($key), $context, $record);
    }

    private static function registration(string $key): array
    {
        foreach (app(HandlerRegistry::class)->signalCatalog() as $registration) {
            if ($registration['key'] === $key) return $registration;
        }
        throw new \InvalidArgumentException('未注册的主动更新信号');
    }

    public static function apply(array $changes, int $expectedRevision, int $actorId, callable $audit): array
    {
        return (new ThinkDbSignalPolicyStore())->applyChanges(
            app(HandlerRegistry::class)->signalCatalog(),
            $changes,
            $expectedRevision,
            $actorId,
            $audit
        );
    }
}
