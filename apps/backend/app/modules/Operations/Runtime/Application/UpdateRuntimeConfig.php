<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Application;

use app\api\ApiException;
use app\modules\Operations\Runtime\Contract\RuntimeConfigStore;
use app\modules\Operations\Runtime\Domain\CommunicationSettings;

/**
 * 配置校验与应用：白名单校验 → env 锁检查 → revision CAS → 同事务审计。
 * 失败不回写 effective（它每次由 Resolver 派生），只记 last_apply_* 快照。
 */
final class UpdateRuntimeConfig
{
    public function __construct(
        private RuntimeConfigStore $store,
        private ResolveRuntimeCommunication $resolver
    ) {}

    /** @return array{valid:bool,violations:array<int,array{field:string,message:string}>} */
    public function validate(array $input): array
    {
        $violations = CommunicationSettings::validateInput($input);
        if ($violations === [] && ($input['desired_mode'] ?? null) === 'realtime') {
            $current = $this->store->current();
            if ($current === null) {
                $violations[] = ['field' => 'desired_mode', 'message' => '运行时配置不可用'];
            } elseif ($current['desired_mode'] !== 'realtime') {
                $settings = CommunicationSettings::merge($current['settings'], array_intersect_key($input, array_flip(CommunicationSettings::INPUT_KEYS)));
                $reason = $this->resolver->realtimeBlockingReason($settings);
                if ($reason !== null) {
                    $violations[] = ['field' => 'desired_mode', 'message' => "实时链路未就绪：{$reason}"];
                }
            }
        }
        return ['valid' => $violations === [], 'violations' => $violations];
    }

    /**
     * @param callable(array):void $audit 同事务审计写入（payload=before/after/revision/_ip）
     */
    public function apply(int $actorId, array $input, callable $audit): array
    {
        $violations = CommunicationSettings::validateInput($input);
        if ($violations !== []) {
            $this->store->recordApply('rejected', (string) ($violations[0]['field'] ?? 'invalid'));
            $grouped = [];
            foreach ($violations as $violation) {
                $grouped[$violation['field']][] = $violation['message'];
            }
            throw ApiException::validation($grouped, '运行参数校验失败', 'runtime-config-invalid');
        }

        $desired = (string) $input['desired_mode'];
        $locked = ResolveRuntimeCommunication::envLockedToPolling();
        if ($locked && $desired !== 'polling') {
            $this->store->recordApply('rejected', 'config-locked');
            throw new ApiException(
                '运行模式已被环境变量锁定为 polling，后台切换被拒绝',
                ApiException::CODE_CONFIG_CONFLICT,
                ['reason_code' => 'config-locked']
            );
        }

        $current = $this->store->current();
        if ($current === null) {
            throw new ApiException('运行时配置存储不可用', ApiException::CODE_CONFIG_CONFLICT, ['reason_code' => 'schema-not-ready']);
        }

        $expected = (int) $input['expected_revision'];
        if ((int) $current['revision'] !== $expected) {
            throw new ApiException('配置已被其他操作修改，请刷新后重试', ApiException::CODE_CONFIG_CONFLICT, ['reason_code' => 'stale-revision']);
        }
        $settings = CommunicationSettings::merge($current['settings'], array_intersect_key($input, array_flip(CommunicationSettings::INPUT_KEYS)));
        if ($desired === 'realtime' && $current['desired_mode'] !== 'realtime') {
            $reason = $this->resolver->realtimeBlockingReason($settings);
            if ($reason !== null) {
                $this->store->recordApply('rejected', $reason);
                throw new ApiException('实时链路未就绪，无法开启', ApiException::CODE_CONFIG_CONFLICT, ['reason_code' => $reason]);
            }
        }
        $before = ['desired_mode' => $current['desired_mode'], 'settings' => CommunicationSettings::normalize($current['settings'])];

        $applied = $this->store->compareAndSwap($expected, $desired, $settings, $actorId, static function () use ($actorId, $before, $desired, $settings, $expected, $audit): void {
            $audit($actorId, [
                'before' => $before,
                'after' => ['desired_mode' => $desired, 'settings' => $settings],
                'revision_from' => $expected,
                'revision_to' => $expected + 1,
            ]);
        });
        if (!$applied) {
            throw new ApiException(
                '配置已被其他操作修改，请刷新后重试',
                ApiException::CODE_CONFIG_CONFLICT,
                ['reason_code' => 'stale-revision']
            );
        }

        return $this->resolver->snapshot();
    }
}
