<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/vendor/topthink/framework/src/helper.php';

use app\api\ApiException;
use app\api\controller\BaseController;
use app\modules\Operations\Administration\Transport\Api\AdminAuditQueryValidate;

$validationApp = new \think\App($root);
$validationLang = new \think\Lang($validationApp, ['use_cookie' => false]);
\think\Validate::maker(static function (\think\Validate $validator) use ($validationLang): void {
    $validator->setLang($validationLang);
});

final class AuditQueryValidationController extends BaseController
{
    public function validateInput(array $input): array
    {
        return $this->param(AdminAuditQueryValidate::class, AdminAuditQueryValidate::$all_scene['list'], $input);
    }
}

$controller = (new ReflectionClass(AuditQueryValidationController::class))->newInstanceWithoutConstructor();
$valid = $controller->validateInput([
    'action' => 'auth.session.revoke_all', 'actor' => 'system', 'resource_type' => 'user',
    'from' => '2026-11-08 09:00:00', 'to' => '2026-11-08 10:00:00',
]);
if (($valid['action'] ?? '') !== 'auth.session.revoke_all' || ($valid['actor'] ?? '') !== 'system') {
    throw new RuntimeException('valid audit filters must be retained');
}
foreach ([
    ['action' => 'auth.recovery%'],
    ['actor' => '0'],
    ['resource_type' => 'user;DROP'],
    ['from' => '2026-02-30 09:00:00'],
    ['to' => '2026-11-08T10:00:00Z'],
] as $input) {
    try {
        $controller->validateInput($input);
        throw new RuntimeException('invalid audit filter was accepted: ' . json_encode($input));
    } catch (ApiException $error) {
        if ($error->getHttpStatus() !== 422) throw new RuntimeException('invalid audit filter must return 422');
    }
}

fwrite(STDOUT, "PASS audit query validation\n");
