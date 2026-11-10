<?php
declare(strict_types=1);

namespace app\modules\Operations\Administration\Public;

use app\modules\Platform\Notifications\Public\NotificationPolicies;
use app\modules\Platform\Eventing\Public\SignalPolicies;
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

    /** 前端主动更新目录和开关，独立于站内通知生成策略。 */
    public static function signals(): array
    {
        return SignalPolicies::catalog();
    }

    public static function applySignals(array $input, int $actorId): array
    {
        $changes = $input['signals'] ?? null;
        $revision = $input['expected_revision'] ?? null;
        if (!is_array($changes) || !is_int($revision) && !ctype_digit((string) $revision)) {
            throw \app\api\ApiException::validation(['signals' => ['主动更新策略格式不正确']]);
        }
        try {
            return SignalPolicies::apply($changes, (int) $revision, $actorId,
                static function (array $before, array $after, int $nextRevision) use ($actorId): void {
                    app(AuditLogger::class)->record($actorId, 'communications.signals.update', 'signal_policy_state', '1', [
                        'before' => $before,
                        'after' => $after,
                        'revision' => $nextRevision,
                        '_ip' => request()->ip(),
                    ]);
                }
            );
        } catch (\InvalidArgumentException $exception) {
            throw \app\api\ApiException::validation(['signals' => [$exception->getMessage()]]);
        } catch (\RuntimeException $exception) {
            if ($exception->getMessage() === 'stale-revision') {
                throw \app\api\ApiException::error('策略已被其他操作更新，请刷新后重试', \app\api\ApiException::CODE_CONFIG_CONFLICT, ['reason_code' => 'stale-revision']);
            }
            throw $exception;
        }
    }
}
