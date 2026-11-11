<?php

declare(strict_types=1);

namespace app\modules\Platform\Notifications\Application;

use app\api\ApiException;
use app\modules\Platform\Notifications\Contract\NotificationPolicyStore;

/**
 * 管理端策略目录用例：列表 + CAS 批量启停（契约 §5.2）。
 * locked=1（安全/审核/账号类）不可修改；身份与目录不一致时拒绝提交。
 */
final class ManageNotificationPolicies
{
    public function __construct(private NotificationPolicyStore $store)
    {
    }

    /** @return array{revision: ?int, policies: array<int, array<string, mixed>>} */
    public function list(): array
    {
        return ['revision' => $this->store->currentRevision(), 'policies' => $this->store->listCurrent()];
    }

    /**
     * @param array{expected_revision?: mixed, policies?: mixed, reason?: mixed} $input
     * @return array{revision: int, policies: array<int, array<string, mixed>>, before: array<int, array<string, mixed>>}
     */
    public function apply(array $input, ?int $actorId, callable $audit): array
    {
        $expected = isset($input['expected_revision']) ? (int) $input['expected_revision'] : 0;
        if ($expected < 1) {
            throw ApiException::validation([['field' => 'expected_revision', 'message' => '缺少或非法的策略版本号，请先刷新']]);
        }
        $rows = $input['policies'] ?? null;
        if (!is_array($rows) || $rows === []) {
            throw ApiException::validation([['field' => 'policies', 'message' => '缺少要变更的策略规则']]);
        }
        $changes = [];
        $violations = [];
        $seen = [];
        foreach (array_values($rows) as $index => $row) {
            if (!is_array($row)) {
                $violations[] = ['field' => "policies.{$index}", 'message' => '策略规则格式非法'];
                continue;
            }
            $eventType = is_string($row['event_type'] ?? null) ? trim($row['event_type']) : '';
            $ruleKey = is_string($row['rule_key'] ?? 'notify') ? trim($row['rule_key'] ?? 'notify') : '';
            $versionInput = $row['event_version'] ?? 1;
            $eventVersion = filter_var($versionInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($eventType === '' || preg_match('/^[a-z][a-z0-9_.-]{0,127}$/', $eventType) !== 1
                || (!is_int($versionInput) && !is_string($versionInput)) || $eventVersion === false) {
                $violations[] = ['field' => "policies.{$index}.event_type", 'message' => '事件类型非法'];
                continue;
            }
            if (isset($seen[$eventType])) {
                $violations[] = ['field' => "policies.{$index}.event_type", 'message' => '同一事件规则不能重复提交'];
                continue;
            }
            $seen[$eventType] = true;
            if (!is_bool($row['enabled'] ?? null)) {
                $violations[] = ['field' => "policies.{$index}.enabled", 'message' => '通知开关必须是布尔值'];
                continue;
            }
            if (!is_string($row['rule_key'] ?? 'notify') || $ruleKey === '') {
                $violations[] = ['field' => "policies.{$index}.rule_key", 'message' => '通知规则标识无效'];
                continue;
            }
            $changes[] = [
                'event_type' => $eventType,
                'event_version' => $eventVersion,
                'rule_key' => $ruleKey,
                'enabled' => $row['enabled'],
            ];
        }
        if ($violations !== []) {
            throw ApiException::validation($violations);
        }
        $reason = isset($input['reason']) ? mb_substr(trim((string) $input['reason']), 0, 255) : null;
        try {
            return $this->store->applyChanges($changes, $expected, $actorId, $reason, $audit);
        } catch (\RuntimeException $exception) {
            // 存储层把 CAS 失败与锁定行统一抛出，转成稳定 API 语义。
            if (str_contains($exception->getMessage(), 'stale-revision')) {
                throw ApiException::error('策略已被其他操作更新，请刷新后重试', ApiException::CODE_CONFIG_CONFLICT, ['reason_code' => 'stale-revision']);
            }
            if (str_contains($exception->getMessage(), 'locked')) {
                throw ApiException::validation([['field' => 'policies', 'message' => '安全、审核与账号类通知策略已锁定，不可关闭']]);
            }
            if (str_contains($exception->getMessage(), 'unknown-rule')) {
                throw ApiException::validation([['field' => 'policies', 'message' => '存在未注册的策略规则，请先刷新']]);
            }
            throw $exception;
        }
    }
}
