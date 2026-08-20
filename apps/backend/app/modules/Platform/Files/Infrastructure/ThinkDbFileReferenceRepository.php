<?php
declare(strict_types=1);

namespace app\modules\Platform\Files\Infrastructure;

use app\modules\Platform\Files\Contract\FileReferenceRepository;
use think\facade\Db;

final class ThinkDbFileReferenceRepository implements FileReferenceRepository
{
    public function forFile(int $fileId): array
    {
        return Db::table('file_references')->where('file_id', $fileId)->order('id', 'asc')->select()->toArray();
    }

    public function fileIdsForResource(string $resourceType, string $resourceId): array
    {
        return array_map('intval', Db::table('file_references')
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->order('id', 'asc')
            ->column('file_id'));
    }

    public function fileIdsForResources(string $resourceType, array $resourceIds): array
    {
        $resourceIds = array_values(array_unique(array_filter(
            array_map('strval', $resourceIds),
            static fn(string $id): bool => $id !== ''
        )));
        if ($resourceIds === []) return [];

        $result = array_fill_keys($resourceIds, []);
        $rows = Db::table('file_references')
            ->where('resource_type', $resourceType)
            ->whereIn('resource_id', $resourceIds)
            ->field('resource_id,file_id')
            ->order('id', 'asc')
            ->select()
            ->toArray();
        foreach ($rows as $row) {
            $resourceId = (string) $row['resource_id'];
            if (isset($result[$resourceId])) $result[$resourceId][] = (int) $row['file_id'];
        }

        return $result;
    }

    public function countForFile(int $fileId): int
    {
        return (int) Db::table('file_references')->where('file_id', $fileId)->count();
    }

    public function replaceResource(
        string $resourceType,
        string $resourceId,
        string $purpose,
        array $fileIds,
        int $ownerId,
        bool $isPublic,
        bool $allowAll
    ): void {
        $fileIds = array_values(array_unique(array_filter(array_map('intval', $fileIds), static fn(int $id): bool => $id > 0)));
        $existingIds = $this->fileIdsForResource($resourceType, $resourceId);
        $lockedRows = $fileIds === [] ? [] : Db::table('files')
            ->whereIn('id', $fileIds)
            ->whereNull('deleted_at')
            ->whereNull('purge_status')
            // 与 purge 请求按同一 files 主键加锁，避免“检查无引用”后并发新增引用。
            ->order('id', 'asc')
            ->lock(true)
            ->select()
            ->toArray();
        if (count($lockedRows) !== count($fileIds)) throw new \DomainException('引用的文件不存在');
        $byId = [];
        foreach ($lockedRows as $row) $byId[(int) $row['id']] = $row;
        $rows = array_map(static fn(int $fileId): array => $byId[$fileId], $fileIds);
        foreach ($rows as $row) {
            if (!$allowAll && (int) $row['user_id'] !== $ownerId) throw new \DomainException('无权引用其他用户的文件');
        }

        Db::table('file_references')->where('resource_type', $resourceType)->where('resource_id', $resourceId)->where('purpose', $purpose)->delete();
        if ($rows !== []) {
            $now = date('Y-m-d H:i:s');
            Db::table('file_references')->insertAll(array_map(static fn(array $row): array => [
                'file_id' => (int) $row['id'],
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'purpose' => $purpose,
                'is_public' => $isPublic ? 1 : 0,
                'created_at' => $now,
            ], $rows));
        }

        $affected = array_values(array_unique(array_merge($existingIds, $fileIds)));
        foreach ($affected as $fileId) {
            $publicReference = Db::table('file_references')->where('file_id', $fileId)->where('is_public', 1)->find() !== null;
            $primary = Db::table('file_references')->where('file_id', $fileId)->order('id', 'asc')->find();
            Db::table('files')->where('id', $fileId)->update([
                'ref_type' => $primary['resource_type'] ?? null,
                'ref_id' => isset($primary['resource_id']) && ctype_digit((string) $primary['resource_id']) ? (int) $primary['resource_id'] : null,
                'is_public' => $publicReference ? 1 : 0,
            ]);
        }
    }
}
