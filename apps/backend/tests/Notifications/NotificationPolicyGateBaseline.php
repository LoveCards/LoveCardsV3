<?php
declare(strict_types=1);

/**
 * Notification policy gate + CAS admin apply baseline (contract §5.2).
 * Covers: not-configured fail-open, applied, skipped, stale revision, locked rules, unknown rules.
 */

$root = dirname(__DIR__, 2);
require $root . '/app/shared/Resource/ResourceRef.php';
require $root . '/app/shared/Transport/Http/CursorPage.php';
require $root . '/app/api/ApiException.php';
require $root . '/app/modules/Platform/Eventing/Public/RealtimeSignal.php';
require $root . '/app/modules/Platform/Eventing/Public/RealtimeSignalPublisher.php';
require $root . '/app/modules/Platform/Notifications/Contract/NotificationRepository.php';
require $root . '/app/modules/Platform/Notifications/Contract/NotificationTypeCatalog.php';
require $root . '/app/modules/Platform/Notifications/Contract/NotificationPolicyStore.php';
require $root . '/app/modules/Platform/Notifications/Domain/NotificationIntent.php';
require $root . '/app/modules/Platform/Notifications/Domain/NotificationCursor.php';
require $root . '/app/modules/Platform/Notifications/Application/NotificationPolicyGate.php';
require $root . '/app/modules/Platform/Notifications/Application/ManageNotificationPolicies.php';
require $root . '/app/modules/Platform/Notifications/Application/ManageNotifications.php';

use app\modules\Platform\Notifications\Application\ManageNotificationPolicies;
use app\modules\Platform\Notifications\Application\ManageNotifications;
use app\modules\Platform\Notifications\Application\NotificationPolicyGate;
use app\modules\Platform\Notifications\Contract\NotificationPolicyStore;
use app\modules\Platform\Notifications\Contract\NotificationRepository;
use app\modules\Platform\Notifications\Domain\NotificationIntent;

final class PolicyFakeStore implements NotificationPolicyStore
{
    public array $snapshot;
    public int $revision;
    /** @var array<int, array<string, mixed>> */
    public array $history;
    public function __construct()
    {
        $this->revision = 3;
        $this->history = [];
        $this->snapshot = [
            'content.liked' => ['enabled' => true, 'locked' => false, 'user_disableable' => true, 'notification_type' => 'content.card_liked'],
            'social.followed' => ['enabled' => false, 'locked' => false, 'user_disableable' => true, 'notification_type' => 'social.followed'],
            'system.notification.issued' => ['enabled' => true, 'locked' => true, 'user_disableable' => false, 'notification_type' => 'system.moderation'],
        ];
        $this->history[3] = $this->snapshot;
    }
    public function snapshot(int $policyRevision): ?array { return $this->history[$policyRevision] ?? null; }
    public function currentRevision(): ?int { return $this->revision; }
    public function listCurrent(): array
    {
        $rows = [];
        foreach ($this->snapshot as $eventType => $rule) {
            $rows[] = ['event_type' => $eventType, 'event_version' => 1, 'rule_key' => 'notify', 'enabled' => $rule['enabled'], 'locked' => $rule['locked'], 'policy_revision' => $this->revision];
        }
        return $rows;
    }
    public function applyChanges(array $changes, int $expectedRevision, ?int $actorId, ?string $reason): array
    {
        if ($expectedRevision !== $this->revision) throw new \RuntimeException('stale-revision');
        $snapshot = $this->history[$expectedRevision];
        foreach ($changes as $change) {
            if (!isset($snapshot[$change['event_type']])) throw new \RuntimeException('unknown-rule');
            if ($snapshot[$change['event_type']]['locked']) throw new \RuntimeException('locked');
            $snapshot[$change['event_type']]['enabled'] = $change['enabled'];
        }
        $this->revision += 1;
        $this->history[$this->revision] = $snapshot;
        $this->snapshot = $snapshot;
        return ['revision' => $this->revision, 'policies' => $this->listCurrent(), 'before' => $this->listCurrent()];
    }
}

final class PolicyFakeRepository implements NotificationRepository
{
    public array $items = [];
    public function create(NotificationIntent $intent): array
    {
        $id = count($this->items) + 1;
        $this->items[$id] = $intent;
        return [$id, true, '2026-11-06 00:00:00'];
    }
    public function recipientCanReceive(int $userId, string $type): bool { return true; }
    public function listForUser(int $userId, ?string $cursor, int $limit, bool $unreadOnly, ?string $type, ?string $category): array { return ['items' => [], 'next_cursor' => null, 'has_more' => false]; }
    public function markRead(int $userId, array $ids): int { return 0; }
    public function markAllRead(int $userId, ?string $category = null): int { return 0; }
    public function unreadCount(int $userId, ?string $category = null): int { return 0; }
    public function preferences(int $userId): array { return []; }
    public function savePreferences(int $userId, array $preferences): void {}
}

final class PolicyFakeEventRecorder
{
    public array $decisions = [];
    public function notePolicyDecision(int $deliveryId, string $decision): void { $this->decisions[] = [$deliveryId, $decision]; }
}

if (!function_exists('app')) {
    function app(string $class): object { return $GLOBALS['__policy_event_recorder']; }
}

$assert = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };

$store = new PolicyFakeStore();
$gate = new NotificationPolicyGate($store);

$assert($gate->decide('content.liked', 3) === 'applied', 'enabled rule at frozen revision should be applied');
$assert($gate->decide('social.followed', 3) === 'skipped', 'disabled rule should be skipped');
$assert($gate->decide('message.sent', 3) === 'not-configured', 'event without a policy row should stay not-configured');
$assert($gate->decide('content.liked', 2) === 'not-configured', 'missing historical snapshot should be not-configured');
$assert($gate->decide('content.liked', null) === 'not-configured', 'null frozen revision should be not-configured');

// CAS admin flow
$policies = new ManageNotificationPolicies($store);
$applied = $policies->apply(['expected_revision' => 3, 'policies' => [['event_type' => 'content.liked', 'enabled' => false]]], 9);
$assert($applied['revision'] === 4, 'CAS apply should bump the global revision');
$assert($gate->decide('content.liked', 3) === 'applied', 'frozen in-flight deliveries keep the old snapshot semantics');
$assert($gate->decide('content.liked', 4) === 'skipped', 'new deliveries should observe the new revision');

$staleFailed = false;
try { $policies->apply(['expected_revision' => 3, 'policies' => [['event_type' => 'content.liked', 'enabled' => true]]], 9); } catch (app\api\ApiException $e) { $staleFailed = true; $assert($e->getCode() === 9016, 'stale revision should map to code 9016'); }
$assert($staleFailed, 'stale expected_revision must be rejected');

$lockedFailed = false;
try { $policies->apply(['expected_revision' => 4, 'policies' => [['event_type' => 'system.notification.issued', 'enabled' => false]]], 9); } catch (app\api\ApiException $e) { $lockedFailed = true; $assert($e->getCode() === 9010, 'locked rule should be a validation error'); }
$assert($lockedFailed, 'locked system policy must not be disableable');

$unknownFailed = false;
try { $policies->apply(['expected_revision' => 4, 'policies' => [['event_type' => 'nope.missing', 'enabled' => true]]], 9); } catch (app\api\ApiException $e) { $unknownFailed = true; }
$assert($unknownFailed, 'unknown rules must be rejected');

// projection path: skipped policy suppresses the projection but records the decision
$GLOBALS['__policy_event_recorder'] = $recorder = new PolicyFakeEventRecorder();
$repository = new PolicyFakeRepository();
$issuer = new ManageNotifications($repository, null, null, $gate);

$liked = $issuer->create(new NotificationIntent(5, 'like:card:1:actor:9', 'content.card_liked', 9), ['content.liked', 4, 77]);
$assert($liked === 0, 'skipped policy must not project a notification');
$assert($recorder->decisions === [[77, 'skipped']], 'skipped decision must be recorded on the delivery');

$followed = $issuer->create(new NotificationIntent(5, 'sys:1', 'system.moderation', null), ['system.notification.issued', 4, 78]);
$assert($followed > 0, 'locked enabled system policy must still project');
$assert($recorder->decisions[1] === [78, 'applied'], 'applied decision must be recorded');

$legacy = $issuer->create(new NotificationIntent(6, 'legacy:1', 'content.card_liked', 9), ['brand.new.event', 4, 79]);
$assert($legacy > 0, 'not-configured events must fail open and keep notifying');
$assert(count($recorder->decisions) === 2, 'not-configured must not write a policy decision column');

fwrite(STDOUT, "PASS notification policy gate baseline\n");
