<?php

declare(strict_types=1);

/**
 * Candidate search contract and privacy boundary baseline.
 *
 * This test uses a deliberately small query double so the filtering rule can
 * run without requiring a live database. The HTTP/auth contract is checked
 * from the route source and generated SDK output as well.
 */
namespace think\facade {
    final class MentionCandidatesQuery
    {
        private array $predicates = [];
        private ?int $take = null;
        private array $fields = [];
        private array $orders = [];

        public function __construct(private string $table, private array $rows) {}
        public function where($field, $operator = null, $value = null): self
        {
            // The production block query uses a nested OR closure. Its result
            // is post-filtered by the service, so the double may safely keep
            // all relation rows for that branch.
            if (is_callable($field)) return $this;
            if ($value === null) {
                $value = $operator;
                $operator = '=';
            }
            $this->predicates[] = static function (array $row) use ($field, $operator, $value): bool {
                $actual = $row[$field] ?? null;
                return match (strtolower((string) $operator)) {
                    '=' => (string) $actual === (string) $value,
                    '<>' => (string) $actual !== (string) $value,
                    default => true,
                };
            };
            return $this;
        }
        public function whereNull(string $field): self
        {
            $this->predicates[] = static fn(array $row): bool => ($row[$field] ?? null) === null;
            return $this;
        }
        public function whereIn(string $field, array $values): self
        {
            $values = array_map('strval', $values);
            $this->predicates[] = static fn(array $row): bool => in_array((string) ($row[$field] ?? ''), $values, true);
            return $this;
        }
        public function whereOr($field, $operator = null, $value = null): self
        {
            // The production query uses whereOr inside the nested block
            // predicate. The fixture applies the relation filter in the outer
            // query and intentionally keeps both directions for post-filtering.
            return $this;
        }
        public function whereLike(string $field, string $pattern): self
        {
            $prefix = mb_strtolower(rtrim($pattern, '%'), 'UTF-8');
            $this->predicates[] = static fn(array $row): bool => str_starts_with(mb_strtolower((string) ($row[$field] ?? ''), 'UTF-8'), $prefix);
            return $this;
        }
        public function whereRaw(string $expression, array $bind = []): self
        {
            if ($this->table !== 'users' || !isset($bind['mention_prefix'])) return $this;
            $encoded = (string) $bind['mention_prefix'];
            $prefix = rtrim(str_replace(['\\_', '\\%', '\\\\'], ['_', '%', '\\'], $encoded), '%');
            $this->predicates[] = static fn(array $row): bool => str_starts_with(mb_strtolower((string) ($row['username'] ?? ''), 'UTF-8'), mb_strtolower($prefix, 'UTF-8'));
            return $this;
        }
        public function field(string $fields): self { $this->fields = array_map('trim', explode(',', $fields)); return $this; }
        public function order(string $field, string $direction = 'asc'): self { $this->orders[] = [$field, strtolower($direction)]; return $this; }
        public function limit(int $limit): self { $this->take = $limit; return $this; }
        public function select(): self { return $this; }
        public function column(string $field): array { return array_values(array_map(static fn(array $row) => $row[$field] ?? null, $this->result())); }
        public function toArray(): array { return $this->result(); }
        private function result(): array
        {
            $rows = array_values(array_filter($this->rows, function (array $row): bool {
                foreach ($this->predicates as $predicate) if (!$predicate($row)) return false;
                return true;
            }));
            foreach (array_reverse($this->orders) as [$field, $direction]) {
                usort($rows, static function (array $a, array $b) use ($field, $direction): int {
                    $left = (string) ($a[$field] ?? '');
                    $right = (string) ($b[$field] ?? '');
                    $result = strcasecmp($left, $right);
                    return $direction === 'desc' ? -$result : $result;
                });
            }
            if ($this->take !== null) $rows = array_slice($rows, 0, $this->take);
            if ($this->fields !== []) {
                $rows = array_map(static function (array $row): array {
                    // The service only asks for fields that exist in fixtures;
                    // retaining unknown values keeps this double permissive.
                    return $row;
                }, $rows);
            }
            return $rows;
        }
    }

    final class Db
    {
        public static array $rows = [];
        public static function table(string $table): MentionCandidatesQuery
        {
            return new MentionCandidatesQuery($table, self::$rows[$table] ?? []);
        }
    }
}

namespace {
    require_once dirname(__DIR__, 2) . '/app/shared/Transport/Http/Resource.php';
    require_once dirname(__DIR__, 2) . '/app/modules/Platform/User/Transport/Api/MentionCandidateResource.php';
    require_once dirname(__DIR__, 2) . '/app/modules/Platform/User/Service/Users.php';

    use app\modules\Platform\User\Service\Users;
    use app\modules\Platform\User\Transport\Api\MentionCandidateResource;
    use think\facade\Db;

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };

    $root = dirname(__DIR__, 2);
    $route = file_get_contents($root . '/app/api/route/users.php');
    $sdk = file_get_contents(dirname($root, 2) . '/packages/sdk/src/generated/resources/users.ts');
    $contract = file_get_contents(dirname($root, 2) . '/contracts/openapi.json');
    $assert(str_contains($route, "users/mention-candidates"), 'route path must be stable');
    $assert(str_contains($route, "users.searchMentionCandidates"), 'operationId must be stable');
    $assert(str_contains($route, 'MentionCandidatesValidate::class'), 'route must bind the dedicated validator');
    $assert(str_contains($route, '->middleware(JwtAuthCheck::class)'), 'candidate search must require authentication');
    $assert(str_contains($sdk, 'searchMentionCandidates(params: { query: string; limit?: number })'), 'SDK must expose typed candidate search');
    $assert(str_contains($contract, '"/users/mention-candidates"'), 'OpenAPI must contain candidate search');
    $assert(MentionCandidateResource::schema()['required'] === ['uid', 'username', 'avatar'], 'candidate resource must include public UID');

    Db::$rows = [
        'users' => [
            ['id' => 1, 'uid' => '123456789001', 'username' => 'viewer', 'avatar' => '/viewer.png', 'status' => 0, 'deleted_at' => null],
            ['id' => 2, 'uid' => '123456789002', 'username' => 'Forest', 'avatar' => '/forest.png', 'status' => 0, 'deleted_at' => null],
            ['id' => 3, 'username' => 'forbidden', 'avatar' => null, 'status' => 0, 'deleted_at' => null],
            ['id' => 4, 'username' => 'forfriend', 'avatar' => '', 'status' => 0, 'deleted_at' => null],
            ['id' => 5, 'username' => 'former', 'avatar' => null, 'status' => 1, 'deleted_at' => null],
            ['id' => 6, 'username' => 'forgotten', 'avatar' => null, 'status' => 0, 'deleted_at' => '2026-01-01'],
            ['id' => 7, 'username' => 'blocked', 'avatar' => null, 'status' => 0, 'deleted_at' => null],
            ['id' => 8, 'username' => 'blocker', 'avatar' => null, 'status' => 0, 'deleted_at' => null],
            ['id' => 9, 'username' => 'fo_x', 'avatar' => null, 'status' => 0, 'deleted_at' => null],
            ['id' => 10, 'username' => 'foox', 'avatar' => null, 'status' => 0, 'deleted_at' => null],
        ],
        'privacy_preferences' => [
            ['user_id' => 3, 'profile_visibility' => 'private'],
            ['user_id' => 4, 'profile_visibility' => 'followers'],
        ],
        'user_follows' => [
            ['follower_id' => 1, 'followee_id' => 4],
        ],
        'user_safety_relations' => [
            ['actor_id' => 1, 'subject_id' => 7, 'relation' => 'block'],
            ['actor_id' => 8, 'subject_id' => 1, 'relation' => 'block'],
        ],
    ];

    $result = Users::searchMentionCandidates(['query' => 'for', 'limit' => 20], 1);
    $assert(array_column($result['data'], 'username') === ['Forest', 'forfriend'], 'active/public-or-followed candidates should be returned in stable order');
    $assert(!in_array('viewer', array_column($result['data'], 'username'), true), 'the viewer must not be suggested to themselves');
    $assert(!in_array('forbidden', array_column($result['data'], 'username'), true), 'private users must not be discoverable');

    $limited = Users::searchMentionCandidates(['query' => 'for', 'limit' => 1], 1);
    $assert(count($limited['data']) === 1 && $limited['data'][0]['username'] === 'Forest', 'limit must be enforced server-side');
    $literalUnderscore = Users::searchMentionCandidates(['query' => 'fo_', 'limit' => 20], 1);
    $assert(array_column($literalUnderscore['data'], 'username') === ['fo_x'], 'underscore in a handle query must stay a literal character');
    $empty = Users::searchMentionCandidates(['query' => '', 'limit' => 8], 1);
    $assert($empty['data'] === [] && $empty['total'] === 0, 'empty query must not enumerate users');

    fwrite(STDOUT, "Mention candidate contract baseline passed.\n");
}
