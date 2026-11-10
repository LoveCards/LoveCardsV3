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
