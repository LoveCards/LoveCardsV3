<?php

namespace app\modules\Platform\Safety\Infrastructure;

use app\modules\Platform\Safety\Contract\SafetyRepository;
use app\modules\Platform\Safety\Domain\PrivacyPreferences;
use app\modules\Platform\Safety\Domain\ReportSubmission;
use app\modules\Platform\Social\Public\Social;
use think\facade\Db;
use app\api\ApiException;

final class ThinkOrmSafetyRepository implements SafetyRepository
{
    public function savePreferences(int $userId, PrivacyPreferences $preferences): void
    {
        $values = $preferences->toArray();
        $values['show_relations'] = $values['show_relations'] ? 1 : 0;
        $values['updated_at'] = date('Y-m-d H:i:s');
        $existing = Db::table('privacy_preferences')->where('user_id', $userId)->value('user_id');
        if ($existing !== null) {
            Db::table('privacy_preferences')->where('user_id', $userId)->update($values);
            return;
        }
        Db::table('privacy_preferences')->insert(['user_id' => $userId] + $values);
    }

    public function preferences(int $userId): PrivacyPreferences
    {
        $row = Db::table('privacy_preferences')->where('user_id', $userId)->find();
        if (!$row) return new PrivacyPreferences();
        return new PrivacyPreferences(
            (string) $row['profile_visibility'],
            (string) $row['messages_from'],
            (bool) $row['show_relations'],
            (string) ($row['gender_visibility'] ?? 'private'),
            (string) ($row['birth_date_visibility'] ?? 'private')
        );
    }

    public function addRelation(int $actorId, int $targetId, string $kind): bool
    {
        if ($this->hasRelation($actorId, $targetId, $kind)) return false;
        try {
            return Db::table('user_safety_relations')->insert([
                'actor_id' => $actorId,
                'subject_id' => $targetId,
                'relation' => $kind,
                'created_at' => date('Y-m-d H:i:s'),
            ]) === 1;
        } catch (\Throwable $exception) {
            if ($this->hasRelation($actorId, $targetId, $kind)) return false;
            throw $exception;
        }
    }

    public function removeRelation(int $actorId, int $targetId, string $kind): bool
    {
        return Db::table('user_safety_relations')->where([
            'actor_id' => $actorId,
            'subject_id' => $targetId,
            'relation' => $kind,
        ])->delete() > 0;
    }

    public function hasRelation(int $actorId, int $targetId, string $kind): bool
    {
        return Db::table('user_safety_relations')->where([
            'actor_id' => $actorId,
            'subject_id' => $targetId,
            'relation' => $kind,
        ])->find() !== null;
    }

    public function isBlockedEitherDirection(int $firstUserId, int $secondUserId): bool
    {
        return Db::table('user_safety_relations')
            ->where('relation', 'block')
            ->where(function ($query) use ($firstUserId, $secondUserId): void {
                $query->where(['actor_id' => $firstUserId, 'subject_id' => $secondUserId])
                    ->whereOr(['actor_id' => $secondUserId, 'subject_id' => $firstUserId]);
            })->value('actor_id') !== null;
    }

    public function blockedUserIds(int $userId): array
    {
        $outgoing = Db::table('user_safety_relations')->where(['actor_id' => $userId, 'relation' => 'block'])->column('subject_id');
        $incoming = Db::table('user_safety_relations')->where(['subject_id' => $userId, 'relation' => 'block'])->column('actor_id');
        return array_values(array_unique(array_map('intval', array_merge($outgoing, $incoming))));
    }

    public function userExists(int $userId): bool
    {
        return Db::table('users')->where('id', $userId)->whereNull('deleted_at')->value('id') !== null;
    }

    public function listBlocked(int $userId, ?string $cursor, int $limit, ?string $query): array
    {
        $builder = Db::table('user_safety_relations')->alias('r')
            ->join('users u', 'u.id = r.subject_id')
            ->where(['r.actor_id' => $userId, 'r.relation' => 'block'])
            ->field('u.id,u.uid,u.username,u.avatar,r.created_at AS blocked_at')
            ->order('r.created_at', 'desc')->order('r.subject_id', 'desc')
            ->limit($limit + 1);

        $anchor = $this->decodeCursor($cursor);
        if ($anchor !== null) {
            $builder->where(function ($where) use ($anchor): void {
                $where->where('r.created_at', '<', $anchor['blocked_at'])
                    ->whereOr(function ($same) use ($anchor): void {
                        $same->where('r.created_at', '=', $anchor['blocked_at'])
                            ->where('r.subject_id', '<', $anchor['subject_id']);
                    });
            });
        }
        $query = trim((string) ($query ?? ''));
        if ($query !== '') $builder->whereLike('u.username', '%' . addcslashes($query, '%_\\') . '%');

        $rows = $builder->select()->toArray();
        $hasMore = count($rows) > $limit;
        if ($hasMore) array_pop($rows);
        $items = array_map(static fn(array $row): array => [
            'user' => [
                'id' => (int) $row['id'],
                'username' => (string) $row['username'],
                'avatar' => trim((string) ($row['avatar'] ?? '')) === '' ? null : (string) $row['avatar'],
            ],
            'blocked_at' => (string) $row['blocked_at'],
        ], $rows);
        $last = $rows === [] ? null : end($rows);
        return [
            'items' => $items,
            'next_cursor' => $hasMore && is_array($last)
                ? $this->encodeCursor((string) $last['blocked_at'], (int) $last['id'])
                : null,
            'has_more' => $hasMore,
        ];
    }

    /** @return array{blocked_at:string,subject_id:int}|null */
    private function decodeCursor(?string $cursor): ?array
    {
        if ($cursor === null || $cursor === '') return null;
        $padding = strlen($cursor) % 4;
        if ($padding > 0) $cursor .= str_repeat('=', 4 - $padding);
        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        $payload = is_string($decoded) ? json_decode($decoded, true) : null;
        if (!is_array($payload)
            || !isset($payload['blocked_at'], $payload['subject_id'])
            || !is_string($payload['blocked_at'])
            || (int) $payload['subject_id'] < 1) {
            throw ApiException::validation(['cursor' => ['游标无效。']]);
        }
        return ['blocked_at' => $payload['blocked_at'], 'subject_id' => (int) $payload['subject_id']];
    }

    private function encodeCursor(string $blockedAt, int $subjectId): string
    {
        return rtrim(strtr(base64_encode((string) json_encode([
            'blocked_at' => $blockedAt, 'subject_id' => $subjectId,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    public function canMessage(int $senderId, int $recipientId): bool
    {
        if ($this->isBlockedEitherDirection($senderId, $recipientId)) return false;

        $preference = $this->preferences($recipientId)->toArray()['messages_from'];
        if ($preference === 'none') return false;
        if ($preference === 'following') return Social::isFollowing($recipientId, $senderId);
        return true;
    }

    public function createReport(ReportSubmission $submission, array $evidenceSnapshot): int
    {
        Db::startTrans();
        try {
            $now = date('Y-m-d H:i:s');
            $reportId = (int) Db::table('reports')->insertGetId([
                'reporter_id' => $submission->reporterId(),
                'target_type' => $submission->resource()->type(),
                'target_id' => $submission->resource()->id(),
                'reason' => $submission->reason(),
                'details' => $submission->details(),
                'evidence_snapshot' => json_encode($evidenceSnapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'status' => 'open',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            Db::table('report_events')->insert([
                'report_id' => $reportId,
                'actor_id' => $submission->reporterId(),
                'event_type' => 'submitted',
                'payload' => json_encode(['to_status' => 'open'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ]);
            Db::commit();
            return $reportId;
        } catch (\Throwable $exception) {
            Db::rollback();
            throw $exception;
        }
    }
}
