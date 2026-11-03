<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/vendor/topthink/framework/src/helper.php';

use app\api\controller\BaseController;
use app\modules\Content\Cards\Transport\Api\Cards;
use app\modules\Content\Comments\Transport\Api\Comments;
use app\modules\Content\Tags\Transport\Api\Tags;
use app\modules\Platform\Files\Transport\Api\Files;
use app\modules\Platform\Rbac\Transport\Api\Roles;
use app\modules\Platform\User\Transport\Api\Users;
use app\shared\Transport\Validation\BatchOperationInput;

$validationApp = new \think\App($root);
$validationLang = new \think\Lang($validationApp, ['use_cookie' => false]);
\think\Validate::maker(static function (\think\Validate $validator) use ($validationLang): void {
    $validator->setLang($validationLang);
    $validator->extend('notBlank', static fn($value): bool => is_scalar($value) && trim((string) $value) !== '');
});

final class AdminMutationValidationController extends BaseController
{
    public function validateScene(string $validator, string $scene, array $input): array
    {
        return $this->param($validator, $validator::$all_scene[$scene], $input);
    }
}

$controller = (new ReflectionClass(AdminMutationValidationController::class))->newInstanceWithoutConstructor();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$administrationScenes = [
    [Users::class, 'adminUpdate', ['id' => 1, 'status' => 0]],
];
$batchRequired = BatchOperationInput::$all_scene['AdminBatchOperate']['require'] ?? [];
$assert($batchRequired === ['ids', 'method'], 'administration batches must require only ids and method');
foreach ($administrationScenes as [$validator, $scene, $input]) {
    $result = $controller->validateScene($validator, $scene, $input);
    $assert(!array_key_exists('reason', $result), "{$validator}::{$scene} must not expose an administration reason");
}

$controller->validateScene(Cards::class, 'adminUpdate', ['id' => 1, 'status' => 0]);
$controller->validateScene(Comments::class, 'moderate', ['id' => 1, 'status' => 0]);
$controller->validateScene(Tags::class, 'adminUpdate', ['id' => 1, 'status' => 0]);

fwrite(STDOUT, "Admin mutation validation baseline passed.\n");

$pagination = \app\shared\Transport\Validation\PaginationInput::class;
$filters = $controller->validateScene($pagination, 'AdminUsers', ['status' => '0', 'role_id' => '2']);
$assert((int) $filters['status'] === 0 && (int) $filters['role_id'] === 2, 'managed user filters must be retained');
foreach ([['status' => '4'], ['role_id' => '0'], ['role_id' => 'abc']] as $invalid) {
    $rejected = false;
    try { $controller->validateScene($pagination, 'AdminUsers', $invalid); }
    catch (\app\api\ApiException $error) { $rejected = true; }
    $assert($rejected, 'invalid managed user filters must fail validation');
}
$public = $controller->validateScene($pagination, 'Defult', ['status' => '0', 'role_id' => '2']);
$assert(!isset($public['status']) && !isset($public['role_id']), 'managed filters must not leak to other pagination scenes');

// search_keys 字段名直达查询层 SQL 键位：格式非法必须在校验层 422，不得穿透产生 500。
$kept = $controller->validateScene($pagination, 'Defult', ['search_keys' => ['email', 'username'], 'search_value' => 'x']);
$assert(($kept['search_keys'] ?? null) === ['email', 'username'], 'valid search_keys must be retained');
foreach ([
    ['search_keys' => ['password;drop']],
    ['search_keys' => ['a.b']],
    ['search_keys' => ['a b']],
    ['search_keys' => ['`email`']],
    ['search_keys' => ['%']],
    ['search_keys' => [str_repeat('x', 65)]],
    ['search_keys' => ['ok', 123]],
] as $invalid) {
    $rejected = false;
    try { $controller->validateScene($pagination, 'Defult', $invalid + ['search_value' => 'x']); }
    catch (\app\api\ApiException $error) { $rejected = true; }
    $assert($rejected, 'malformed search_keys must fail validation');
}
