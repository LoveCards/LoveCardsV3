<?php
declare(strict_types=1);

namespace app\modules\Operations\Administration\Transport\Api;

use app\shared\Transport\Http\Resource;

/** Public administration projection of a durable upgrade plan. */
final class AdminUpgradePlanResource extends Resource
{
    public static function schema(string $variant = 'default'): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['plan_id', 'release_id', 'revision', 'status', 'current_step', 'contract_approved', 'steps'],
            'properties' => [
                'plan_id' => ['type' => 'string'],
                'release_id' => ['type' => 'string'],
                'previous_release_id' => ['type' => ['string', 'null']],
                'revision' => ['type' => 'integer'],
                'status' => ['type' => 'string'],
                'current_step' => ['type' => ['string', 'null']],
                'contract_approved' => ['type' => 'boolean'],
                'created_by' => ['type' => ['integer', 'null']],
                'package' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => [
                        'checksum' => ['type' => ['string', 'null']],
                        'size_bytes' => ['type' => ['integer', 'null']],
                    ],
                ],
                'milestones' => ['type' => 'object', 'additionalProperties' => true],
                'steps' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['name', 'state', 'attempts'],
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'state' => ['type' => 'string'],
                            'attempts' => ['type' => 'integer'],
                            'cursor' => ['type' => ['string', 'null']],
                            'failure_code' => ['type' => ['string', 'null']],
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        if (!is_array($data)) throw new \UnexpectedValueException('Upgrade Plan 响应无效');

        $steps = [];
        $current = null;
        foreach ((array) ($data['steps'] ?? []) as $step) {
            if (!is_array($step)) continue;
            $state = (string) ($step['state'] ?? 'pending');
            $row = [
                'name' => (string) ($step['name'] ?? ''),
                'state' => $state,
                'attempts' => (int) ($step['attempts'] ?? 0),
                'cursor' => isset($step['cursor']) ? (string) $step['cursor'] : null,
                'failure_code' => isset($step['failure_code']) ? (string) $step['failure_code'] : null,
            ];
            if ($current === null && !in_array($state, ['succeeded', 'skipped'], true)) $current = $row['name'];
            $steps[] = $row;
        }

        $context = is_array($data['context'] ?? null) ? $data['context'] : [];
        $package = is_array($context['package'] ?? null) ? $context['package'] : [];
        $milestoneKeys = [
            'created_at', 'environment_verified_at', 'verified_at', 'staged_at',
            'schema_checked_at', 'preflight_migrations_completed_at',
            'expand_migrations_completed_at', 'backfill_migrations_completed_at',
            'migrations_verified_at', 'backup_gate_at', 'activated_at',
            'health_checked_at', 'contract_executed_at',
            'contract_migrations_completed_at', 'recovered_at', 'restored_release_id',
            'runtime_drained', 'runtime_reloaded',
        ];
        $milestones = [];
        foreach ($milestoneKeys as $key) {
            if (array_key_exists($key, $context)) $milestones[$key] = $context[$key];
        }

        return [
            'plan_id' => (string) ($data['plan_id'] ?? ''),
            'release_id' => (string) ($data['release_id'] ?? ''),
            'previous_release_id' => isset($data['previous_release_id']) ? (string) $data['previous_release_id'] : null,
            'revision' => (int) ($data['revision'] ?? 0),
            'status' => (string) ($data['status'] ?? 'pending'),
            'current_step' => $current,
            'contract_approved' => (bool) ($data['contract_approved'] ?? false),
            'created_by' => isset($data['created_by']) ? (int) $data['created_by'] : null,
            'package' => [
                'checksum' => isset($package['checksum']) ? (string) $package['checksum'] : null,
                'size_bytes' => isset($package['size_bytes']) ? (int) $package['size_bytes'] : null,
            ],
            'milestones' => $milestones,
            'steps' => $steps,
        ];
    }
}
