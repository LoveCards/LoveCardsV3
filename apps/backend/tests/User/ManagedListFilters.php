<?php
declare(strict_types=1);

namespace app\shared\Infrastructure\Persistence {
    final class ModelQuery {
        public static array $params = [];
        public static array $scope = [];
        public static function make($model): self { return new self(); }
        public function trashScope($mode, $allowed): self { self::$scope = [$mode, $allowed]; return $this; }
        public function getPaginate(array $params): object {
            self::$params = $params;
            return new class { public function toArray(): array { return ['data' => [], 'total' => 0]; } };
        }
    }
}

namespace {
    require dirname(__DIR__, 2) . '/app/modules/Platform/User/Service/Users.php';
    use app\modules\Platform\User\Service\Users;
    use app\shared\Infrastructure\Persistence\ModelQuery;
    function check(bool $condition, string $message): void { if (!$condition) throw new \RuntimeException($message); }
    Users::listAll(['status' => '0', 'role_id' => '7', 'page' => 3, 'search_value' => '林', 'order_key' => 'number'], ['users.read.all']);
    $params = ModelQuery::$params;
    check($params['where'][0] === ['status', '=', 0], 'normal status must not be treated as an empty filter');
    check($params['where'][1][0] === 'id' && $params['where'][1][1] === 'in', 'role filter must constrain the user query');
    $subquery = new class {
        public array $calls = [];
        public function table($table): self { $this->calls[] = ['table', $table]; return $this; }
        public function where($key, $value): self { $this->calls[] = ['where', $key, $value]; return $this; }
        public function field($field): self { $this->calls[] = ['field', $field]; return $this; }
    };
    $params['where'][1][2]($subquery);
    check($subquery->calls === [['table', 'user_roles'], ['where', 'role_id', 7], ['field', 'user_id']], 'role membership must use the relation subquery');
    check($params['page'] === 3 && $params['search_value'] === '林' && $params['order_key'] === 'number', 'filters must preserve pagination, search and sorting');
    check(ModelQuery::$scope === [0, true], 'existing trash authorization must remain intact');
    Users::listAll([], []);
    check(ModelQuery::$params['where'] === [], 'cleared filters must leave the query unrestricted');
    check(ModelQuery::$scope === [0, false], 'filters must not grant trash access');
    fwrite(STDOUT, "PASS managed user list filters\n");
}
