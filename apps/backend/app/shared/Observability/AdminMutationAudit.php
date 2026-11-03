<?php
declare(strict_types=1);

namespace app\shared\Observability;

/** Records successful administration state transitions through the central audit sink. */
final class AdminMutationAudit
{
    public function __construct(private AuditLogger $logger)
    {
    }

    /**
     * @param array<int|string,array<string,mixed>|null> $beforeById
     * @param array<int|string,array<string,mixed>|null> $afterById
     */
    public function recordChanges(
        int $actorId,
        string $action,
        string $resourceType,
        array $beforeById,
        array $afterById,
        string $ipAddress = ''
    ): int {
        $ids = array_values(array_unique(array_merge(array_keys($beforeById), array_keys($afterById))));
        usort($ids, static fn($left, $right): int => strnatcmp((string) $left, (string) $right));

        $recorded = 0;
        foreach ($ids as $id) {
            $before = $beforeById[$id] ?? null;
            $after = $afterById[$id] ?? null;
            if ($this->comparable($before) === $this->comparable($after)) continue;

            $this->logger->record($actorId, $action, $resourceType, (string) $id, [
                'before' => $before,
                'after' => $after,
                '_ip' => $ipAddress,
            ]);
            $recorded++;
        }
        return $recorded;
    }

    /** @param array<string,mixed>|null $snapshot */
    private function comparable(?array $snapshot): ?array
    {
        if ($snapshot === null) return null;
        unset($snapshot['created_at'], $snapshot['updated_at']);
        return $this->sortRecursively($snapshot);
    }

    /** @return array<mixed> */
    private function sortRecursively(array $value): array
    {
        if (!$this->isList($value)) ksort($value);
        foreach ($value as &$item) {
            if (is_array($item)) $item = $this->sortRecursively($item);
        }
        unset($item);
        return $value;
    }

    private function isList(array $value): bool
    {
        if ($value === []) return true;
        return array_keys($value) === range(0, count($value) - 1);
    }
}
