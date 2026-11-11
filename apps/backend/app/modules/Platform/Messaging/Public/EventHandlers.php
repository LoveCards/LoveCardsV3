<?php
declare(strict_types=1);

namespace app\modules\Platform\Messaging\Public;

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
                'message.sent',
                1,
                'realtime.message-sent',
                'realtime',
                ExecutionMode::DAEMON_REQUIRED,
                new \app\modules\Platform\Messaging\Infrastructure\MessageSentSignalHandler(),
                100,
                [5, 30, 300, 1800],
                [
                    'key' => 'messages.new',
                    'topic' => 'messages',
                    'owner' => 'Messaging',
                    'display_name' => '新私信主动提示',
                    'impact_text' => '关闭后私信仍可发送和读取，浏览器不主动提示新私信。',
                    'default_enabled' => true,
                ]
            ),
            new HandlerRegistration(
                'message.read',
                1,
                'realtime.message-read',
                'realtime',
                ExecutionMode::DAEMON_REQUIRED,
                new \app\modules\Platform\Messaging\Infrastructure\MessageReadSignalHandler(),
                100,
                [5, 30, 300, 1800],
                [
                    'key' => 'messages.read',
                    'topic' => 'messages',
                    'owner' => 'Messaging',
                    'display_name' => '私信已读状态主动提示',
                    'impact_text' => '关闭后已读状态照常保存，浏览器不主动提示会话已读变化。',
                    'default_enabled' => true,
                ]
            ),
        ];
    }
}
