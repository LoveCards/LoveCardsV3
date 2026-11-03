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
