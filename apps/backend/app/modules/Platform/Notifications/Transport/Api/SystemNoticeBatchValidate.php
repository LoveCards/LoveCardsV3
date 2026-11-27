<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Transport\Api;

use think\Validate;

/** 管理发布的请求边界；普通用户通知接口不接收发布命令。 */
final class SystemNoticeBatchValidate extends Validate
{
    public static $all_scene = [
        'prepare' => ['normal' => ['recipient_ids', 'action_url', 'severity'], 'require' => ['request_key', 'audience', 'message'], 'nonNull' => [], 'toNull' => []],
        'candidates' => ['normal' => ['limit'], 'require' => ['query'], 'nonNull' => [], 'toNull' => []],
        'latest' => ['normal' => ['request_key'], 'require' => [], 'nonNull' => [], 'toNull' => []],
        'confirm' => ['normal' => [], 'require' => [], 'nonNull' => [], 'toNull' => []],
        'retry' => ['normal' => [], 'require' => ['expected_generation', 'reason'], 'nonNull' => [], 'toNull' => []],
    ];
    protected $must = ['request_key', 'recipient_ids', 'action_url', 'severity', 'limit'];
    protected $rule = [
        'request_key' => 'requestKey',
        'audience' => 'require|in:selected,all',
        'recipient_ids' => 'recipientIds',
        'message' => 'require|noticeMessage',
        'action_url' => 'internalPath',
        'severity' => 'noticeSeverity',
        'query' => 'require|searchText',
        'limit' => 'candidateLimit',
        'expected_generation' => 'require|generationNumber',
        'reason' => 'require|retryReason',
    ];
    protected $message = [
        'request_key' => '发布请求标识无效',
        'audience' => '请选择指定用户或全站用户',
        'recipient_ids' => '收件用户必须是最多 200 个正整数的列表',
        'message' => '正文不能为空，且不能超过通知数据预算',
        'action_url' => '跳转地址必须是有效站内路径',
        'severity' => '通知级别只能选择普通或提醒',
        'query' => '请输入 1 至 80 字的用户名或用户编号',
        'limit' => '候选用户数量必须是 1 至 20 的整数',
        'expected_generation' => '请提供当前批次的有效代次',
        'reason' => '请填写 1 至 255 字的重试原因',
    ];

    public static function requestSchemas(): array
    {
        $key = ['type' => 'string', 'minLength' => 1, 'maxLength' => 64, 'pattern' => '^[A-Za-z0-9][A-Za-z0-9_.:-]{0,63}$'];
        $object = ['type' => 'object', 'additionalProperties' => false];
        return [
            'prepare' => $object + ['required' => ['request_key', 'audience', 'message'], 'properties' => [
                'request_key' => $key,
                'audience' => ['type' => 'string', 'enum' => ['selected', 'all']],
                'recipient_ids' => ['type' => 'array', 'maxItems' => 200, 'items' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 2147483647]],
                'message' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 4096, 'description' => '纯文本；正文、路径与级别编码后的通知 payload 总计不超过 4096 字节'],
                'action_url' => ['type' => 'string', 'nullable' => true, 'maxLength' => 2048],
                'severity' => ['type' => 'string', 'enum' => ['info', 'warning'], 'default' => 'info'],
            ]],
            'candidates' => $object + ['required' => ['query'], 'properties' => [
                'query' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 80],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20, 'default' => 20],
            ]],
            'latest' => $object + ['properties' => ['request_key' => $key]],
            'confirm' => $object + ['properties' => []],
            'retry' => $object + ['required' => ['expected_generation', 'reason'], 'properties' => [
                'expected_generation' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 2147483647],
                'reason' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
            ]],
        ];
    }

    protected function requestKey($value, $rule, array $data, string $field): bool
    {
        return !array_key_exists($field, $data) || (is_string($value) && preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,63}$/D', $value) === 1);
    }

    protected function recipientIds($value, $rule, array $data, string $field): bool
    {
        if (!array_key_exists($field, $data)) return true;
        if (!is_array($value) || !array_is_list($value) || count($value) > 200) return false;
        foreach ($value as $id) if (!is_int($id) || $id < 1 || $id > 2147483647) return false;
        return true;
    }

    protected function noticeMessage($value): bool
    {
        return is_string($value) && trim($value) !== '' && strlen($value) <= 4096 && !str_contains($value, "\0");
    }

    protected function internalPath($value, $rule, array $data, string $field): bool
    {
        if (!array_key_exists($field, $data) || $value === null || $value === '') return true;
        if (!is_string($value) || strlen($value) > 2048 || !str_starts_with($value, '/')) return false;
        $decoded = rawurldecode($value);
        return !str_contains($value, '\\') && !str_contains($decoded, '\\')
            && !str_contains($decoded, '//') && !str_contains($decoded, '://')
            && preg_match('/[\x00-\x1F\x7F]/', $value . $decoded) !== 1;
    }

    protected function noticeSeverity($value, $rule, array $data, string $field): bool
    {
        return !array_key_exists($field, $data) || in_array($value, ['info', 'warning'], true);
    }

    protected function searchText($value): bool
    {
        return is_string($value) && trim($value) !== '' && mb_strlen($value, 'UTF-8') <= 80 && !str_contains($value, "\0");
    }

    protected function candidateLimit($value, $rule, array $data, string $field): bool
    {
        return !array_key_exists($field, $data) || ((is_int($value) || is_string($value)) && preg_match('/^[1-9][0-9]*$/D', (string) $value) === 1 && (int) $value <= 20);
    }

    protected function generationNumber($value): bool
    {
        return is_int($value) && $value >= 1 && $value <= 2147483647;
    }

    protected function retryReason($value): bool
    {
        return is_string($value) && trim($value) !== '' && mb_strlen($value, 'UTF-8') <= 255 && !str_contains($value, "\0");
    }
}
