<?php
declare(strict_types=1);
namespace app\modules\Content\Cards\Public;

use app\modules\Content\Cards\Service\CardVisibilityPolicy;
use think\facade\Db;

/** 向跨模块读取方提供统一的卡片授权。 */
final class CardAccess
{
    public static function canRead(array $card, int $viewerId): bool { return CardVisibilityPolicy::canRead($card, $viewerId); }

    public static function assertReadable(int $cardId, int $viewerId): array
    {
        return CardVisibilityPolicy::assertReadable($cardId, $viewerId);
    }

    public static function readableIds(array $ids, int $viewerId): array
    {
        if ($ids === []) return [];
        $query = Db::table('cards')->whereIn('cards.id', $ids)->where('cards.status', 0)->whereNull('cards.deleted_at');
        CardVisibilityPolicy::apply($query, $viewerId);
        return array_map('intval', $query->column('cards.id'));
    }
}
