<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/app/api/ApiException.php';
require $root . '/app/shared/Resource/ResourceRef.php';
require $root . '/app/modules/Platform/Safety/Domain/PrivacyPreferences.php';
require $root . '/app/modules/Platform/Safety/Domain/SafetyRelation.php';
require $root . '/app/modules/Platform/Safety/Domain/ReportSubmission.php';
require $root . '/app/modules/Platform/Safety/Contract/SafetyRepository.php';
require $root . '/app/modules/Platform/Safety/Contract/ReportTargetResolver.php';
require $root . '/app/modules/Platform/Safety/Application/ManageSafety.php';

use app\modules\Platform\Safety\Application\ManageSafety;
use app\modules\Platform\Safety\Contract\SafetyRepository;
use app\modules\Platform\Safety\Contract\ReportTargetResolver;
use app\modules\Platform\Safety\Domain\PrivacyPreferences;
use app\modules\Platform\Safety\Domain\ReportSubmission;
use app\shared\Resource\ResourceRef;

final class FakeSafetyRepository implements SafetyRepository
{
    public PrivacyPreferences $preferences;
    public array $relations = [];
    public array $evidence = [];
    public function __construct() { $this->preferences = new PrivacyPreferences(); }
    public function savePreferences(int $userId, PrivacyPreferences $preferences): void { $this->preferences = $preferences; }
    public function preferences(int $userId): PrivacyPreferences { return $this->preferences; }
    public function addRelation(int $actorId, int $targetId, string $kind): bool { $key = "{$actorId}:{$targetId}:{$kind}"; if (isset($this->relations[$key])) return false; return $this->relations[$key] = true; }
    public function removeRelation(int $actorId, int $targetId, string $kind): bool { $key = "{$actorId}:{$targetId}:{$kind}"; if (!isset($this->relations[$key])) return false; unset($this->relations[$key]); return true; }
    public function hasRelation(int $actorId, int $targetId, string $kind): bool { return isset($this->relations["{$actorId}:{$targetId}:{$kind}"]); }
    public function isBlockedEitherDirection(int $firstUserId, int $secondUserId): bool { return $this->hasRelation($firstUserId, $secondUserId, 'block') || $this->hasRelation($secondUserId, $firstUserId, 'block'); }
    public function blockedUserIds(int $userId): array { return []; }
    public function userExists(int $userId): bool { return $userId > 0; }
    public function listBlocked(int $userId, ?string $cursor, int $limit, ?string $query): array { return ['items' => [], 'next_cursor' => null, 'has_more' => false]; }
    public function canMessage(int $senderId, int $recipientId): bool { return !$this->hasRelation($recipientId, $senderId, 'block'); }
    public array $reportInserts = [];
    public ?int $existingOpen = null;
    public function findOpenReport(int $reporterId, string $targetType, string $targetId): ?int { return $this->existingOpen; }
    public function createReport(ReportSubmission $submission, array $evidenceSnapshot, string $targetId): int { $this->evidence = $evidenceSnapshot; $this->reportInserts[] = $targetId; return 7; }
}

final class FakeReportTargetResolver implements ReportTargetResolver
{
    public function snapshotForReporter(int $reporterId, ResourceRef $resource): ?array
    {
        if ($resource->type() === 'comment') return ['type' => 'comment', 'captured' => ['id' => $resource->id()]];
        // 用户举报：请求携带 12 位公开 uid，快照解析出行后主键是 users.id。
        if ($resource->type() === 'user') return ['type' => 'user', 'captured' => ['id' => 7, 'uid' => $resource->id()]];
        return null;
    }
}

$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$repository = new FakeSafetyRepository();
$safety = new ManageSafety($repository, new FakeReportTargetResolver());
$preferences = new PrivacyPreferences('followers', 'following', false);
$safety->savePreferences(1, $preferences);
$assert($safety->preferences(1)->toArray()['profile_visibility'] === 'followers', 'preferences should persist');

$assert($safety->addRelation(1, 2, 'block'), 'first block should create relation');
$assert(!$safety->addRelation(1, 2, 'block'), 'repeated block should be idempotent');
$assert($safety->removeRelation(1, 2, 'block'), 'unblock should remove relation');

$selfRejected = false;
try { $safety->addRelation(1, 1, 'mute'); } catch (\app\api\ApiException $exception) { $selfRejected = $exception->getHttpStatus() === 422; }
$assert($selfRejected, 'self safety relation must be rejected');

$report = new ReportSubmission(1, new ResourceRef('comment', 9), 'abuse', '测试');
$assert($safety->report($report) === 7, 'report should return stable id');
$assert($repository->evidence === ['type' => 'comment', 'captured' => ['id' => '9']], 'report must persist the resolved evidence snapshot');
$assert($repository->reportInserts === ['9'], 'comment report must store the canonical target id');

// 用户举报按公开 uid 提交，但落库主键必须统一为 users.id（管理端目标链接的导航键）。
$assert($safety->report(new ReportSubmission(1, new ResourceRef('user', '123456789012'), 'abuse')) === 7, 'user report must create the case');
$assert($repository->reportInserts === ['9', '7'], 'user report must store the canonical internal user id');

// 重复举报幂等：同一目标已有待处理/处理中案件时返回原案件，不再插入。
$repository->existingOpen = 7;
$assert($safety->report(new ReportSubmission(1, new ResourceRef('user', '123456789012'), 'abuse')) === 7, 'duplicate report must return the open case id');
$assert($repository->reportInserts === ['9', '7'], 'duplicate report must not create a second case');

$missingRejected = false;
try { $safety->report(new ReportSubmission(1, new ResourceRef('card', 99), 'spam')); }
catch (\app\api\ApiException $exception) { $missingRejected = $exception->getHttpStatus() === 404; }
$assert($missingRejected, 'missing or invisible report targets must be rejected');

fwrite(STDOUT, "PASS safety behavior baseline\n");
