<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

use app\modules\Operations\Administration\Transport\Api\AdminNotificationPoliciesApplyResource;
use app\modules\Operations\Administration\Transport\Api\AdminNotificationPoliciesResource;
use app\modules\Platform\Eventing\Public\HandlerRegistry;

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$registrations = array_values(array_filter(
    (\app\EventHandlers::create())->catalog(),
    static fn(array $row): bool => $row['queue_name'] === 'notifications'
));
$eventTypes = array_column($registrations, 'event_type');
$assert(count($eventTypes) === 7 && count(array_unique($eventTypes)) === 7, 'current notification events must have unique event types');

$rules = array_map(static fn(string $eventType): array => [
    'event_type' => $eventType,
    'event_version' => 1,
    'rule_key' => 'notify',
    'enabled' => $eventType !== 'message.sent',
    'notification_type' => null,
    'recipient_rule' => 'handler',
    'user_disableable' => $eventType !== 'system.notification.issued',
    'locked' => $eventType === 'system.notification.issued',
    'policy_revision' => 13,
], $eventTypes);
$original = $rules;
$catalog = AdminNotificationPoliciesResource::make(['revision' => 13, 'policies' => $rules]);
$applied = AdminNotificationPoliciesApplyResource::make(['revision' => 14, 'policies' => $rules]);
$assert($rules === $original, 'resource presentation must not mutate raw policy rows');
$assert(count($catalog['policies']) === 7 && count($applied['policies']) === 7, 'both responses must keep every policy');
foreach ($catalog['policies'] as $index => $row) {
    $assert(is_string($row['display_name'] ?? null) && preg_match('/\p{Han}/u', $row['display_name']) === 1, 'registered notification must have a Chinese name');
    $assert(is_string($row['impact_text'] ?? null) && $row['impact_text'] !== '', 'registered notification must explain its impact');
    $assert($row['display_name'] === $applied['policies'][$index]['display_name'], 'GET and PUT must use the same name');
    $assert($row['impact_text'] === $applied['policies'][$index]['impact_text'], 'GET and PUT must use the same impact');
    $assert($row['enabled'] === $original[$index]['enabled'] && $row['locked'] === $original[$index]['locked'], 'presentation must not change policy behavior');
}

$unknown = $rules[0];
$unknown['event_type'] = 'future.unknown';
$unknown['enabled'] = false;
$unknown['locked'] = true;
$fallback = AdminNotificationPoliciesResource::make(['revision' => 13, 'policies' => [$unknown]])['policies'][0];
$assert($fallback['display_name'] === null && $fallback['impact_text'] === null, 'unknown event must have explicit missing presentation');
$assert($fallback['enabled'] === false && $fallback['locked'] === true, 'unknown event must preserve server policy flags');

$itemSchema = AdminNotificationPoliciesResource::schema()['properties']['policies']['items'] ?? [];
$assert(isset($itemSchema['properties']['display_name'], $itemSchema['properties']['impact_text']), 'policy item schema must declare presentation fields');
$assert(isset(AdminNotificationPoliciesApplyResource::schema()['properties']['policies']['items']), 'apply response must declare policy item schema');

fwrite(STDOUT, "PASS notification policy presentation baseline\n");
