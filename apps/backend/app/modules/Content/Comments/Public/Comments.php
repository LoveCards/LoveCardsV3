<?php

namespace app\modules\Content\Comments\Public;

use app\modules\Content\Comments\Service\Comments as CommentsService;

final class Comments
{
    public static function requiresApproval(): bool { return CommentsService::requiresApproval(); }
    public static function listAll(array $params, array $caps = []): array { return CommentsService::listAll($params, $caps); }
    public static function count(): int { return (int) CommentsService::count(); }

    /** 删除卡片时，由调用方事务包住评论清理与卡片删除。 */
    public static function deleteForPurgedCards(array $cardIds): void
    {
        CommentsService::deleteForPurgedCards($cardIds);
    }
}
