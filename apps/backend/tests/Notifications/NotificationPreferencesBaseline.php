<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/app/api/ApiException.php';
require $root . '/app/shared/Resource/ResourceRef.php';
require $root . '/app/shared/Transport/Http/CursorPage.php';
require $root . '/app/modules/Platform/Notifications/Contract/NotificationTypeCatalog.php';
require $root . '/app/modules/Platform/Notifications/Domain/NotificationIntent.php';
require $root . '/app/modules/Platform/Notifications/Contract/NotificationRepository.php';
require $root . '/app/modules/Platform/Notifications/Application/ManageNotifications.php';

use app\modules\Platform\Notifications\Application\ManageNotifications;
use app\modules\Platform\Notifications\Contract\NotificationRepository;
use app\modules\Platform\Notifications\Contract\NotificationTypeCatalog;
use app\modules\Platform\Notifications\Domain\NotificationIntent;

final class NotificationPreferencesRepo implements NotificationRepository
{
    public array $values = [];
    public array $created = [];
    public function create(NotificationIntent $intent): int { $this->created[] = $intent; return count($this->created); }
    public function recipientCanReceive(int $userId, string $type): bool { return str_starts_with($type, 'system.') || ($this->values[$type] ?? true); }
    public function listForUser(int $userId, ?string $cursor, int $limit, bool $unreadOnly, ?string $type, ?string $category): array { return ['items'=>[],'next_cursor'=>null,'has_more'=>false]; }
    public function markRead(int $userId, array $ids): int { return 0; }
    public function markAllRead(int $userId, ?string $category = null): int { return 0; }
    public function unreadCount(int $userId, ?string $category = null): int { return 0; }
    public function preferences(int $userId): array { $out=[]; foreach (NotificationTypeCatalog::configurableTypes() as $type) $out[$type]=$this->values[$type]??true; return $out; }
    public function savePreferences(int $userId, array $preferences): void { $this->values = array_replace($this->values, $preferences); }
}

$assert = static fn(bool $condition, string $message) => $condition ?: throw new RuntimeException($message);
$repo = new NotificationPreferencesRepo(); $service = new ManageNotifications($repo);
$result = $service->updatePreferences(7, ['in_app' => ['message' => false]]);
$assert($result['in_app']['message'] === false && $result['in_app']['social.followed'] === true, 'notification preference update must be partial');
$assert($service->create(new NotificationIntent(7, 'message:1', 'message', 8)) === 0, 'disabled future notification projection must be suppressed');
$assert($service->create(new NotificationIntent(7, 'system:1', 'system.security', null)) === 1, 'mandatory system notification must remain enabled');
$rejected = false;
try { $service->updatePreferences(7, ['in_app' => ['system.security' => false]]); } catch (\app\api\ApiException $exception) { $rejected = $exception->getHttpStatus() === 422; }
$assert($rejected, 'system notification preferences must be immutable');
$assert($result['locked_types'] === ['system.notice','system.security','system.moderation'], 'all current system notification types must be advertised as locked');
fwrite(STDOUT, "PASS notification preferences baseline\n");
