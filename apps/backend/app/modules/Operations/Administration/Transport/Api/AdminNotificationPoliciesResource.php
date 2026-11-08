<?php
declare(strict_types=1);

namespace app\modules\Operations\Administration\Transport\Api;

use app\shared\Transport\Http\Resource;

/** 通知策略目录响应：当前全局版本 + 现行规则行（契约 §5）。 */
final class AdminNotificationPoliciesResource extends Resource
{
    private const PRESENTATION = [
        'social.followed' => ['新关注提醒', '关闭后，关注关系照常建立，不生成关注提醒。'],
        'message.sent' => ['私信提醒', '关闭后，私信仍可送达并刷新列表，不生成站内提醒。'],
        'content.liked' => ['点赞提醒', '关闭后，点赞照常生效，不生成卡片或评论点赞提醒。'],
        'library.saved' => ['收藏提醒', '关闭后，收藏照常生效，不生成收藏提醒。'],
        'content.comment.published' => ['评论与回复提醒', '关闭后，评论照常发表，不生成评论或回复提醒。'],
        'content.mentions.added' => ['@提及提醒', '关闭后，提及内容照常保留，不生成提及提醒。'],
        'system.notification.issued' => ['系统安全与审核通知', '涉及账号安全、审核与治理结果，必须始终开启。'],
    ];

    public static function displayName(string $eventType): ?string
    {
        return self::PRESENTATION[$eventType][0] ?? null;
    }

    public static function schema(string $variant = 'default'): array
    {
        return ['type' => 'object', 'additionalProperties' => false, 'required' => ['revision', 'policies'], 'properties' => [
            'revision' => ['type' => ['integer', 'null']],
            'policies' => ['type' => 'array', 'items' => [
                'type' => 'object', 'additionalProperties' => false,
                'required' => ['event_type', 'event_version', 'rule_key', 'enabled', 'notification_type', 'recipient_rule', 'user_disableable', 'locked', 'policy_revision', 'display_name', 'impact_text'],
                'properties' => [
                    'event_type' => ['type' => 'string'], 'event_version' => ['type' => 'integer'],
                    'rule_key' => ['type' => 'string'], 'enabled' => ['type' => 'boolean'],
                    'notification_type' => ['type' => ['string', 'null']], 'recipient_rule' => ['type' => 'string'],
                    'user_disableable' => ['type' => 'boolean'], 'locked' => ['type' => 'boolean'],
                    'policy_revision' => ['type' => 'integer'],
                    'display_name' => ['type' => ['string', 'null']], 'impact_text' => ['type' => ['string', 'null']],
                ],
            ]],
        ]];
    }

    public static function make($value): array
    {
        $data = self::normalize($value);
        if (!is_array($data)) return ['revision' => null, 'policies' => []];
        return [
            'revision' => isset($data['revision']) ? (int) $data['revision'] : null,
            'policies' => array_map(static function (array $row): array {
                $text = self::PRESENTATION[$row['event_type']] ?? null;
                $row['display_name'] = self::displayName((string) $row['event_type']);
                $row['impact_text'] = $text[1] ?? null;
                return $row;
            }, array_values(is_array($data['policies'] ?? null) ? $data['policies'] : [])),
        ];
    }
}
