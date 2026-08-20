<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/vendor/topthink/framework/src/helper.php';

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

foreach (['cursor', 'notifications', 'stream', 'draft', 'preferences'] as $scene) {
    $assert($controller->validateScene($scene, []) === [], "empty {$scene} scene should pass");
}

$expectFields = static function (string $scene, array $input, array $expected) use ($controller, $assert): void {
    try {
        $controller->validateScene($scene, $input);
        throw new RuntimeException("{$scene} should reject missing required fields");
    } catch (ApiException $exception) {
        $actual = array_keys((array) $exception->getData());
        sort($actual);
        sort($expected);
        $assert($actual === $expected, "{$scene} reported unrelated fields: " . implode(',', $actual));
    }
};

$expectFields('read', [], ['ids']);
$expectFields('messageSend', [], ['recipient_id', 'body']);
$expectFields('report', [], ['resource_type', 'resource_id', 'reason']);

$assert($controller->validateScene('cursor', ['limit' => 50])['limit'] === 50, 'cursor limit should validate');
$assert($controller->validateScene('preferences', ['show_relations' => true])['show_relations'] === true, 'JSON boolean preferences should validate');

fwrite(STDOUT, "PASS community validation scenes\n");
