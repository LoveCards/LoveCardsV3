<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/vendor/topthink/framework/src/helper.php';
require dirname(__DIR__, 2) . '/app/modules/Content/Cards/Service/Cards.php';

$root = dirname(__DIR__, 2);
$app = new \think\App($root);
$lang = new \think\Lang($app, ['use_cookie' => false]);
\think\Validate::maker(static function (\think\Validate $validator) use ($lang): void {
    $validator->setLang($lang);
    $validator->extend('arrayJson', static fn($value): bool => is_array($value) || is_string($value));
    $validator->extend('picturesLength', static fn($value): bool => true);
    $validator->extend('tagsLength', static fn($value): bool => true);
    $validator->extend('notBlank', static fn($value): bool => is_scalar($value) && trim((string) $value) !== '');
});

use app\api\ApiException;
use app\api\controller\BaseController;
use app\modules\Content\Cards\Transport\Api\Cards as CardsValidator;

final class CardContentValidationController extends BaseController
{
    public function check(string $scene, array $input): array
    {
        return $this->param(CardsValidator::class, CardsValidator::$all_scene[$scene], $input);
    }
}

$controller = (new ReflectionClass(CardContentValidationController::class))->newInstanceWithoutConstructor();
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$valid = static function (string $scene, string $content) use ($controller): void {
    $controller->check($scene, $scene === 'selfUpdate' ? ['id' => 1, 'content' => $content] : ['content' => $content]);
};
$invalid = static function (string $scene, string $content) use ($controller): ApiException {
    try {
        $controller->check($scene, $scene === 'selfUpdate' ? ['id' => 1, 'content' => $content] : ['content' => $content]);
    } catch (ApiException $exception) {
        return $exception;
    }
    throw new RuntimeException('expected card content validation failure');
};

$valid('selfCreate', 'x');
$valid('selfCreate', str_repeat('中', 1000));
$valid('selfCreate', str_repeat('😀', 1000));
$assert($invalid('selfCreate', str_repeat('中', 1001))->getHttpStatus() === 422, '1001 Chinese characters must return HTTP 422');
$lengthError = $invalid('selfCreate', str_repeat('😀', 1001));
$assert(($lengthError->getData()['errors']['content'][0] ?? null) === '正文最多 1000 字。', 'length error must expose stable content field message: ' . var_export($lengthError->getData(), true));
$valid('selfUpdate', str_repeat('中', 1000));
$assert($invalid('selfUpdate', str_repeat('😀', 1001))->getHttpStatus() === 422, 'update over-limit must return HTTP 422');
$controller->check('selfUpdate', ['id' => 1]); // omitted content must not block other-field updates
$controller->check('adminUpdate', ['id' => 1, 'content' => str_repeat('中', 1000)]);
try {
    $controller->check('adminUpdate', ['id' => 1, 'content' => str_repeat('中', 1001)]);
    throw new RuntimeException('expected admin update content validation failure');
} catch (ApiException $exception) {
    $assert($exception->getHttpStatus() === 422, 'admin update over-limit must return HTTP 422');
    $assert(($exception->getData()['errors']['content'][0] ?? null) === '正文最多 1000 字。', 'admin update must expose stable content field message');
}
$controller->check('adminUpdate', ['id' => 1]);

$normalize = new ReflectionMethod(\app\modules\Content\Cards\Service\Cards::class, 'normalizeCardMetadata');
$normalize->setAccessible(true);
$metadata = $normalize->invoke(null, ['data' => ['title' => 'old', 'summary' => 'old', 'kind' => 'image']]);
$assert($metadata['data'] === ['kind' => 'image'], 'new writes must not preserve data.title/data.summary');
$assert($normalize->invoke(null, ['content' => 'history']) === ['content' => 'history'], 'history reads/partial updates without data must remain untouched');

fwrite(STDOUT, "PASS Card content length and single-body contract\n");
