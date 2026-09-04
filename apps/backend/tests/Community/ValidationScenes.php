<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/vendor/topthink/framework/src/helper.php';

// The production container injects Lang into every validator. This isolated
// contract test uses the global validate() helper directly, so mirror that
// framework binding before exercising an invalid rule and its error message.
$validationApp = new \think\App($root);
$validationLang = new \think\Lang($validationApp, ['use_cookie' => false]);
\think\Validate::maker(static function (\think\Validate $validator) use ($validationLang): void {
    $validator->setLang($validationLang);
});

use app\api\ApiException;
use app\api\controller\BaseController;
use app\modules\Platform\Community\Transport\Api\Community;

final class CommunityValidationController extends BaseController
{
    public function validateScene(string $scene, array $input): array
    {
        return $this->param(Community::class, Community::$all_scene[$scene], $input);
    }
}

$controller = (new ReflectionClass(CommunityValidationController::class))->newInstanceWithoutConstructor();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

foreach (['cursor', 'notifications', 'notificationUnread', 'notificationReadAll', 'stream', 'draft', 'preferences'] as $scene) {
    $assert($controller->validateScene($scene, []) === [], "empty {$scene} scene should pass");
}

$expectFields = static function (string $scene, array $input, array $expected) use ($controller, $assert): void {
    try {
        $controller->validateScene($scene, $input);
        throw new RuntimeException("{$scene} should reject missing required fields");
    } catch (ApiException $exception) {
        $data = (array) $exception->getData();
        $actual = array_keys((array) ($data['errors'] ?? $data));
        sort($actual);
        sort($expected);
        $assert($actual === $expected, "{$scene} reported unrelated fields: " . implode(',', $actual));
    }
};

$expectFields('read', [], ['ids']);
$expectFields('messageSend', [], ['recipient_uid', 'body']);
$expectFields('report', [], ['resource_type', 'resource_id', 'reason']);

$assert($controller->validateScene('cursor', ['limit' => 50])['limit'] === 50, 'cursor limit should validate');
$assert($controller->validateScene('preferences', ['show_relations' => true])['show_relations'] === true, 'JSON boolean preferences should validate');
$notificationFilters = $controller->validateScene('notifications', [
    'type' => 'content.replied',
    'category' => 'interaction',
]);
$assert($notificationFilters['type'] === 'content.replied', 'notification type should accept the frozen interaction types');
$assert($notificationFilters['category'] === 'interaction', 'notification category should accept the frozen categories');
$engagementFilters = $controller->validateScene('notifications', ['category' => 'engagement']);
$assert($engagementFilters['category'] === 'engagement', 'notification list should accept the engagement query category');
$assert($controller->validateScene('notificationUnread', ['category' => 'engagement'])['category'] === 'engagement', 'notification unread should accept the engagement query category');
$assert($controller->validateScene('notificationReadAll', ['category' => 'engagement'])['category'] === 'engagement', 'notification read-all should accept the engagement query category');
$invalidEngagement = false;
try { $controller->validateScene('notifications', ['category' => 'unknown']); } catch (ApiException) { $invalidEngagement = true; }
$assert($invalidEngagement, 'unknown notification query categories must be rejected');

fwrite(STDOUT, "PASS community validation scenes\n");
