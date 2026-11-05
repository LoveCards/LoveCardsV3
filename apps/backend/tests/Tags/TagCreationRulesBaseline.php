<?php
declare(strict_types=1);

// ============================================================
// LoveCardsV3 — Tags 模块「标签创建规则」行为基线测试
//
// 范围：锁定 tags.allow_user_create / tags.review_new_tags 两开关在
//       ensureTag（隐式+显式共用入口）上的裁决：默认放行、关闭拒建、
//       先审后发落待审、同名待审去重、管理员豁免、公开投影默认值。
// 运行：@php tests/Tags/TagCreationRulesBaseline.php
// 架构：standalone PHP 脚本（无 PHPUnit / 无框架引导）
// 模式：namespace 替身（ConfigService/ApiException/TagsModel）+ require 生产代码
// ============================================================

// ════════════════════════════════════════════════════════════
// 1. ApiException 替身：仅需 badRequest 工厂与消息可读
// ════════════════════════════════════════════════════════════
namespace app\api
{
    class ApiException extends \Exception
    {
        public static function badRequest(string $message): self
        {
            return new self($message);
        }
    }
}

// ════════════════════════════════════════════════════════════
// 2. Config 门面替身（Public 层，与生产 import 保持一致）
// ════════════════════════════════════════════════════════════
namespace app\modules\Platform\Config\Public
{
    class Config
    {
        /** @var array<string, mixed> */
        public static array $rows = [];

        public static function get(string $key, $default = null)
        {
            return array_key_exists($key, self::$rows) ? self::$rows[$key] : $default;
        }

        public static function reset(): void
        {
            self::$rows = [];
        }
    }
}

// ════════════════════════════════════════════════════════════
// 3. Tags 模型替身：内存行 + 两段式 where 链
// ════════════════════════════════════════════════════════════
namespace app\modules\Content\Infrastructure\Persistence\Model
{
    class TagsRow
    {
        public function __construct(public int $id, public array $attrs)
        {
        }

        public function toArray(): array
        {
            return ['id' => $this->id] + $this->attrs;
        }

        public function isEmpty(): bool
        {
            return false;
        }
    }

    class TagsEmptyResult
    {
        public function isEmpty(): bool { return true; }
        public function toArray(): array { return []; }
        public $id = 0;
    }

    class TagsFakeQuery
    {
        /** @var array<int, array<string, mixed>> */
        private array $conditions = [];

        public function where(string $column, $value): self
        {
            $this->conditions[] = [$column, $value];
            return $this;
        }

        public function find()
        {
            foreach (Tags::$rows as $id => $row) {
                $matched = true;
                foreach ($this->conditions as [$column, $value]) {
                    if ((int) ($row[$column] ?? -1) !== (int) $value && ($row[$column] ?? null) !== $value) {
                        $matched = false;
                        break;
                    }
                }
                if ($matched) {
                    return new TagsRow($id, $row);
                }
            }
            return null;
        }

        public function findOrEmpty()
        {
            $found = $this->find();
            if ($found !== null) {
                return $found;
            }
            return new TagsEmptyResult();
        }
    }

    class Tags
    {
        /** @var array<int, array<string, mixed>> 自增 id → 属性 */
        public static array $rows = [];
        public static int $nextId = 1;

        public static function where(string $column, $value): TagsFakeQuery
        {
            return (new TagsFakeQuery())->where($column, $value);
        }

        public static function create(array $attrs)
        {
            $id = self::$nextId++;
            $stored = $attrs;
            $stored['id'] = $id;
            self::$rows[$id] = $stored;
            return new TagsRow($id, $attrs);
        }

        public static function reset(): void
        {
            self::$rows = [];
            self::$nextId = 1;
        }
    }
}

// ════════════════════════════════════════════════════════════
// 4. 载入生产代码（真实 GrantSet/Scope + 被测 Service）
// ════════════════════════════════════════════════════════════
namespace
{
    use app\api\ApiException;
    use app\modules\Content\Infrastructure\Persistence\Model\Tags as TagsModel;
    use app\modules\Content\Tags\Service\Tags as TagsService;
    use app\modules\Platform\Config\Public\Config as ConfigService;
    use app\shared\Authorization\Grant;
    use app\shared\Authorization\GrantSet;

    require __DIR__ . '/../../app/shared/Authorization/Scope.php';
    require __DIR__ . '/../../app/shared/Authorization/Grant.php';
    require __DIR__ . '/../../app/shared/Authorization/GrantSet.php';
    require __DIR__ . '/../../app/modules/Content/Tags/Service/Tags.php';

    $failures = 0;
    $assertions = 0;

    function check(bool $condition, string $label): void
    {
        global $failures, $assertions;
        $assertions++;
        if ($condition) {
            echo "  ok {$label}\n";
        } else {
            $failures++;
            echo "  FAIL {$label}\n";
        }
    }

    function resetWorld(array $configRows = []): void
    {
        TagsModel::reset();
        ConfigService::reset();
        ConfigService::$rows = $configRows;
    }

    function adminGrants(): GrantSet
    {
        return new GrantSet([new Grant('admin.access', 'global'), new Grant('tags.create', 'global')]);
    }

    function userGrants(): GrantSet
    {
        return new GrantSet([new Grant('tags.create', 'global')]);
    }

    echo "== TagCreationRulesBaseline ==\n";

    // ---------------------------------------------------------------
    // T1: default policy keeps current behavior — new tags are created published
    // ---------------------------------------------------------------
    resetWorld();
    $id = TagsService::ensureTag('周末记录', 7, userGrants());
    check($id > 0 && (int) TagsModel::$rows[$id]['status'] === 0, 'T1 allow-create default creates a published tag');
    check((int) TagsModel::$rows[$id]['user_id'] === 7 && TagsModel::$rows[$id]['name'] === '周末记录', 'T1 creator and name recorded');

    // ---------------------------------------------------------------
    // T2: existing published tags are reused regardless of the switch
    // ---------------------------------------------------------------
    resetWorld(['tags.allow_user_create' => false]);
    TagsModel::$rows[101] = ['aid' => 1, 'status' => 0, 'name' => '城市漫游', 'user_id' => 3];
    $id = TagsService::ensureTag('#城市漫游', 7, userGrants());
    check($id === 101 && count(TagsModel::$rows) === 1, 'T2 existing tag reused even when creation disabled');

    // ---------------------------------------------------------------
    // T3: allow_user_create=false rejects unknown names for regular users
    // ---------------------------------------------------------------
    resetWorld(['tags.allow_user_create' => false]);
    $rejected = null;
    try {
        TagsService::ensureTag('新造话题', 7, userGrants());
    } catch (ApiException $exception) {
        $rejected = $exception->getMessage();
    }
    check($rejected !== null && str_contains($rejected, '暂不支持创建新标签'), 'T3 creation disabled rejects unknown tag with actionable message');
    check(TagsModel::$rows === [], 'T3 no row written after rejection');

    // ---------------------------------------------------------------
    // T4: review_new_tags=true parks new tags in status 3 (待审核)
    // ---------------------------------------------------------------
    resetWorld(['tags.review_new_tags' => true]);
    $pendingId = TagsService::ensureTag('新话题', 7, userGrants());
    check((int) TagsModel::$rows[$pendingId]['status'] === 3, 'T4 review switch parks new tag as pending');
    $again = TagsService::ensureTag('新话题', 8, userGrants());
    check($again === $pendingId && count(TagsModel::$rows) === 1, 'T4 same pending name deduplicated across users');

    // ---------------------------------------------------------------
    // T5: admin.access holders bypass both switches
    // ---------------------------------------------------------------
    resetWorld(['tags.allow_user_create' => false]);
    $id = TagsService::ensureTag('管理员新标签', 1, adminGrants());
    check($id > 0 && (int) TagsModel::$rows[$id]['status'] === 0, 'T5 admin bypasses creation lock');
    resetWorld(['tags.allow_user_create' => false, 'tags.review_new_tags' => true]);
    $id = TagsService::ensureTag('管理员新标签2', 1, adminGrants());
    check($id > 0 && (int) TagsModel::$rows[$id]['status'] === 0, 'T5 admin bypasses review requirement');

    // ---------------------------------------------------------------
    // T6: creationPolicy projection keeps permissive defaults when keys unset
    // ---------------------------------------------------------------
    resetWorld();
    $policy = TagsService::creationPolicy();
    check($policy['allow_user_create'] === true && $policy['review_new_tags'] === false, 'T6 policy defaults match current shipped behavior');
    resetWorld(['tags.allow_user_create' => false, 'tags.review_new_tags' => true]);
    $policy = TagsService::creationPolicy();
    check($policy['allow_user_create'] === false && $policy['review_new_tags'] === true, 'T6 policy mirrors stored rows');

    // ---------------------------------------------------------------
    // T7: blank and '#' only names are rejected; createTag returns row array
    // ---------------------------------------------------------------
    resetWorld();
    $rejected = null;
    try {
        TagsService::ensureTag('  #  ', 7, userGrants());
    } catch (ApiException $exception) {
        $rejected = $exception->getMessage();
    }
    check($rejected !== null && str_contains($rejected, '标签名称不能为空'), 'T7 blank name rejected');
    $row = TagsService::createTag(['name' => '#显式创建', 'user_id' => 7], userGrants());
    check((int) ($row['id'] ?? 0) > 0 && $row['name'] === '显式创建', 'T7 explicit createTag returns persisted row');

    echo $failures === 0
        ? "PASS {$assertions} assertions\n"
        : "FAIL {$failures}/{$assertions} assertions\n";
    exit($failures === 0 ? 0 : 1);
}
