<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/app/api/ApiException.php';
require $root . '/app/shared/Resource/ResourceRef.php';
require $root . '/app/shared/Transport/Http/CursorPage.php';
require $root . '/app/shared/Transaction/TransactionManager.php';
require $root . '/app/shared/Observability/AuditLogger.php';
require $root . '/app/modules/Platform/Safety/Domain/PrivacyPreferences.php';
require $root . '/app/modules/Platform/Safety/Domain/SafetyRelation.php';
require $root . '/app/modules/Platform/Safety/Domain/ReportSubmission.php';
require $root . '/app/modules/Platform/Safety/Contract/SafetyRepository.php';
require $root . '/app/modules/Platform/Safety/Contract/ReportTargetResolver.php';
require $root . '/app/modules/Platform/Social/Contract/SocialGraphRepository.php';
require $root . '/app/modules/Platform/Safety/Application/ManageSafety.php';

use app\modules\Platform\Safety\Application\ManageSafety;
use app\modules\Platform\Safety\Contract\ReportTargetResolver;
use app\modules\Platform\Safety\Contract\SafetyRepository;
use app\modules\Platform\Safety\Domain\PrivacyPreferences;
use app\modules\Platform\Safety\Domain\ReportSubmission;
use app\modules\Platform\Social\Contract\SocialGraphRepository;
use app\shared\Observability\AuditLogger;
use app\shared\Resource\ResourceRef;
use app\shared\Transaction\TransactionManager;

final class SelfServiceSafetyRepo implements SafetyRepository
{
    public PrivacyPreferences $value;
    public array $relations = [];
    public function __construct() { $this->value = new PrivacyPreferences('followers', 'none', false); }
    public function savePreferences(int $userId, PrivacyPreferences $preferences): void { $this->value = $preferences; }
    public function preferences(int $userId): PrivacyPreferences { return $this->value; }
    public function addRelation(int $actorId, int $targetId, string $kind): bool { $key = "$actorId:$targetId:$kind"; if (isset($this->relations[$key])) return false; $this->relations[$key] = true; return true; }
    public function removeRelation(int $actorId, int $targetId, string $kind): bool { $key = "$actorId:$targetId:$kind"; if (!isset($this->relations[$key])) return false; unset($this->relations[$key]); return true; }
    public function hasRelation(int $actorId, int $targetId, string $kind): bool { return isset($this->relations["$actorId:$targetId:$kind"]); }
    public function isBlockedEitherDirection(int $firstUserId, int $secondUserId): bool { return $this->hasRelation($firstUserId, $secondUserId, 'block') || $this->hasRelation($secondUserId, $firstUserId, 'block'); }
    public function blockedUserIds(int $userId): array { return [2]; }
    public function userExists(int $userId): bool { return $userId === 2; }
    public function listBlocked(int $userId, ?string $cursor, int $limit, ?string $query): array { return ['items' => [['user' => ['id' => 2, 'username' => 'blocked_user', 'avatar' => null], 'blocked_at' => '2026-08-25 00:00:00']], 'next_cursor' => 'opaque', 'has_more' => true]; }
    public function canMessage(int $senderId, int $recipientId): bool { return !$this->isBlockedEitherDirection($senderId, $recipientId); }
    public function createReport(ReportSubmission $submission, array $evidenceSnapshot): int { return 1; }
}
final class SelfServiceTargets implements ReportTargetResolver { public function snapshotForReporter(int $reporterId, ResourceRef $resource): ?array { return null; } }
final class SelfServiceSocial implements SocialGraphRepository
{
    public array $unfollowed = [];
    public function profileByHandle(string $handle): ?array { return null; }
    public function userExists(int $userId): bool { return true; }
    public function follow(int $actorId, int $targetId): bool { return true; }
    public function unfollow(int $actorId, int $targetId): bool { $this->unfollowed[] = [$actorId, $targetId]; return true; }
    public function isFollowing(int $actorId, int $targetId): bool { return false; }
    public function listFollowers(int $userId, ?string $cursor, int $limit, array $excludedUserIds = []): array { return ['items'=>[],'next_cursor'=>null,'has_more'=>false]; }
    public function listFollowing(int $userId, ?string $cursor, int $limit, array $excludedUserIds = []): array { return ['items'=>[],'next_cursor'=>null,'has_more'=>false]; }
    public function counts(int $userId): array { return ['followers'=>0,'following'=>0]; }
}
final class SelfServiceTx implements TransactionManager { public int $calls = 0; public function run(callable $callback): mixed { $this->calls++; return $callback(); } }
final class SelfServiceAudit implements AuditLogger { public array $records = []; public function record(?int $actorId, string $action, string $resourceType, ?string $resourceId, array $metadata = []): void { $this->records[] = $action; } }

$assert = static fn(bool $condition, string $message) => $condition ?: throw new RuntimeException($message);
$repo = new SelfServiceSafetyRepo(); $social = new SelfServiceSocial(); $tx = new SelfServiceTx(); $audit = new SelfServiceAudit();
$service = new ManageSafety($repo, new SelfServiceTargets(), $social, $tx, $audit);
$updated = $service->savePreferences(1, ['show_relations' => true]);
$assert($updated->toArray() === ['profile_visibility'=>'followers','messages_from'=>'none','show_relations'=>true,'gender_visibility'=>'private','birth_date_visibility'=>'private'], 'partial privacy update must preserve omitted values');
$assert($service->addRelation(1, 2, 'block'), 'first block must be created');
$assert($social->unfollowed === [[1,2],[2,1]], 'blocking must remove both follow directions in the transaction');
$page = $service->listBlocked(1, null, 20, null);
$assert(isset($page['data'][0]['user']) && $page['next_cursor'] === 'opaque' && $page['has_more'], 'blocked users must use the shared cursor page contract');
$assert(count($audit->records) === 2 && $tx->calls === 2, 'privacy and block writes must be transactionally audited');
fwrite(STDOUT, "PASS user safety self-service baseline\n");
