<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Domain;

/** 系统通知发布输入与回执口径；不把入队或浏览器提示解释为送达。 */
final class SystemNoticeBatch
{
    public const CHUNK_SIZE = 100;
    public const MAX_SELECTED = 200;
    public const CHUNK_EVENT = 'system.notice.batch.chunk.queued';
    public const CHUNK_HANDLER = 'notifications.system-notice-batch';

    public static function normalize(array $input): array
    {
        if (!is_string($input['request_key'] ?? null) || !is_string($input['message'] ?? null)) {
            throw new \InvalidArgumentException('发布请求标识和正文必须是文本');
        }
        $key = trim($input['request_key']);
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,63}$/', $key)) {
            throw new \InvalidArgumentException('发布请求标识无效');
        }
        $audience = $input['audience'] ?? '';
        if (!in_array($audience, ['selected', 'all'], true)) {
            throw new \InvalidArgumentException('收件范围无效');
        }
        $ids = $input['recipient_ids'] ?? [];
        if (!is_array($ids) || !array_is_list($ids)) throw new \InvalidArgumentException('收件用户必须是列表');
        foreach ($ids as $id) {
            if (!is_int($id) || $id < 1) throw new \InvalidArgumentException('收件用户 ID 必须为正整数');
        }
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NUMERIC);
        if ($audience === 'selected' && ($ids === [] || count($ids) > self::MAX_SELECTED)) {
            throw new \InvalidArgumentException('请选择 1 至 200 位收件用户');
        }
        if ($audience === 'all' && $ids !== []) throw new \InvalidArgumentException('全站发送不能同时指定用户');
        $message = trim($input['message']);
        $severity = $input['severity'] ?? 'info';
        if (!in_array($severity, ['info', 'warning'], true)) throw new \InvalidArgumentException('系统通告只支持普通或提醒级别');
        $url = $input['action_url'] ?? null;
        if ($url === '') $url = null;
        if ($url !== null && !is_string($url)) throw new \InvalidArgumentException('跳转路径无效');
        // 复用既有类型、正文预算与站内路径校验，避免第二份签发规则。
        $notice = new SystemNotice(1, 'batch.validation', SystemNotice::NOTICE, $message, $url, $severity);
        $payload = $notice->eventPayload()['payload'];
        return [
            'request_key' => $key, 'audience' => $audience, 'recipient_ids' => $ids,
            'message' => $payload['message'], 'action_url' => $payload['action_url'], 'severity' => $severity,
        ];
    }

    public static function requestHash(array $input): string
    {
        unset($input['request_key']);
        return hash('sha256', json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public static function noticeKey(int $batchId): string
    {
        if ($batchId < 1) throw new \InvalidArgumentException('发布批次无效');
        return 'notice.batch:' . $batchId;
    }

    public static function status(array $batch, int $generated, int $skipped, int $failed, int $pending, bool $fanoutDead, bool $processing): string
    {
        if (empty($batch['confirmed_at'])) return 'prepared';
        if ($pending === 0 && $failed === 0) return 'completed';
        if ($fanoutDead || ($failed > 0 && $pending === 0)) {
            return $generated + $skipped > 0 ? 'partial_failure' : 'failed';
        }
        return $processing || (int) ($batch['dispatch_cursor'] ?? 0) > 0 ? 'processing' : 'queued';
    }
}
