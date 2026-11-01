<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/vendor/autoload.php';

use app\modules\Platform\Library\Infrastructure\ThinkOrmLibraryRepository;
use think\Container;

final class CursorRows
{
    public function __construct(private array $rows) {}
    public function toArray(): array { return $this->rows; }
}
final class CursorQuery
{
    private array $predicates = [];
    private int $limit = PHP_INT_MAX;
    public function __construct(private array $rows) {}
    public function where(string $key, mixed $operator, mixed $value = null): self {
        $key = str_replace('cards.', '', $key);
        if ($value === null) { $value = $operator; $operator = '='; }
        $this->predicates[] = static fn(array $row): bool => $operator === '<' ? $row[$key] < $value : $row[$key] == $value;
        return $this;
    }
    public function whereNull(string $key): self {
        $key = str_replace('cards.', '', $key);
        $this->predicates[] = static fn(array $row): bool => ($row[$key] ?? null) === null;
        return $this;
    }
    public function whereIn(string $key, array $values): self {
        $key = str_replace('cards.', '', $key);
        $this->predicates[] = static fn(array $row): bool => in_array($row[$key], $values, true);
        return $this;
    }
    public function whereRaw(string $sql): self { return $this; }
    public function field(string $fields): self { return $this; }
    public function order(string $field, string $direction): self { usort($this->rows, static fn($a, $b) => $b['id'] <=> $a['id']); return $this; }
    public function limit(int $limit): self { $this->limit = $limit; return $this; }
    private function rows(): array {
        return array_slice(array_values(array_filter($this->rows, function (array $row): bool {
            foreach ($this->predicates as $predicate) if (!$predicate($row)) return false;
            return true;
        })), 0, $this->limit);
    }
    public function select(): CursorRows { return new CursorRows($this->rows()); }
    public function column(string $field): array { return array_column($this->rows(), str_replace('cards.', '', $field)); }
}
$db = new class {
    public array $items = [];
    public array $cards = [];
    public int $cardQueries = 0;
    public function table(string $table): CursorQuery {
        if ($table === 'cards') { $this->cardQueries++; return new CursorQuery($this->cards); }
        return new CursorQuery($this->items);
    }
};
// 这里仅测试仓库在收到可访问 ID 集合后的分页，不把查询替身当成权限矩阵证明。
foreach (range(1, 120) as $id) $db->items[] = ['id' => $id, 'user_id' => 1, 'resource_type' => 'card', 'resource_id' => (string) $id];
foreach ([1, 5, 20, 40, 45] as $id) $db->cards[] = ['id' => $id, 'status' => 0];
$db->items[] = ['id' => 1000, 'user_id' => 2, 'resource_type' => 'card', 'resource_id' => '45'];
Container::getInstance()->instance('think\DbManager', $db);
$repo = new ThinkOrmLibraryRepository();
foreach (['listSaved', 'listHistory'] as $method) {
    $cursor = null;
    $seen = [];
    do {
        $page = $repo->$method(1, $cursor, 2);
        $seen = array_merge($seen, array_column($page['items'], 'id'));
        $cursor = $page['next_cursor'];
    } while ($page['has_more']);
    if ($seen !== [45, 40, 20, 5, 1]) throw new RuntimeException('filtered pagination skipped, duplicated or mixed users');
}
$db->cards = [];
$page = $repo->listSaved(1, null, 2);
if ($page !== ['items' => [], 'next_cursor' => null, 'has_more' => false]) throw new RuntimeException('all hidden rows must return a terminal empty page');
echo "PASS library cursor scans hidden gaps without lost items\n";
