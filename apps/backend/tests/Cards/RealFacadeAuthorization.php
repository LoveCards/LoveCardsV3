<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use app\api\ApiException;
use app\modules\Content\Cards\Service\CardVisibilityPolicy;
use think\Container;
use think\facade\Db;

// 保留真实 vendor Facade，仅替换它分发的查询对象，复现动态方法入口。
$query = new class {
    public int $reads = 0;
    public ?array $row = null;
    public bool $fail = false;
    public array $calls = [];
    public function table(string $table): self { $this->calls[] = ['table', $table]; return $this; }
    public function where(...$args): self { $this->calls[] = ['where', ...$args]; return $this; }
    public function whereNull(...$args): self { $this->calls[] = ['whereNull', ...$args]; return $this; }
    public function whereRaw(...$args): self { $this->calls[] = ['whereRaw', ...$args]; return $this; }
    public function find(): ?array {
        $this->reads++;
        if ($this->fail) throw new RuntimeException('query failed');
        return $this->row;
    }
};
Container::getInstance()->instance('think\DbManager', $query);
if (method_exists(Db::class, 'table')) throw new RuntimeException('test requires the real dynamic facade');
try {
    CardVisibilityPolicy::assertReadable(2147483647, 42);
    throw new RuntimeException('missing card was allowed');
} catch (ApiException $error) {
    if ($error->getHttpStatus() !== 404) throw $error;
}
if ($query->reads !== 1) throw new RuntimeException('authorization must execute the query');
$query->row = ['id' => 7, 'status' => 0, 'visibility' => 'private', 'user_id' => 42];
if (CardVisibilityPolicy::assertReadable(7, 42) !== $query->row) throw new RuntimeException('real row must be returned as an array');
$query->fail = true;
try {
    CardVisibilityPolicy::assertReadable(7, 42);
    throw new LogicException('database failure was allowed');
} catch (RuntimeException $error) {
    if ($error->getMessage() !== 'query failed') throw $error;
}
echo "PASS real Facade authorization executes and fails closed\n";
