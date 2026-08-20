<?php

namespace app\modules\Platform\Community\Transport\Api;

use think\Validate;

/** Shared request contract for community-facing cursor, safety and messaging APIs. */
final class Community extends Validate
{
    public static function requestSchemas(): array
    {
        $cursor = [
            'type' => 'object',
            'properties' => [
                'cursor' => ['type' => 'string', 'maxLength' => 128],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
            ],
            'additionalProperties' => false,
        ];

        return [
            'cursor' => $cursor,
            'notifications' => [
                'type' => 'object',
                'properties' => $cursor['properties'] + ['unread_only' => ['type' => 'boolean']],
                'additionalProperties' => false,
            ],
            'read' => [
                'type' => 'object',
                'properties' => ['ids' => ['type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1]]],
                'required' => ['ids'],
                'additionalProperties' => false,
            ],
            'preferences' => [
                'type' => 'object',
                'properties' => [
                    'profile_visibility' => ['type' => 'string', 'enum' => ['public', 'followers', 'private']],
                    'messages_from' => ['type' => 'string', 'enum' => ['everyone', 'following', 'none']],
                    'show_relations' => ['type' => 'boolean'],
                ],
                'additionalProperties' => false,
            ],
            'report' => [
                'type' => 'object',
                'properties' => [
                    'resource_type' => ['type' => 'string', 'maxLength' => 32],
                    'resource_id' => ['type' => 'string', 'maxLength' => 128],
                    'reason' => ['type' => 'string', 'maxLength' => 64],
                    'details' => ['type' => 'string', 'maxLength' => 2000],
                ],
                'required' => ['resource_type', 'resource_id', 'reason'],
                'additionalProperties' => false,
            ],
            'draft' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string', 'maxLength' => 255],
                    'content' => ['type' => 'string', 'maxLength' => 100000],
                    'payload' => ['type' => 'object', 'additionalProperties' => true],
                ],
                'additionalProperties' => false,
            ],
            'messageSend' => [
                'type' => 'object',
                'properties' => [
                    'recipient_id' => ['type' => 'integer', 'minimum' => 1],
                    'body' => ['type' => 'string', 'maxLength' => 5000],
                    'client_message_id' => ['type' => 'string', 'maxLength' => 128],
                ],
                'required' => ['recipient_id', 'body'],
                'additionalProperties' => false,
            ],
            'stream' => [
                'type' => 'object',
                'properties' => ['after' => ['type' => 'integer', 'minimum' => 0]],
                'additionalProperties' => false,
            ],
        ];
    }

    public static $all_scene = [
        'cursor' => ['normal' => ['cursor', 'limit'], 'require' => false, 'nonNull' => false, 'toNull' => false],
        'notifications' => ['normal' => ['cursor', 'limit', 'unread_only'], 'require' => false, 'nonNull' => false, 'toNull' => false],
        'read' => ['normal' => false, 'require' => ['ids'], 'nonNull' => false, 'toNull' => false],
        'preferences' => ['normal' => ['profile_visibility', 'messages_from', 'show_relations'], 'require' => false, 'nonNull' => false, 'toNull' => false],
        'report' => ['normal' => ['details'], 'require' => ['resource_type', 'resource_id', 'reason'], 'nonNull' => false, 'toNull' => false],
        'draft' => ['normal' => ['title', 'content', 'payload'], 'require' => false, 'nonNull' => false, 'toNull' => false],
        'messageSend' => ['normal' => ['client_message_id'], 'require' => ['recipient_id', 'body'], 'nonNull' => false, 'toNull' => false],
        'stream' => ['normal' => ['after'], 'require' => false, 'nonNull' => false, 'toNull' => false],
    ];

    protected $rule = [
        'cursor' => 'max:128',
        'limit' => 'number|between:1,100',
        'unread_only' => 'stringBool',
        'ids' => 'arrayJson',
        'profile_visibility' => 'in:public,followers,private',
        'messages_from' => 'in:everyone,following,none',
        'show_relations' => 'stringBool',
        'resource_type' => 'require|max:32|regex:/^[a-z][a-z0-9_]{0,31}$/',
        'resource_id' => 'require|max:128|regex:/^[A-Za-z0-9][A-Za-z0-9._:-]*$/',
        'reason' => 'require|max:64',
        'details' => 'max:2000',
        'title' => 'max:255',
        'content' => 'max:100000',
        'payload' => 'arrayJson',
        'recipient_id' => 'require|number',
        'body' => 'require|max:5000',
        'client_message_id' => 'max:128',
        'after' => 'number|between:0,2147483647',
    ];

    /** 社区 JSON 请求的布尔字段同时接受 JSON boolean 与字符串形式。 */
    protected function stringBool($value, $rule, $data = [])
    {
        return is_bool($value) || $value === 'true' || $value === 'false';
    }
}
