<?php
declare(strict_types=1);

namespace app\modules\Content\Cards\Service;

use app\api\ApiException;
use app\modules\Platform\Safety\Public\Safety;
use think\facade\Db;

/** Single source of truth for card audience checks. */
final class CardVisibilityPolicy
{
    public const PUBLIC = 'public';
    public const FOLLOWERS = 'followers';
    public const PRIVATE = 'private';

    public static function values(): array
    {
        return [self::PUBLIC, self::FOLLOWERS, self::PRIVATE];
    }

    public static function normalize(mixed $value): string
    {
        $value = strtolower(trim((string) $value));
        if (!in_array($value, self::values(), true)) {
            throw ApiException::validation(['visibility' => ['可见范围必须是 public、followers 或 private。']]);
        }
        return $value;
    }

    /** Apply the audience predicate before pagination. */
    public static function apply($query, int $viewerId): void
    {
        $query->whereRaw('EXISTS (SELECT 1 FROM users vis_u WHERE vis_u.id = cards.user_id AND vis_u.status = 0 AND vis_u.deleted_at IS NULL)');
        if ($viewerId < 1) {
            $query->where('cards.visibility', self::PUBLIC);
            return;
        }

        // Keep this predicate literal-only (viewerId is an integer and the
        // visibility values are class constants). ThinkORM's SQL debug
        // formatter cannot safely expand named binds that occur inside a
        // nested raw expression; that turned authenticated hot-list reads
        // into a 9001 after the query itself had already succeeded.
        $viewer = (int) $viewerId;
        $public = self::PUBLIC;
        $followers = self::FOLLOWERS;
        $query->whereRaw("NOT EXISTS (SELECT 1 FROM user_safety_relations vis_b WHERE vis_b.relation = 'block' AND ((vis_b.actor_id = {$viewer} AND vis_b.subject_id = cards.user_id) OR (vis_b.actor_id = cards.user_id AND vis_b.subject_id = {$viewer})))");
        // Card audience is independent from profile privacy. A public card is
        // readable by any viewer; a followers card only by the author or a
        // real follower. Profile privacy is enforced by profile endpoints.
        $query->whereRaw("(
                cards.user_id = {$viewer}
                OR cards.visibility = '{$public}'
                OR (cards.visibility = '{$followers}' AND EXISTS (
                    SELECT 1 FROM user_follows vf
                    WHERE vf.follower_id = {$viewer} AND vf.followee_id = cards.user_id
                ))
            )");
    }

    public static function assertReadable(int $cardId, int $viewerId): array
    {
        $base = Db::table('cards');
        $query = $base->where('cards.id', $cardId)->where('cards.status', 0)->whereNull('cards.deleted_at');
        self::apply($query, $viewerId);
        $row = $query->find();
        if (!$row) throw ApiException::notFound('卡片不存在');
        return is_object($row) && method_exists($row, 'toArray') ? $row->toArray() : (array) $row;
    }

    public static function canRead(array $card, int $viewerId): bool
    {
        if ((int) ($card['status'] ?? -1) !== 0 || !empty($card['deleted_at'])) return false;
        $ownerId = (int) ($card['user_id'] ?? 0);
        if ($ownerId < 1) return false;
        $owner = Db::table('users')->where('id', $ownerId)->where('status', 0)->whereNull('deleted_at')->find();
        if (!$owner) return false;
        if ($viewerId === $ownerId) return true;
        if ($viewerId > 0 && Safety::isBlockedEitherDirection($viewerId, $ownerId)) return false;
        if ($viewerId < 1) return (string) ($card['visibility'] ?? self::PUBLIC) === self::PUBLIC;
        $following = (bool) Db::table('user_follows')->where(['follower_id' => $viewerId, 'followee_id' => $ownerId])->find();
        $visibility = (string) ($card['visibility'] ?? self::PUBLIC);
        $cardVisible = $visibility === self::PUBLIC || ($visibility === self::FOLLOWERS && $following);
        return $cardVisible;
    }
}
