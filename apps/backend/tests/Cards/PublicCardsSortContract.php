<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/vendor/topthink/framework/src/helper.php';
require $root . '/app/api/ApiException.php';
require $root . '/app/api/controller/BaseController.php';

use app\api\ApiException;
use app\api\controller\BaseController;
use app\modules\Content\Cards\Transport\Api\PublicCardsQuery;
use app\shared\Infrastructure\Persistence\ModelQuery;

final class PublicCardsSortValidationController extends BaseController
{
    public function validateList(array $input): array
    {
        return $this->param(PublicCardsQuery::class, PublicCardsQuery::$all_scene['list'], $input);
    }
}

final class PublicCardsSortQuery
{
    public array $orders = [];

    public function order(string $field, string $direction): self
    {
        $this->orders[] = [$field, $direction];
        return $this;
    }
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$controller = (new ReflectionClass(PublicCardsSortValidationController::class))->newInstanceWithoutConstructor();
foreach (['created_at', 'goods', 'comments'] as $key) {
    $params = $controller->validateList(['order_key' => $key, 'page' => '2']);
    $assert($params['order_key'] === $key && $params['page'] === '2', "public sort {$key} must pass validation");
}
foreach (['id', 'updated_at', 'goods;drop'] as $key) {
    try {
        $controller->validateList(['order_key' => $key]);
        throw new RuntimeException("public sort {$key} must be rejected");
    } catch (ApiException $exception) {
        $assert($exception->getCode() === ApiException::CODE_PARAM_INVALID, "public sort {$key} must return a parameter error");
    }
}

$schema = PublicCardsQuery::requestSchemas()['list'];
$assert($schema['properties']['order_key']['enum'] === ['created_at', 'goods', 'comments'], 'SDK sort enum must match runtime whitelist');
$assert($schema['additionalProperties'] === false, 'public query schema must reject undocumented filters');

$query = new PublicCardsSortQuery();
$modelQuery = (new ReflectionClass(ModelQuery::class))->newInstanceWithoutConstructor();
$queryProperty = new ReflectionProperty(ModelQuery::class, 'query');
$queryProperty->setValue($modelQuery, $query);
$order = new ReflectionMethod(ModelQuery::class, 'order');
$order->invoke($modelQuery, 'goods', true, true);
$assert($query->orders === [['goods', 'desc'], ['id', 'desc']], 'public rank must use id as deterministic tie-breaker');

$query->orders = [];
$order->invoke($modelQuery, 'created_at', false, true);
$assert($query->orders === [['created_at', 'asc'], ['id', 'desc']], 'legacy ascending sort must retain deterministic ties');

fwrite(STDOUT, "PASS public cards sort contract\n");
