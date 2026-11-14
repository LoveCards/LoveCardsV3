<?php
declare(strict_types=1);

namespace app\modules\Content\Cards\Public;

use app\modules\Platform\Eventing\Public\HandlerRegistration;
use app\modules\Platform\Eventing\Public\ExecutionMode;

/** 本模块拥有的显式事件处理登记，供应用组合根装配。 */
final class EventHandlers
{
    /** @return HandlerRegistration[] */
    public static function registrations(): array
    {
        return [
            new HandlerRegistration(
                'content.card.published',
                1,
                'realtime.card-published',
                'realtime',
                ExecutionMode::DAEMON_REQUIRED,
                new \app\modules\Content\Cards\Infrastructure\CardPublishedSignalHandler(),
                100,
                [5, 30, 300, 1800],
                [
                    'key' => 'feed.published',
                    'topic' => 'feed',
                    'owner' => 'Content/Cards',
                    'display_name' => '信息流新内容主动提示',
                    'impact_text' => '关闭后卡片仍会正常发布，首页和关注页不会主动提示新内容。',
                    'default_enabled' => true,
                ]
            ),
            new HandlerRegistration(
                'content.card.feed.batch.requested',
                1,
                'realtime.card-feed-batch',
                'realtime',
                ExecutionMode::DAEMON_REQUIRED,
                new \app\modules\Content\Cards\Infrastructure\CardPublishedSignalHandler(),
                100,
                [5, 30, 300, 1800]
            ),
        ];
    }
}
