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
        public function update(array $data): int { $this->calls[] = ['update', $data]; return 1; }
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
    final class TagsMap
    {
        public static array $created = [];
        public static function create(array $item): void { self::$created[] = $item; }
    }
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
    $assert(!in_array(['where', 'tm.status', 0], Db::$lastQuery?->calls ?? [], true), 'topic hydration must not query the retired mapping status');
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

    // T-rebind: 编辑保存重挂标签时，旧映射必须被显式软删（软删模型的查询级 delete() 实测为空操作），
    // 否则每次保存都会叠加映射行，卡片话题与公开详情出现重复标签。
    Cards::updateCardTags(['id' => 46, 'tags' => [13]], false);
    $rebind = Db::$lastQuery;
    $assert($rebind !== null, 'tag re-binding must query the mapping table');
    $updateCall = null;
    foreach ($rebind->calls as $call) {
        if ($call[0] === 'update') { $updateCall = $call; break; }
    }
    $assert(is_array($updateCall) && array_key_exists('deleted_at', $updateCall[1]), 'tag re-binding must soft-delete existing mappings explicitly');
    $assert(in_array(['where', 'pid', 46], $rebind->calls, true), 'tag re-binding must scope the soft-delete to the edited card');
    $assert(in_array(['whereNull', 'deleted_at'], $rebind->calls, true), 'tag re-binding must only soft-delete live mappings');
    $assert(\app\modules\Content\Infrastructure\Persistence\Model\TagsMap::$created === [['aid' => 1, 'pid' => 46, 'tag_id' => 13]], 'tag re-binding must insert the new mapping row once');

    // T-create: 新建卡片绑定标签不做重删（此时没有任何旧映射）。
    \app\modules\Content\Infrastructure\Persistence\Model\TagsMap::$created = [];
    Db::$lastQuery = null;
    Cards::updateCardTags(['id' => 47, 'tags' => [13]], true);
    $createCalls = array_map(static fn (array $call): string => $call[0], Db::$lastQuery?->calls ?? []);
    $assert(!in_array('update', $createCalls, true), 'card creation must not issue a re-binding soft-delete');

    // T-clear: 显式空 tags（校验层 toNull 后到达的语义）必须软删全部既有映射且不插入新行——清空话题。
    \app\modules\Content\Infrastructure\Persistence\Model\TagsMap::$created = [];
    Db::$lastQuery = null;
    Cards::updateCardTags(['id' => 48, 'tags' => '[]'], false);
    $clearUpdate = null;
    foreach (Db::$lastQuery?->calls ?? [] as $call) {
        if ($call[0] === 'update') { $clearUpdate = $call; break; }
    }
    $assert(is_array($clearUpdate) && array_key_exists('deleted_at', $clearUpdate[1]), 'clearing all tags must soft-delete existing mappings');
    $assert(\app\modules\Content\Infrastructure\Persistence\Model\TagsMap::$created === [], 'clearing all tags must not insert mapping rows');

    fwrite(STDOUT, "PASS card topic hydration returns id/name resources for detail cards\n");
}
