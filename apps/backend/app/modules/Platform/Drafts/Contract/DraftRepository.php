<?php
declare(strict_types=1);
namespace app\modules\Platform\Drafts\Contract;
interface DraftRepository { public function save(int $userId, ?int $id, string $title, string $content, array $payload = []): int; public function find(int $userId, int $id): ?array; public function list(int $userId, ?string $cursor = null, int $limit = 20): array; public function delete(int $userId, int $id): bool; }
