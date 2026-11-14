<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Public;

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
                'social.followed',
                1,
                'notifications.social-followed',
                'notifications',
                ExecutionMode::REQUEST_SAFE,
                new \app\modules\Platform\Notifications\Infrastructure\SocialFollowedNotificationHandler()
            ),
            new HandlerRegistration(
                'message.sent',
                1,
                'notifications.message-sent',
                'notifications',
                ExecutionMode::REQUEST_SAFE,
                new \app\modules\Platform\Notifications\Infrastructure\MessageSentNotificationHandler()
            ),
            new HandlerRegistration(
                'content.liked',
                1,
                'notifications.content-liked',
                'notifications',
                ExecutionMode::REQUEST_SAFE,
                new \app\modules\Platform\Notifications\Infrastructure\ContentLikedNotificationHandler()
            ),
            new HandlerRegistration(
                'library.saved',
                1,
                'notifications.library-saved',
                'notifications',
                ExecutionMode::REQUEST_SAFE,
                new \app\modules\Platform\Notifications\Infrastructure\LibrarySavedNotificationHandler()
            ),
            new HandlerRegistration(
                'content.comment.published',
                1,
                'notifications.content-comment-published',
                'notifications',
                ExecutionMode::REQUEST_SAFE,
                new \app\modules\Platform\Notifications\Infrastructure\ContentCommentPublishedNotificationHandler()
            ),
            new HandlerRegistration(
                'content.mentions.added',
                1,
                'notifications.content-mentions-added',
                'notifications',
                ExecutionMode::REQUEST_SAFE,
                new \app\modules\Platform\Notifications\Infrastructure\ContentMentionsAddedNotificationHandler()
            ),
            new HandlerRegistration(
                'system.notification.issued',
                1,
                'notifications.system-notification-issued',
                'notifications',
                ExecutionMode::REQUEST_SAFE,
                new \app\modules\Platform\Notifications\Infrastructure\SystemNotificationIssuedHandler()
            ),
            new HandlerRegistration(
                'notification.created',
                1,
                'realtime.notification-created',
                'realtime',
                ExecutionMode::DAEMON_REQUIRED,
                new \app\modules\Platform\Notifications\Infrastructure\NotificationCreatedSignalHandler(),
                100,
                [5, 30, 300, 1800],
                [
                    'key' => 'notifications.new',
                    'topic' => 'notifications',
                    'owner' => 'Notifications',
                    'display_name' => '新站内通知主动提示',
                    'impact_text' => '关闭后站内通知照常生成和读取，浏览器不主动提示新通知。',
                    'default_enabled' => true,
                ]
            ),
        ];
    }
}
