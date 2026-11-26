<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Contract;

/** 通知公开类型及其列表分类的单一映射。 */
final class NotificationTypeCatalog
{
    public const CATEGORIES = ['social', 'message', 'interaction', 'system'];
    public const LIST_CATEGORIES = ['social', 'message', 'interaction', 'system', 'engagement'];
    public const ENGAGEMENT_TYPES = [
        'message',
        'social.followed',
        'content.card_liked',
        'content.comment_liked',
        'library.saved',
        'content.commented',
        'content.replied',
        'content.mentioned',
    ];

    /** @var array<string, string> */
    private const TYPE_TO_CATEGORY = [
        'social.followed' => 'social',
        'message' => 'message',
        'content.card_liked' => 'interaction',
        'content.comment_liked' => 'interaction',
        'library.saved' => 'interaction',
        'content.commented' => 'interaction',
        'content.replied' => 'interaction',
        'content.mentioned' => 'interaction',
        'system.notice' => 'system',
        'system.security' => 'system',
        'system.moderation' => 'system',
    ];

    /** 管理界面使用的类型名称；未知历史值保留原协议值并给出明确说明。 */
    public static function labelFor(string $type): string
    {
        return match ($type) {
            'social.followed' => '关注提醒',
            'message' => '私信提醒',
            'content.card_liked' => '卡片获赞',
            'content.comment_liked' => '评论获赞',
            'library.saved' => '收藏提醒',
            'content.commented' => '卡片评论',
            'content.replied' => '评论回复',
            'content.mentioned' => '提及提醒',
            'system.notice' => '系统通知',
            'system.security' => '安全通知',
            'system.moderation' => '审核通知',
            default => '未知类型',
        };
    }

    /** @return array<int, string> */
    public static function types(): array
    {
        return array_keys(self::TYPE_TO_CATEGORY);
    }

    /** @return array<int,string> */
    public static function configurableTypes(): array
    {
        return array_values(array_filter(self::types(), static fn(string $type): bool => !str_starts_with($type, 'system.')));
    }

    /** @return array<int,string> */
    public static function lockedTypes(): array
    {
        return array_values(array_filter(self::types(), static fn(string $type): bool => str_starts_with($type, 'system.')));
    }

    public static function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));
        if (!array_key_exists($type, self::TYPE_TO_CATEGORY)) {
            throw new \InvalidArgumentException('通知类型无效');
        }

        return $type;
    }

    public static function categoryFor(string $type): string
    {
        return self::TYPE_TO_CATEGORY[self::normalizeType($type)];
    }

    /** @return array<int, string> */
    public static function typesForCategory(string $category): array
    {
        if (!in_array($category, self::CATEGORIES, true)) {
            throw new \InvalidArgumentException('通知分类无效');
        }

        return array_keys(array_filter(
            self::TYPE_TO_CATEGORY,
            static fn(string $candidate): bool => $candidate === $category
        ));
    }

    /** @return array<int, string> */
    public static function typesForListCategory(string $category): array
    {
        if ($category === 'engagement') return self::ENGAGEMENT_TYPES;
        return self::typesForCategory($category);
    }
}
