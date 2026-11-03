<?php

declare(strict_types=1);

namespace app\modules\Platform\Files\Infrastructure;

use app\modules\Platform\Files\Contract\FilePurgeRepository;
use app\modules\Platform\Files\Domain\FileConstants;
use think\facade\Db;

final class ThinkDbFilePurgeRepository implements FilePurgeRepository
{
    public function lockForRequest(array $fileIds): array
    {
        $rows = Db::table('files')->whereIn('id', $fileIds)->order('id', 'asc')->lock(true)->select()->toArray();
        $result = [];
        foreach ($rows as $row) $result[(int) $row['id']] = $row;
        return $result;
    }

    public function markRequested(array $fileIds, \DateTimeImmutable $at): void
    {
        $updated = Db::table('files')->whereIn('id', $fileIds)->update([
            'purge_status' => FileConstants::PURGE_PENDING,
            'purge_attempts' => 0,
            'purge_requested_at' => $this->utc($at),
            'purge_storage_deleted_at' => null,
            'purge_error' => null,
            'updated_at' => $this->utc($at),
        ]);
        if ($updated !== count($fileIds)) throw new \RuntimeException('文件 purge 请求状态写入不完整');
    }

    public function claim(int $fileId, \DateTimeImmutable $at): ?array
    {
        $row = Db::table('files')->where('id', $fileId)->find();
        if ($row === null) return null;
        $state = (string) ($row['purge_status'] ?? '');
        if ($state === FileConstants::PURGE_STORAGE_DELETED) return $row;
        if (!in_array($state, [FileConstants::PURGE_PENDING, FileConstants::PURGE_PROCESSING, FileConstants::PURGE_FAILED], true)) return null;

        $updated = Db::table('files')->where('id', $fileId)->where('purge_status', $state)->update([
            'purge_status' => FileConstants::PURGE_PROCESSING,
            'purge_attempts' => Db::raw('purge_attempts + 1'),
            'purge_error' => null,
            'updated_at' => $this->utc($at),
        ]);
        if ($updated !== 1) return null;
        $row['purge_status'] = FileConstants::PURGE_PROCESSING;
        return $row;
    }

    public function markStorageDeleted(int $fileId, \DateTimeImmutable $at): void
    {
        $updated = Db::table('files')->where('id', $fileId)->where('purge_status', FileConstants::PURGE_PROCESSING)->update([
            'purge_status' => FileConstants::PURGE_STORAGE_DELETED,
            'purge_storage_deleted_at' => $this->utc($at),
            'purge_error' => null,
            'updated_at' => $this->utc($at),
        ]);
        if ($updated !== 1) throw new \RuntimeException('文件存储删除状态写入失败');
    }

    public function markFailed(int $fileId, string $error, \DateTimeImmutable $at): void
    {
        Db::table('files')->where('id', $fileId)->where('purge_status', FileConstants::PURGE_PROCESSING)->update([
            'purge_status' => FileConstants::PURGE_FAILED,
            'purge_error' => mb_substr(trim($error), 0, 255),
            'updated_at' => $this->utc($at),
        ]);
    }

    public function lockForFinalize(int $fileId): ?array
    {
        $row = Db::table('files')->where('id', $fileId)->lock(true)->find();
        return $row ?: null;
    }

    public function deleteRecord(int $fileId): void
    {
        $deleted = Db::table('files')->where('id', $fileId)->where('purge_status', FileConstants::PURGE_STORAGE_DELETED)->delete();
        if ($deleted !== 1) throw new \RuntimeException('文件 purge 数据库收尾失败');
    }

    private function utc(\DateTimeImmutable $at): string
    {
        return $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
