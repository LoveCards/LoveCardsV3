<?php
declare(strict_types=1);

namespace think\facade {
    final class TopicRows
    {
        public function __construct(private array $rows) {}
        public function toArray(): array { return $this->rows; }
    }

    final class TopicQuery
    {
        public array $calls = [];
        public ?array $findRow = null;
        public function alias(...$args): self { $this->calls[] = ['alias', ...$args]; return $this; }
        public function join(...$args): self { $this->calls[] = ['join', ...$args]; return $this; }
        public function where(...$args): self { $this->calls[] = ['where', ...$args]; return $this; }
        public function whereIn(...$args): self { $this->calls[] = ['whereIn', ...$args]; return $this; }
        public function whereNull(...$args): self { $this->calls[] = ['whereNull', ...$args]; return $this; }
        public function field(...$args): self { $this->calls[] = ['field', ...$args]; return $this; }
        public function order(...$args): self { $this->calls[] = ['order', ...$args]; return $this; }
        public function select(): TopicRows
        {
            return new TopicRows([
                ['pid' => 46, 'id' => 13, 'name' => '牛的'],
            ]);
        }
        public function find(): ?array
        {
            return $this->findRow;
        }
    }

    final class Db
    {
        public static ?TopicQuery $lastQuery = null;
        public static ?array $nextFindRow = null;
        public static function table(string $table): TopicQuery
        {
            if ($table !== 'tags_map') throw new \RuntimeException("Unexpected table: {$table}");
            $query = new TopicQuery();
            $query->findRow = self::$nextFindRow;
            return self::$lastQuery = $query;
        }
    }
}

namespace app\modules\Content\Infrastructure\Persistence\Model {
    final class TagsMap {}
}

namespace {
    $root = dirname(__DIR__, 2);
    require $root . '/app/shared/Transport/Http/Resource.php';
    require $root . '/app/modules/Content/Cards/Transport/Api/CardResource.php';
    require $root . '/app/modules/Content/Cards/Service/Cards.php';

    use app\modules\Content\Cards\Service\Cards;
    use app\modules\Content\Cards\Transport\Api\CardResource;
    use think\facade\Db;

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };

    $hydrate = new ReflectionMethod(Cards::class, 'hydrateCard');
    $hydrate->setAccessible(true);
    $card = $hydrate->invoke(null, [
        'id' => 46,
        'tags' => [13],
        'pictures' => [],
    ], false, [], [], null);
    $resource = CardResource::make($card);

    $assert($resource['topics'] === [['id' => 13, 'name' => '牛的']], 'card 46 must hydrate tag 13 into its topic resource');
    $assert(in_array(['where', 'tm.aid', 1], Db::$lastQuery?->calls ?? [], true), 'topic hydration must scope mapping rows to the active application');
    $assert(in_array(['where', 't.aid', 1], Db::$lastQuery?->calls ?? [], true), 'topic hydration must scope tags to the active application');
    $assert(in_array(['where', 't.status', 0], Db::$lastQuery?->calls ?? [], true), 'topic hydration must include active tags only');
    $assert(in_array(['where', 'tm.status', 0], Db::$lastQuery?->calls ?? [], true), 'topic hydration must include active mappings only');
    $assert(in_array(['whereNull', 'tm.deleted_at'], Db::$lastQuery?->calls ?? [], true), 'topic hydration must exclude deleted mappings');
    $assert(in_array(['whereNull', 't.deleted_at'], Db::$lastQuery?->calls ?? [], true), 'topic hydration must exclude deleted tags');

    // T-edit: 编辑视图（editable）必须把待审核标签（status=3）一并回显，保证"先审后发"下保存编辑不丢关联。
    $editableCard = $hydrate->invoke(null, [
        'id' => 46,
        'tags' => [13],
        'pictures' => [],
    ], false, [], [], null, true);
    $editableResource = CardResource::make($editableCard);
    $assert($editableResource['topics'] === [['id' => 13, 'name' => '牛的']], 'editable card must keep its hydrated topics');
    $assert(in_array(['whereIn', 't.status', [0, 3]], Db::$lastQuery?->calls ?? [], true), 'editable topic hydration must include pending tags');
    $assert(!in_array(['where', 't.status', 0], Db::$lastQuery?->calls ?? [], true), 'editable topic hydration must not hard-filter to published status');

    // T-idempotent: 编辑保存回传同名待审标签时，按卡片既有映射幂等重挂，不进入创建闸（避免拒绝或重复行）。
    $resolve = new ReflectionMethod(Cards::class, 'resolveTagIds');
    $resolve->setAccessible(true);
    Db::$nextFindRow = ['tag_id' => 13];
    $resolved = $resolve->invoke(null, ['新话题'], 7, null, 46);
    $assert($resolved === [13], 'editing a card with its own pending tag must re-attach the mapped pending tag id');
    $assert(in_array(['where', 'tm.pid', 46], Db::$lastQuery?->calls ?? [], true), 'pending re-attachment must scope to the edited card');
    $assert(in_array(['where', 't.status', 3], Db::$lastQuery?->calls ?? [], true), 'pending re-attachment must match pending tags only');
    $assert(in_array(['where', 't.name', '新话题'], Db::$lastQuery?->calls ?? [], true), 'pending re-attachment must match the submitted tag name');

    // T-no-pid: 新建卡片（无 pid）不做映射复用，名称解析必须走创建入口（此替身下 Tags 门面未加载，触发即暴露）。
    try {
        $resolve->invoke(null, ['新话题'], 7, null, 0);
        $assert(false, 'creation without a card context must route through the tag creation entry');
    } catch (Throwable $error) {
        // 期望：进入 TagsFacade::ensureTag（替身环境未加载该类）→ 说明未走映射复用分支。
        $assert(str_contains($error->getMessage(), 'Tags') || $error instanceof \Error, 'creation path must not short-circuit through card mappings');
    }

    fwrite(STDOUT, "PASS card topic hydration returns id/name resources for detail cards\n");
}
