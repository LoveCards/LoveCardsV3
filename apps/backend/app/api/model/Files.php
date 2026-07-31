<?php

namespace app\api\model;

use think\Model;
use think\model\concern\SoftDelete;
use app\api\application\Files\FileConstants;

class Files extends Model
{
    use SoftDelete;

    protected $name = 'files';
    protected $pk = 'id';

    protected $autoWriteTimestamp = 'datetime';
    protected $createTime = 'created_at';
    protected $updateTime = 'updated_at';
    protected $deleteTime = 'deleted_at';

    protected $hidden = ['deleted_at', 'driver_path'];

    // 兼容别名：指向 Application 层规范化常量
    const SCENE_CARD = FileConstants::SCENE_CARD;
    const SCENE_COMMENT = FileConstants::SCENE_COMMENT;
    const SCENE_AVATAR = FileConstants::SCENE_AVATAR;
    const SCENE_DIRECT = FileConstants::SCENE_DIRECT;
    const STATUS_NORMAL = FileConstants::STATUS_NORMAL;
    const STATUS_BANNED = FileConstants::STATUS_BANNED;
    const UPLOAD_PENDING = FileConstants::UPLOAD_PENDING;
    const UPLOAD_COMPLETED = FileConstants::UPLOAD_COMPLETED;
    const UPLOAD_FAILED = FileConstants::UPLOAD_FAILED;

    public function scopeByHash($query, string $hash)
    {
        return $query->where('hash', $hash);
    }

    public function scopeByHashes($query, array $hashes)
    {
        return $query->whereIn('hash', $hashes);
    }

    public static function generateHash(): string
    {
        return bin2hex(random_bytes(8));
    }

    public function user()
    {
        return $this->belongsTo(Users::class, 'user_id');
    }

    /**
     * 可见范围：
     * - uid>0：本人记录（不含软删除） OR 安全公开记录
     * - uid<=0（访客）：仅安全公开记录
     *
     * 安全公开 = is_public=1 AND status=NORMAL AND upload_status=COMPLETED AND deleted_at IS NULL
     */
    public function scopeVisibleTo($query, $userId)
    {
        if ($userId <= 0) {
            return $query->securePublic();
        }
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->whereNull('deleted_at')
              ->whereOr(function ($q2) {
                  $q2->securePublic();
              });
        });
    }

    /**
     * 安全公开记录范围：
     * is_public=1 AND status=NORMAL AND upload_status=COMPLETED AND deleted_at IS NULL
     */
    public function scopeSecurePublic($query)
    {
        return $query->where('is_public', 1)
            ->where('status', self::STATUS_NORMAL)
            ->where('upload_status', self::UPLOAD_COMPLETED)
            ->whereNull('deleted_at');
    }

    public function scopeByScene($query, $scene)
    {
        return $query->where('scene', $scene);
    }

    public function scopeByRef($query, $refType, $refId = null)
    {
        $query->where('ref_type', $refType);
        if ($refId !== null) {
            $query->where('ref_id', $refId);
        }
        return $query;
    }

    public function scopeNormal($query)
    {
        return $query->where('status', self::STATUS_NORMAL);
    }

    public function markAsCompleted(string $url, string $driverPath): bool
    {
        $this->status = self::STATUS_NORMAL;
        $this->upload_status = self::UPLOAD_COMPLETED;
        $this->file_url = $url;
        $this->driver_path = $driverPath;
        $this->updated_at = date('Y-m-d H:i:s');
        return $this->save();
    }

    public function markAsFailed(): bool
    {
        $this->upload_status = self::UPLOAD_FAILED;
        $this->updated_at = date('Y-m-d H:i:s');
        return $this->save();
    }

    public function isExpired(): bool
    {
        if ($this->expire_at === null) {
            return false;
        }
        return strtotime($this->expire_at) < time();
    }

    public static function cleanupExpired(int $limit = 100): array
    {
        $expired = self::where('upload_status', self::UPLOAD_PENDING)
            ->where('expire_at', '<', date('Y-m-d H:i:s'))
            ->limit($limit)
            ->select();

        $cleaned = [];
        foreach ($expired as $record) {
            $cleaned[] = [
                'id' => $record->id,
                'expired_at' => $record->expire_at,
            ];
            $record->upload_status = self::UPLOAD_FAILED;
            $record->save();
        }

        return $cleaned;
    }

    public static function hardCleanupExpired(int $limit = 100): array
    {
        $expired = self::where('upload_status', self::UPLOAD_PENDING)
            ->where('expire_at', '<', date('Y-m-d H:i:s'))
            ->limit($limit)
            ->select();

        $cleaned = [];
        foreach ($expired as $record) {
            $cleaned[] = ['id' => $record->id];
            $record->delete();
        }

        return $cleaned;
    }

}
