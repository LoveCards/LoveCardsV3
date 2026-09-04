<?php
declare(strict_types=1);

// Contract-level coverage for the public user-card vertical slice. The
// persistence path is exercised by the application tests; this fixture keeps
// the HTTP boundary deterministic and does not require a live MySQL service.
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/vendor/topthink/framework/src/helper.php';
require $root . '/app/api/ApiException.php';
require $root . '/app/api/controller/BaseController.php';
require $root . '/app/modules/Content/Cards/Transport/Api/PublicUserCards.php';

use app\api\ApiException;
use app\api\controller\BaseController;
use app\modules\Content\Cards\Transport\Api\PublicUserCards;

final class PublicUserCardsValidationController extends BaseController
{
    public function validateList(array $input): array
    {
        return $this->param(PublicUserCards::class, PublicUserCards::$all_scene['list'], $input);
    }
}

$controller = (new ReflectionClass(PublicUserCardsValidationController::class))->newInstanceWithoutConstructor();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$assert($controller->validateList([]) === [], 'empty public card query should use server defaults');
$assert($controller->validateList(['page' => '2', 'list_rows' => '50']) === ['page' => '2', 'list_rows' => '50'], 'page/list_rows should remain query-compatible');

$schemas = PublicUserCards::requestSchemas();
$assert(($schemas['list']['additionalProperties'] ?? true) === false, 'public card query schema must reject undocumented filters');
$assert(PublicUserCards::$all_scene['list']['normal'] === ['page', 'list_rows'], 'runtime scene must expose only page/list_rows');
$assert((new ReflectionClass(PublicUserCards::class))->getDefaultProperties()['rule']['list_rows'] === 'number|between:1,100', 'runtime validator must cap list_rows at 100');
$assert(($schemas['list']['properties']['list_rows']['maximum'] ?? null) === 100, 'public card page size must be capped at 100');

$assert(ApiException::getHttpStatusByCode(ApiException::CODE_PROFILE_NOT_FOUND) === 404, 'missing public profile must map to 404');
$assert(ApiException::getHttpStatusByCode(ApiException::CODE_PROFILE_RESTRICTED) === 403, 'restricted public profile must map to 403');

fwrite(STDOUT, "PASS public user-card API contract\n");
