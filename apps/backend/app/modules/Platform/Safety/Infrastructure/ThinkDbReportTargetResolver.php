<?php
declare(strict_types=1);

namespace app\modules\Platform\Safety\Infrastructure;

use app\modules\Platform\Safety\Contract\ReportTargetResolver;
use app\shared\Resource\ResourceRef;
use think\facade\Db;

final class ThinkDbReportTargetResolver implements ReportTargetResolver
{
    public function snapshotForReporter(int $reporterId, ResourceRef $resource): ?array
    {
        $id = $resource->id();
        if (!ctype_digit($id) || (int) $id < 1) return null;
        return match ($resource->type()) {
            'user' => $this->user((int) $id),
            'card' => $this->card($reporterId, (int) $id),
            'comment' => $this->comment($reporterId, (int) $id),
            'message' => $this->message($reporterId, (int) $id),
            'file' => $this->file($reporterId, (int) $id),
            default => null,
        };
    }

    private function user(int $id): ?array
    {
        $row = Db::table('users')->where('id', $id)->whereNull('deleted_at')->field('id,number,username,status')->find();
        return $row ? ['type' => 'user', 'captured' => $row] : null;
    }

    private function card(int $reporterId, int $id): ?array
    {
        $row = Db::table('cards')->where('id', $id)->whereNull('deleted_at')
            ->where(function ($query) use ($reporterId): void {
                $query->where('status', 0)->whereOr('user_id', $reporterId);
            })->field('id,user_id,status,content')->find();
        return $row ? ['type' => 'card', 'captured' => $this->content($row)] : null;
    }

    private function comment(int $reporterId, int $id): ?array
    {
        $row = Db::table('comments')->where('id', $id)->whereNull('deleted_at')
            ->where(function ($query) use ($reporterId): void {
                $query->where('status', 0)->whereOr('user_id', $reporterId);
            })->field('id,pid,user_id,status,content')->find();
        return $row ? ['type' => 'comment', 'captured' => $this->content($row)] : null;
    }

    private function message(int $reporterId, int $id): ?array
    {
        $row = Db::table('messages')->alias('m')
            ->join('conversation_members member', 'member.conversation_id = m.conversation_id')
            ->where('m.id', $id)
            ->where('member.user_id', $reporterId)
            ->whereNull('m.deleted_at')
            ->field('m.id,m.conversation_id,m.sender_id,m.body,m.created_at')
            ->find();
        return $row ? ['type' => 'message', 'captured' => $this->content($row, 'body')] : null;
    }

    private function file(int $reporterId, int $id): ?array
    {
        $row = Db::table('files')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->where(function ($query) use ($reporterId): void {
                $query->where('user_id', $reporterId)->whereOr(function ($public): void {
                    $public->where('is_public', 1)->where('status', 0)->where('upload_status', 1);
                });
            })
            ->whereNull('purge_status')
            ->field('id,user_id,hash,mime_type,status,upload_status,file_url')
            ->find();
        return $row ? ['type' => 'file', 'captured' => $row] : null;
    }

    private function content(array $row, string $field = 'content'): array
    {
        $value = (string) ($row[$field] ?? '');
        $row[$field] = mb_substr($value, 0, 2000);
        $row[$field . '_sha256'] = hash('sha256', $value);
        return $row;
    }
}
