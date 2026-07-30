<?php

namespace app\api\infrastructure\Files;

use app\api\application\Files\FileRepository;
use app\api\application\Files\FileConstants;
use app\api\model\Files as FilesModel;
use app\common\support\ModelList;
use think\facade\Db;

/**
 * 基于 ThinkORM 的文件仓库实现
 *
 * 所有 Files Model 和 Db facade 的直接调用集中在此，
 * Application 层通过 FileRepository Port 访问。
 */
class ThinkOrmFileRepository implements FileRepository
{
    public function create(array $attributes): int
    {
        $record = FilesModel::create($attributes);
        return (int) $record->id;
    }

    public function findByIdWithTrashed(int $id): ?array
    {
        $file = FilesModel::withTrashed()->where('id', $id)->find();
        return $file ? $file->toArray() : null;
    }

    public function findById(int $id): ?array
    {
        $file = FilesModel::find($id);
        return $file ? $file->toArray() : null;
    }

    public function findByIdVisible(int $id, int $userId, bool $canReadAll): ?array
    {
        $query = FilesModel::withTrashed()->where('id', $id);

        if (!$canReadAll) {
            if ($userId > 0) {
                $query->visibleTo($userId);
            } else {
                $query->securePublic();
            }
        }

        $file = $query->find();
        return $file ? $file->toArray() : null;
    }

    public function findByHash(string $hash, int $userId, bool $canReadAll): ?array
    {
        $query = FilesModel::withTrashed()->where('hash', $hash);

        if (!$canReadAll) {
            if ($userId > 0) {
                $query->visibleTo($userId);
            } else {
                $query->securePublic();
            }
        }

        $file = $query->find();
        return $file ? $file->toArray() : null;
    }

    public function findByHashes(array $hashes, int $userId, bool $canReadAll): array
    {
        if (empty($hashes)) return [];

        $query = FilesModel::withTrashed()->whereIn('hash', $hashes);

        if (!$canReadAll) {
            if ($userId > 0) {
                $query->visibleTo($userId);
            } else {
                $query->securePublic();
            }
        }

        $files = $query->select();
        $result = [];
        foreach ($files as $file) {
            $result[] = $file->toArray();
        }
        return $result;
    }

    public function findOwnPending(int $recordId, int $userId): ?array
    {
        $file = FilesModel::where('id', $recordId)
            ->where('user_id', $userId)
            ->find();

        if ($file === null) {
            return null;
        }

        if ((int) $file->upload_status !== FileConstants::UPLOAD_PENDING) {
            return null;
        }

        return $file->toArray();
    }

    public function markAsCompleted(int $id, string $url, string $driverPath): bool
    {
        $file = FilesModel::find($id);
        if ($file === null) return false;
        return $file->markAsCompleted($url, $driverPath);
    }

    public function markAsFailed(int $id): bool
    {
        $file = FilesModel::find($id);
        if ($file === null) return false;
        return $file->markAsFailed();
    }

    public function isExpired(int $id): bool
    {
        $file = FilesModel::find($id);
        if ($file === null) return true;
        return $file->isExpired();
    }

    public function findIdsWithOwner(array $ids): array
    {
        $files = FilesModel::withTrashed()->whereIn('id', $ids)->select();
        $result = [];
        foreach ($files as $file) {
            $result[(int) $file->id] = (int) $file->user_id;
        }
        return $result;
    }

    public function batchUpdateStatus(array $ids, string $field, $value): void
    {
        Db::table('files')->whereIn('id', $ids)->update([$field => $value]);
    }

    public function batchTogglePublic(array $ids): void
    {
        Db::table('files')->whereIn('id', $ids)->update(['is_public' => Db::raw('1 - is_public')]);
    }

    public function softDelete(int $id): void
    {
        $file = FilesModel::find($id);
        if ($file) $file->delete();
    }

    public function restore(int $id): void
    {
        $file = FilesModel::withTrashed()->find($id);
        if ($file) $file->restore();
    }

    public function hardDelete(int $id): void
    {
        Db::table('files')->where('id', $id)->delete();
    }

    public function cleanupExpired(int $limit): array
    {
        return FilesModel::cleanupExpired($limit);
    }

    public function statsByChannel(string $slug): array
    {
        $query = FilesModel::where('channel_slug', $slug)->whereNull('deleted_at');
        return [
            'file_count' => $query->count(),
            'total_size' => (int) $query->sum('file_size'),
        ];
    }

    public function paginate(array $params, int $userId, bool $canReadAll): array
    {
        $params['search_default_key'] = 'original_name';

        $modelList = ModelList::make(FilesModel::class);

        $showDeleted = (int) ($params['show_deleted'] ?? 0);
        if ($canReadAll && $showDeleted > 0) {
            if ($showDeleted === 1) {
                $modelList->withTrashed();
            } elseif ($showDeleted === 2) {
                $modelList->onlyTrashed();
            }
        }

        $where = $params['where'] ?? [];

        if (!$canReadAll) {
            if ($userId > 0) {
                $where[] = function ($q) use ($userId) {
                    $q->where('user_id', $userId)
                      ->whereOr(function ($q2) {
                          $q2->where('is_public', 1)
                              ->where('status', FileConstants::STATUS_NORMAL)
                              ->where('upload_status', FileConstants::UPLOAD_COMPLETED)
                              ->whereNull('deleted_at');
                      });
                };
            } else {
                $where[] = ['is_public', '=', 1];
                $where[] = ['status', '=', FileConstants::STATUS_NORMAL];
                $where[] = ['upload_status', '=', FileConstants::UPLOAD_COMPLETED];
                $where[] = function ($q) {
                    $q->whereNull('deleted_at');
                };
            }
        }

        if (isset($params['scene'])) {
            $where[] = ['scene', '=', $params['scene']];
        }
        if (isset($params['ref_type'])) {
            $where[] = ['ref_type', '=', $params['ref_type']];
        }
        if (isset($params['ref_id'])) {
            $where[] = ['ref_id', '=', $params['ref_id']];
        }
        if (isset($params['status'])) {
            $where[] = ['status', '=', $params['status']];
        }
        if (isset($params['upload_status'])) {
            $where[] = ['upload_status', '=', $params['upload_status']];
        }

        $params['where'] = $where;
        $result = $modelList->getPaginate($params);
        return $result->toArray();
    }

    public function paginateOwn(array $params, int $userId): array
    {
        $params['search_default_key'] = 'original_name';
        $modelList = ModelList::make(FilesModel::class);
        $where = $params['where'] ?? [];
        $where[] = ['user_id', '=', $userId];
        if (isset($params['scene'])) {
            $where[] = ['scene', '=', $params['scene']];
        }
        if (isset($params['ref_type'])) {
            $where[] = ['ref_type', '=', $params['ref_type']];
        }
        if (isset($params['ref_id'])) {
            $where[] = ['ref_id', '=', $params['ref_id']];
        }
        if (isset($params['status'])) {
            $where[] = ['status', '=', $params['status']];
        }
        if (isset($params['upload_status'])) {
            $where[] = ['upload_status', '=', $params['upload_status']];
        }
        $params['where'] = $where;
        $result = $modelList->getPaginate($params);
        return $result->toArray();
    }

    public function getChannelAndDriverPath(int $id): ?array
    {
        $file = FilesModel::withTrashed()->find($id);
        if ($file === null) return null;
        return [
            'channel_slug' => $file->channel_slug,
            'driver_path' => $file->driver_path,
            'file_path' => $file->file_path,
        ];
    }

    public function createPendingRecord(
        string $hash,
        string $channelSlug,
        ?int $userId,
        string $filename,
        string $path,
        int $size,
        string $mime,
        string $expireAt
    ): int {
        $fileModel = new FilesModel();
        $fileModel->hash = $hash;
        $fileModel->channel_slug = $channelSlug;
        $fileModel->user_id = $userId;
        $fileModel->original_name = $filename;
        $fileModel->file_path = $path;
        $fileModel->file_url = '';
        $fileModel->file_size = $size;
        $fileModel->file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $fileModel->mime_type = $mime;
        $fileModel->driver_path = '';
        $fileModel->scene = FileConstants::SCENE_DIRECT;
        $fileModel->status = FileConstants::STATUS_NORMAL;
        $fileModel->upload_status = FileConstants::UPLOAD_PENDING;
        $fileModel->expire_at = $expireAt;
        $fileModel->created_at = date('Y-m-d H:i:s');
        $fileModel->updated_at = date('Y-m-d H:i:s');
        $fileModel->save();

        return (int) $fileModel->id;
    }
}
