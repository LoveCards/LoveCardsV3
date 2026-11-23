<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/vendor/topthink/framework/src/helper.php';

use app\api\ApiException;
use app\api\controller\BaseController;
use app\modules\Content\Cards\Transport\Api\PublicCardSearchQuery;

final class PublicCardSearchValidationController extends BaseController
{
    public function validateSearch(array $input): array
    {
        return $this->param(PublicCardSearchQuery::class, PublicCardSearchQuery::$all_scene['search'], $input);
    }
}

$controller = (new ReflectionClass(PublicCardSearchValidationController::class))->newInstanceWithoutConstructor();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
foreach (['城市', '#猫', 'ab', '🌸🌿'] as $term) {
    $input = ['search_value' => $term, 'sort' => 'match', 'page' => '2', 'list_rows' => '24'];
    $assert($controller->validateSearch($input) === $input, 'supported search input must pass');
}
foreach ([[], ['search_value' => ' '], ['search_value' => '猫'], ['search_value' => '# '],
    ['search_value' => str_repeat('a', 101)], ['search_value' => '城市', 'sort' => 'goods'],
    ['search_value' => '城市', 'page' => '1.5'], ['search_value' => '城市', 'list_rows' => 31]] as $input) {
    try {
        $controller->validateSearch($input);
        throw new RuntimeException('invalid search must be rejected: ' . json_encode($input));
    } catch (ApiException $error) {
        $assert($error->getCode() === ApiException::CODE_PARAM_INVALID, 'invalid search must report a parameter error');
    }
}
$filtered = $controller->validateSearch(['search_value' => '城市', 'show_deleted' => 2, 'search_keys' => ['password']]);
$assert($filtered === ['search_value' => '城市'], 'public search must discard undeclared list filters');
$schema = PublicCardSearchQuery::requestSchemas()['search'];
$assert($schema['properties']['sort']['enum'] === ['match', 'latest'], 'sort enum must match the SDK contract');
$assert($schema['required'] === ['search_value'], 'public search must require an explicit keyword');
echo "PASS public card search input contract\n";
