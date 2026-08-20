<?php

namespace app\modules\Platform\Safety\Infrastructure;

use app\modules\Platform\Safety\Contract\SafetyRepository;
use app\modules\Platform\Safety\Domain\PrivacyPreferences;
use app\modules\Platform\Safety\Domain\ReportSubmission;
use app\modules\Platform\Social\Public\Social;
use think\facade\Db;

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
            (bool) $row['show_relations']
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

    public function canMessage(int $senderId, int $recipientId): bool
    {
        $blocked = Db::table('user_safety_relations')
            ->where('relation', 'block')
            ->where(function ($query) use ($senderId, $recipientId): void {
                $query->where(['actor_id' => $senderId, 'subject_id' => $recipientId])
                    ->whereOr(['actor_id' => $recipientId, 'subject_id' => $senderId]);
            })->find();
        if ($blocked !== null) return false;

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
