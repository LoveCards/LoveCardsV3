<?php
declare(strict_types=1);

namespace app\modules\Platform\Safety\Contract;

interface AdminReportRepository
{
    /** @return array{items:array<int,array<string,mixed>>,total:int} */
    public function list(array $filters, int $page, int $limit): array;

    /** @return array<string,mixed>|null */
    public function find(int $reportId): ?array;

    /** @return array<string,mixed>|null */
    public function lockForUpdate(int $reportId): ?array;

    /** @param array<string,mixed> $values */
    public function update(int $reportId, array $values): void;

    /** @param array<string,mixed> $payload */
    public function appendEvent(int $reportId, int $actorId, string $eventType, array $payload, \DateTimeImmutable $at): void;
}
