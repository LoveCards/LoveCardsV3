<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Public;

use app\modules\Platform\Notifications\Application\ManageNotificationPolicies;
use app\modules\Platform\Notifications\Contract\NotificationPolicyStore;

/**
 * 通知策略管理门面（供 Host Controller 调用）。
 */
final class NotificationPolicies
{
    private static function useCase(): ManageNotificationPolicies
    {
        return new ManageNotificationPolicies(app(NotificationPolicyStore::class));
    }

    /** @return array{revision: ?int, policies: array<int, array<string, mixed>>} */
    public static function list(): array
    {
        return self::useCase()->list();
    }

    /** @return array{revision: int, policies: array<int, array<string, mixed>>, before: array<int, array<string, mixed>>} */
    public static function apply(array $input, ?int $actorId): array
    {
        return self::useCase()->apply($input, $actorId);
    }
}
