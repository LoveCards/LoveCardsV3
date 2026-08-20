<?php

namespace app\modules\Platform\Files\Domain;

/**
 * 文件业务常量
 *
 * 从 Model 层提取到 Application 层，避免 Application 依赖 Model。
 * Model 层的 Files 常量保留为兼容别名，指向此处。
 */
final class FileConstants
{
    // 场景
    const SCENE_CARD = 'card';
    const SCENE_COMMENT = 'comment';
    const SCENE_AVATAR = 'avatar';
    const SCENE_DIRECT = 'direct';

    // 审核状态
    const STATUS_NORMAL = 0;
    const STATUS_BANNED = 1;

    // 上传状态
    const UPLOAD_PENDING = 0;
    const UPLOAD_COMPLETED = 1;
    const UPLOAD_FAILED = 2;

    // 物理清理状态
    const PURGE_NONE = 'none';
    const PURGE_PENDING = 'pending';
    const PURGE_PROCESSING = 'processing';
    const PURGE_STORAGE_DELETED = 'storage_deleted';
    const PURGE_FAILED = 'failed';

    private function __construct() {}
}
