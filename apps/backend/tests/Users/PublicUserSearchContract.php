<?php

declare(strict_types=1);

namespace think {
    class Validate {}
}

namespace think\facade {
    final class PublicUserQuery
    {
        public array $raw = [];
        public array $binds = [];
        public array $orders = [];
        public bool $filteredBeforePagination = false;

        public function alias(string $alias): self { return $this; }
        public function where(string $field, mixed $value): self { return $this; }
        public function whereNull(string $field): self { return $this; }
        public function whereRaw(string $sql, array $binds = []): self
        {
            $this->raw[] = $sql;
            $this->binds += $binds;
            return $this;
        }
        public function field(string $fields): self { return $this; }
        public function order(string $field, string $direction): self
        {
            $this->orders[] = [$field, $direction];
            return $this;
        }
        public function orderRaw(string $sql, array $binds = []): self
        {
            $this->raw[] = $sql;
            $this->binds += $binds;
            return $this;
        }
        public function paginate(array $options): self
        {
            $sql = implode(' ', $this->raw);
            $this->filteredBeforePagination = str_contains($sql, 'privacy_preferences')
                && str_contains($sql, 'user_roles');
            return $this;
        }
        public function toArray(): array
        {
            return ['total' => 0, 'per_page' => 20, 'current_page' => 1, 'last_page' => 1, 'data' => []];
        }
    }

    final class Db
    {
        public static ?PublicUserQuery $query = null;
        public static function table(string $table): PublicUserQuery
        {
            if ($table !== 'users') throw new \RuntimeException('搜索必须查询 users');
            return self::$query = new PublicUserQuery();
        }
    }
}

namespace {
    function config(string $key): int
    {
        if ($key !== 'system.system_roles.guest') throw new \RuntimeException('意外的配置项');
        return 4;
    }

    require_once dirname(__DIR__, 2) . '/app/shared/Transport/Http/Resource.php';
    require_once dirname(__DIR__, 2) . '/app/shared/Transport/Validation/PaginationInput.php';
    require_once dirname(__DIR__, 2) . '/app/modules/Platform/User/Transport/Api/PublicUserSearchResource.php';
    require_once dirname(__DIR__, 2) . '/app/modules/Platform/User/Transport/Api/PublicUsersSearch.php';
    require_once dirname(__DIR__, 2) . '/app/modules/Platform/User/Service/PublicUserSearch.php';

    use app\modules\Platform\User\Service\PublicUserSearch;
    use app\modules\Platform\User\Transport\Api\PublicUserSearchResource;
    use app\modules\Platform\User\Transport\Api\PublicUsersSearch;
    use think\facade\Db;

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new \RuntimeException($message);
    };

    $empty = PublicUserSearch::search(['search_value' => '  ']);
    $assert($empty['data'] === [] && Db::$query === null, '空关键词不得枚举用户');

    PublicUserSearch::search(['search_value' => 'fo_%', 'list_rows' => 20], 0);
    $anonymous = Db::$query;
    $anonymousSql = implode(' ', $anonymous->raw);
    $assert($anonymous->filteredBeforePagination, '游客可见性和 guest 过滤必须先于分页');
    $assert(str_contains($anonymousSql, "profile_visibility <> 'public'"), '游客只能查公开主页');
    $assert(str_contains($anonymousSql, 'user_roles'), '游客账号不得进入公开搜索');
    $assert($anonymous->binds['public_user_prefix'] === 'fo\\_\\%%', 'LIKE 通配符必须按字面值处理');
    $assert($anonymous->orders === [['u.username', 'asc'], ['u.id', 'asc']], '分页排序必须稳定');

    PublicUserSearch::search(['search_value' => '123456789012'], 9);
    $signedIn = Db::$query;
    $signedInSql = implode(' ', $signedIn->raw);
    $assert($signedIn->filteredBeforePagination, '已登录搜索的可见性过滤必须先于分页');
    $assert(str_contains($signedInSql, 'user_safety_relations') && str_contains($signedInSql, 'search_block.actor_id = 9') && str_contains($signedInSql, 'search_block.subject_id = 9'), '双向屏蔽都必须生效');
    $assert(str_contains($signedInSql, 'user_follows') && str_contains($signedInSql, 'u.id = 9'), '关注可见与本人可见必须生效');
    $assert($signedIn->binds['public_user_uid'] === '123456789012', '12 位 UID 必须支持精确搜索');
    $assert($signedIn->binds['user_rank_uid'] === '123456789012', 'UID 精确结果必须优先排序');
    $assert(str_contains($signedInSql, 'WHEN u.username = :user_rank_name'), '用户名完整匹配必须先于前缀匹配');

    $resource = PublicUserSearchResource::make([
        'uid' => '123456789012', 'username' => 'user', 'avatar' => '', 'bio' => '介绍',
        'email' => 'private@example.test', 'phone' => '123', 'password' => 'secret',
    ]);
    $assert(array_keys($resource) === ['uid', 'username', 'avatar', 'bio'], '响应不得泄露私密账号字段');
    $assert($resource['avatar'] === null, '空头像应归一为空值');
    $assert(PublicUsersSearch::requestSchemas()['search']['required'] === ['search_value'], '空查询必须在请求层被拒绝');

    fwrite(STDOUT, "Public user search contract baseline passed.\n");
}
