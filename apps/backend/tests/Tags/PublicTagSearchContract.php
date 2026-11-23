<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/vendor/topthink/framework/src/helper.php';
require $root . '/app/api/ApiException.php';
require $root . '/app/api/controller/BaseController.php';

use app\api\ApiException;
use app\api\controller\BaseController;
use app\modules\Content\Tags\Service\Tags;
use app\modules\Content\Tags\Transport\Api\PublicTagSearchQuery;
use app\modules\Content\Tags\Transport\Api\PublicTagSearchResource;

final class PublicTagSearchValidationController extends BaseController
{
    public function validateSearch(array $input): array
    {
        return $this->param(PublicTagSearchQuery::class, PublicTagSearchQuery::$all_scene['search'], $input);
    }
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$controller = (new ReflectionClass(PublicTagSearchValidationController::class))->newInstanceWithoutConstructor();
$valid = $controller->validateSearch(['search_value' => '#海边', 'page' => '2', 'list_rows' => '12', 'status' => '3']);
$assert($valid === ['search_value' => '#海边', 'page' => '2', 'list_rows' => '12'], 'public tag search must keep only declared inputs');

foreach ([[], ['search_value' => '  '], ['search_value' => '#'], ['search_value' => str_repeat('a', 256)], ['search_value' => '海', 'list_rows' => '51']] as $input) {
    try {
        $controller->validateSearch($input);
        throw new RuntimeException('invalid public tag search must be rejected');
    } catch (ApiException $exception) {
        $assert($exception->getCode() === ApiException::CODE_PARAM_INVALID, 'invalid public tag search must return parameter error');
    }
}

try {
    Tags::searchPublic(['search_value' => '#']);
    throw new RuntimeException('service must reject empty normalized query');
} catch (ApiException $exception) {
    $assert($exception->getCode() === ApiException::CODE_PARAM_INVALID, 'service must not enumerate on empty normalized query');
}

$schema = PublicTagSearchQuery::requestSchemas()['search'];
$assert($schema['required'] === ['search_value'], 'SDK contract must require search_value');
$assert($schema['properties']['list_rows']['maximum'] === 50, 'SDK page size must match runtime limit');
$assert($schema['additionalProperties'] === false, 'public tag search must not expose internal filters');

$response = PublicTagSearchResource::paginate(['data' => [['id' => 9, 'name' => '海边', 'status' => 0, 'user_id' => 7]], 'total' => 1]);
$assert($response['data'][0]['id'] === 9 && $response['data'][0]['name'] === '海边' && $response['total'] === 1, 'tag search response must preserve pagination and public identity');
$assert(array_keys($response['data'][0]) === ['id', 'name'], 'public search must not expose creator or status fields');

$routes = file_get_contents($root . '/app/api/route/tags.php');
$assert(strpos($routes, "Route::get('tags/search'") < strpos($routes, "Route::get('tags/:id'"), 'static search route must precede dynamic tag route');

fwrite(STDOUT, "PASS public tag search contract\n");
