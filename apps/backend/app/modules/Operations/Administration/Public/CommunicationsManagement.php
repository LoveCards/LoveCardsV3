<?php
declare(strict_types=1);

namespace app\modules\Operations\Administration\Public;

use app\modules\Platform\Notifications\Public\NotificationPolicies;
use app\shared\Observability\AuditLogger;

/**
 * 通信管理用例门面（Host 适配层）。审计 before/after + 新 revision（契约 §5.2）。
 */
final class CommunicationsManagement
{
    /** @return array{revision: ?int, policies: array<int, array<string, mixed>>} */
    public static function policies(): array
    {
        return NotificationPolicies::list();
    }

    /** @return array{revision: int, policies: array<int, array<string, mixed>>} */
    public static function applyPolicies(array $input, int $actorId): array
    {
        $result = NotificationPolicies::apply($input, $actorId);
        app(AuditLogger::class)->record($actorId, 'communications.policies.update', 'notification_policies', (string) $result['revision'], [
            'before' => $result['before'],
            'after' => $result['policies'],
            'revision' => $result['revision'],
            'reason' => $input['reason'] ?? null,
            '_ip' => request()->ip(),
        ]);
        unset($result['before']);
        return $result;
    }
}
