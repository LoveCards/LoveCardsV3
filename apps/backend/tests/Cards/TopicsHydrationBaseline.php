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
    }

    final class Db
    {
        public static ?TopicQuery $lastQuery = null;
        public static function table(string $table): TopicQuery
        {
            if ($table !== 'tags_map') throw new \RuntimeException("Unexpected table: {$table}");
            return self::$lastQuery = new TopicQuery();
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

    fwrite(STDOUT, "PASS card topic hydration returns id/name resources for detail cards\n");
}
