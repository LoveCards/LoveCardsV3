<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use app\api\ApiException;
use app\modules\Platform\Auth\Public\MediaGrants;
use think\Container;

$cache = new class {
    public array $value = [];
    public string $key = '';
    public function get(string $key, mixed $default = null): mixed
    {
        $this->key = $key;
        return $this->value ?: $default;
    }
};
Container::getInstance()->instance('cache', $cache);
$grants = (new ReflectionClass(MediaGrants::class))->newInstanceWithoutConstructor();
$token = str_repeat('a', 64);
$cache->value = ['user_id' => 7, 'jti_hash' => str_repeat('b', 64), 'card_id' => 12, 'expires_at' => time() - 1];

try {
    $grants->viewer($token, 12);
    throw new RuntimeException('expired grant was accepted');
} catch (ApiException $exception) {
    if ($exception->getHttpStatus() !== 404) throw $exception;
}
if ($cache->key !== 'media_grant:' . hash('sha256', $token)) {
    throw new RuntimeException('grant lookup must use a one-way token hash');
}

$cache->value = ['user_id' => 7, 'jti_hash' => str_repeat('b', 64), 'card_id' => 13, 'expires_at' => time() + 60];
try {
    $grants->viewer($token, 12);
    throw new RuntimeException('grant for another card was accepted');
} catch (ApiException $exception) {
    if ($exception->getHttpStatus() !== 404) throw $exception;
}

try {
    $grants->viewer('not-a-grant', 12);
    throw new RuntimeException('malformed grant was accepted');
} catch (ApiException $exception) {
    if ($exception->getHttpStatus() !== 404) throw $exception;
}

echo "PASS media grants expire and remain bound to one card\n";
