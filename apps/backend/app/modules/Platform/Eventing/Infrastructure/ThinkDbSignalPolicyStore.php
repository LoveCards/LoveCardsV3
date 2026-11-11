<?php
declare(strict_types=1);

namespace app\modules\Platform\Eventing\Infrastructure;

use think\facade\Db;
use app\modules\Platform\Eventing\Contract\SignalModePort;
use app\modules\Platform\Eventing\Public\DeliveryContext;
use app\modules\Platform\Eventing\Public\SignalTransportUnavailable;

/** 单行 CAS 配置；事件和 Handler 的代码注册仍是目录权威。 */
final class ThinkDbSignalPolicyStore
{
    public function catalog(array $registrations): array
    {
        return $this->renderCatalog($registrations, $this->current());
    }

    private function renderCatalog(array $registrations, array $state): array
    {
        $items = [];
        foreach ($registrations as $registration) {
            $key = (string) $registration['key'];
            $items[] = $registration + [
                'enabled' => array_key_exists($key, $state['overrides'])
                    ? (bool) $state['overrides'][$key]
                    : (bool) $registration['default_enabled'],
            ];
        }
        return ['revision' => $state['revision'], 'signals' => $items];
    }

    public function enabled(string $key, array $registrations): bool
    {
        foreach ($registrations as $registration) {
            if ($registration['key'] !== $key) continue;
            $state = $this->current();
            return array_key_exists($key, $state['overrides'])
                ? (bool) $state['overrides'][$key]
                : (bool) $registration['default_enabled'];
        }
        throw new \InvalidArgumentException('未注册的主动更新信号');
    }

    /** @param callable(array,array,int):void $audit 在同一数据库事务中写审计。 */
    public function applyChanges(array $registrations, array $changes, int $expectedRevision, int $actorId, callable $audit): array
    {
        if ($expectedRevision < 1 || $changes === []) throw new \InvalidArgumentException('策略版本或变更为空');
        $known = [];
        foreach ($registrations as $registration) $known[$registration['key']] = (bool) $registration['default_enabled'];
        $parsed = [];
        foreach ($changes as $change) {
            if (!is_array($change) || !is_string($change['key'] ?? null)
                || !array_key_exists($change['key'], $known)
                || !is_bool($change['enabled'] ?? null)
                || array_key_exists($change['key'], $parsed)) {
                throw new \InvalidArgumentException('主动更新策略包含未知、重复或非法的规则');
            }
            $parsed[$change['key']] = $change['enabled'];
        }

        $updatedState = Db::transaction(function () use ($parsed, $known, $expectedRevision, $actorId, $audit): array {
            $row = Db::table('signal_policy_state')->where('id', 1)->lock(true)->find();
            if ($row === null || (int) $row['revision'] !== $expectedRevision) {
                throw new \RuntimeException('stale-revision');
            }
            $before = $this->decode($row['overrides_json']);
            $generations = $this->decodeGenerations($row['generations_json']);
            foreach ($parsed as $key => $enabled) {
                if (($before[$key] ?? $known[$key]) && !$enabled) {
                    $generation = $generations[$key] ?? 1;
                    if ($generation >= PHP_INT_MAX) throw new \OverflowException('信号资格代次已达到上限');
                    $generations[$key] = $generation + 1;
                }
            }
            ksort($generations, SORT_STRING);
            $after = array_merge($before, $parsed);
            ksort($after, SORT_STRING);
            $updated = Db::table('signal_policy_state')->where('id', 1)->where('revision', $expectedRevision)->update([
                'overrides_json' => json_encode($after, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'generations_json' => json_encode((object) $generations, JSON_THROW_ON_ERROR),
                'revision' => $expectedRevision + 1,
                'updated_by' => $actorId,
                'updated_at' => gmdate('Y-m-d H:i:s.u'),
            ]);
            if ($updated !== 1) throw new \RuntimeException('stale-revision');
            $audit($before, $after, $expectedRevision + 1);
            return ['revision' => $expectedRevision + 1, 'overrides' => $after];
        });
        return $this->renderCatalog($registrations, $updatedState);
    }

    /** 登记者须先锁通信模式；本方法只锁信号策略，不做外部探测。 */
    public function registrationState(array $signal): array
    {
        $row = Db::table('signal_policy_state')->where('id', 1)->lock(true)->find();
        if (!is_array($row)) throw new \RuntimeException('主动更新策略尚未初始化');
        $overrides = $this->decode($row['overrides_json']);
        $generations = $this->decodeGenerations($row['generations_json']);
        $key = (string) $signal['key'];
        return ['key' => $key, 'enabled' => $overrides[$key] ?? (bool) $signal['default_enabled'],
            'generation' => $generations[$key] ?? 1];
    }

    /** 发出网络请求前的短事务授权；顺序固定为通信、信号、投递。 */
    public function authorize(array $signal, DeliveryContext $context): bool
    {
        return Db::transaction(function () use ($signal, $context): bool {
            $row = $this->eligibleDelivery($signal, $context);
            if ($row === null) return false;
            $remaining = (float) (new \DateTimeImmutable($row['lease_expires_at'], new \DateTimeZone('UTC')))->format('U.u') - microtime(true);
            $context->grantSignal($signal['key'], (int) $row['signal_generation'], $remaining);
            return true;
        });
    }

    /** 父投递锁内去重，续批只能继承仍有效的原始资格。 */
    public function continueSignal(array $signal, DeliveryContext $context, callable $record): mixed
    {
        $origin = $context->signalOrigin();
        if ($origin['key'] !== $signal['key']) throw new \LogicException('续批信号身份不匹配');
        return Db::transaction(function () use ($signal, $context, $origin, $record): mixed {
            $row = $this->eligibleDelivery($signal, $context);
            if ($row === null) return null;
            $context->assertSignalWindow();
            if ((int) $row['signal_generation'] !== $origin['generation']) throw new \LogicException('续批资格代次不匹配');
            return $record($origin + ['signal' => $signal]);
        });
    }

    private function eligibleDelivery(array $signal, DeliveryContext $context): ?array
    {
        $mode = app(SignalModePort::class)->selectedModeForEnqueue();
        if ($mode === 'polling') return null;
        if ($mode !== 'realtime') throw new SignalTransportUnavailable('通信模式不可用');
        $state = $this->registrationState($signal);
        $row = Db::table('event_deliveries')->where('id', $context->deliveryId())->lock(true)->find();
        if (!is_array($row) || $context->claimToken() === null || ($row['claim_token'] ?? null) !== $context->claimToken()
            || ($row['status'] ?? null) !== 'processing'
            || new \DateTimeImmutable($row['lease_expires_at'], new \DateTimeZone('UTC')) <= new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) {
            throw new SignalTransportUnavailable('投递租约已失效');
        }
        if (!$state['enabled'] || ($row['signal_key'] ?? null) !== $state['key']
            || (int) ($row['signal_generation'] ?? 0) !== $state['generation']
            || ($row['policy_decision'] ?? null) === 'skipped') return null;
        return $row;
    }

    private function decodeGenerations(mixed $json): array
    {
        $value = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($value)) throw new \UnexpectedValueException('信号资格数据损坏');
        foreach ($value as $key => $generation) {
            if (!is_string($key) || !is_int($generation) || $generation < 1) throw new \UnexpectedValueException('信号资格数据损坏');
        }
        return $value;
    }

    private function current(): array
    {
        $row = Db::table('signal_policy_state')->where('id', 1)->find();
        if ($row === null) throw new \RuntimeException('主动更新策略尚未初始化');
        return ['revision' => (int) $row['revision'], 'overrides' => $this->decode($row['overrides_json'])];
    }

    private function decode(mixed $json): array
    {
        $value = json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($value)) throw new \UnexpectedValueException('主动更新策略数据损坏');
        foreach ($value as $key => $enabled) {
            if (!is_string($key) || !is_bool($enabled)) throw new \UnexpectedValueException('主动更新策略数据损坏');
        }
        return $value;
    }
}
