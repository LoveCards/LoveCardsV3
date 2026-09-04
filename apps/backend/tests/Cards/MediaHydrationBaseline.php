<?php
declare(strict_types=1);

namespace think\facade {
    final class ResultRows
    {
        public function __construct(private array $rows) {}
        public function toArray(): array { return $this->rows; }
    }

    final class ReferenceQuery
    {
        public array $calls = [];

        public function where(...$args): self { $this->calls[] = ['where', ...$args]; return $this; }
        public function whereIn(...$args): self { $this->calls[] = ['whereIn', ...$args]; return $this; }
        public function field(...$args): self { $this->calls[] = ['field', ...$args]; return $this; }
        public function order(...$args): self { $this->calls[] = ['order', ...$args]; return $this; }
        public function select(): ResultRows { return new ResultRows(Db::$references); }
    }

    final class Db
    {
        public static int $referenceQueries = 0;
        public static array $references = [];
        public static ?ReferenceQuery $lastReferenceQuery = null;

        public static function table(string $table): ReferenceQuery
        {
            if ($table !== 'file_references') throw new \RuntimeException("Unexpected table: {$table}");
            self::$referenceQueries++;
            return self::$lastReferenceQuery = new ReferenceQuery();
        }
    }
}

namespace app\modules\Platform\Files\Infrastructure\Persistence\Model {
    use think\facade\ResultRows;

    final class MediaQuery
    {
        public array $calls = [];

        public function where(...$args): self { $this->calls[] = ['where', ...$args]; return $this; }
        public function whereIn(...$args): self { $this->calls[] = ['whereIn', ...$args]; return $this; }
        public function whereNull(...$args): self { $this->calls[] = ['whereNull', ...$args]; return $this; }
        public function order(...$args): self { $this->calls[] = ['order', ...$args]; return $this; }
        public function whereOr(callable $callback): self { $this->calls[] = ['whereOr']; $callback($this); return $this; }
        public function securePublic(): self { Files::$securePublicCalls++; return $this; }
        public function select(): ResultRows { return new ResultRows(Files::$rows); }
    }

    final class Files
    {
        public const SCENE_CARD = 1;
        public const STATUS_NORMAL = 0;
        public const UPLOAD_COMPLETED = 1;

        public static int $queries = 0;
        public static int $securePublicCalls = 0;
        public static array $rows = [];
        public static ?MediaQuery $lastQuery = null;

        public static function where(callable $callback): MediaQuery
        {
            self::$queries++;
            $query = self::$lastQuery = new MediaQuery();
            $callback($query);
            return $query;
        }
    }
}

namespace {
    use app\modules\Content\Cards\Service\Cards;
    use app\modules\Platform\Files\Application\ManageFileReferences;
    use app\modules\Platform\Files\Contract\FileRepository;
    use app\modules\Platform\Files\Infrastructure\ThinkDbFileReferenceRepository;
    use app\modules\Platform\Files\Infrastructure\ThinkOrmFileRepository;
    use app\modules\Platform\Files\Infrastructure\Persistence\Model\Files;
    use app\shared\Transaction\TransactionManager;
    use think\facade\Db;

    $root = dirname(__DIR__, 2);
    require $root . '/app/modules/Platform/Files/Domain/FileConstants.php';
    require $root . '/app/modules/Platform/Files/Contract/FileReferenceRepository.php';
    require $root . '/app/modules/Platform/Files/Contract/FileRepository.php';
    require $root . '/app/shared/Transaction/TransactionManager.php';
    require $root . '/app/modules/Platform/Files/Application/ManageFileReferences.php';
    require $root . '/app/modules/Platform/Files/Infrastructure/ThinkDbFileReferenceRepository.php';
    require $root . '/app/modules/Platform/Files/Infrastructure/ThinkOrmFileRepository.php';
    require $root . '/app/modules/Platform/Files/Public/Files.php';

    final class PassthroughTransaction implements TransactionManager
    {
        public function run(callable $callback): mixed { return $callback(); }
    }

    function app(string $abstract): object
    {
        static $services = [];
        if (isset($services[$abstract])) return $services[$abstract];
        return $services[$abstract] = match ($abstract) {
            FileRepository::class => new ThinkOrmFileRepository(),
            ManageFileReferences::class => new ManageFileReferences(
                new ThinkDbFileReferenceRepository(),
                new PassthroughTransaction()
            ),
            default => throw new RuntimeException("Unexpected service: {$abstract}"),
        };
    }

    require $root . '/app/modules/Content/Cards/Service/Cards.php';

    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };

    $file = static fn(int $id, int $cardId, string $hash): array => [
        'id' => $id,
        'ref_type' => 'card',
        'ref_id' => $cardId,
        'scene' => Files::SCENE_CARD,
        'status' => Files::STATUS_NORMAL,
        'upload_status' => Files::UPLOAD_COMPLETED,
        'purge_status' => null,
        'is_public' => 1,
        'hash' => $hash,
        'file_url' => "/upload/{$id}.jpg",
        'mime_type' => 'image/jpeg',
        'file_size' => 128,
        'metadata' => '{}',
    ];

    Db::$references = [
        ['resource_id' => '10', 'file_id' => 2],
        ['resource_id' => '10', 'file_id' => 1],
    ];
    Files::$rows = [
        $file(1, 10, 'aaaaaaaaaaaaaaaa'),
        $file(2, 10, 'bbbbbbbbbbbbbbbb'),
        $file(3, 20, 'cccccccccccccccc'),
    ];

    $method = new ReflectionMethod(Cards::class, 'hydrateCards');
    $method->setAccessible(true);
    $cards = $method->invoke(null, [
        ['id' => 10, 'pictures' => '[]'],
        ['id' => 20, 'pictures' => '[]'],
    ], true);

    $assert(Db::$referenceQueries === 1, 'a card page must query file_references exactly once');
    $assert(Files::$queries === 1, 'a card page must query files exactly once');
    $assert(Files::$securePublicCalls === 1, 'public card hydration must apply the secure public file scope');
    $assert(array_column($cards, 'id') === [10, 20], 'card hydration must preserve card order');
    $assert(array_column($cards[0]['media'], 'file_id') === [2, 1], 'ledger order must determine card media order');
    $assert(array_column($cards[1]['media'], 'file_id') === [3], 'legacy ref_type/ref_id media must remain readable without ledger rows');
    $assert($cards[0]['pictures'] === ['/media/bbbbbbbbbbbbbbbb', '/media/aaaaaaaaaaaaaaaa'], 'public media must use stable hash URLs');

    $singleCard = new ReflectionMethod(Cards::class, 'hydrateCard');
    $singleCard->setAccessible(true);
    $legacyPictures = $singleCard->invoke(null, [
        'id' => 30,
        'cover' => 'https://private.example/secret-cover.jpg',
        'pictures' => ['https://private.example/secret.jpg', '/media/cccccccccccccccc'],
    ], true, [], [], []);
    $assert($legacyPictures['pictures'] === ['/media/cccccccccccccccc'], 'public hydration must drop unverified legacy media URLs');
    $assert($legacyPictures['cover'] === null, 'public hydration must drop unverified legacy cover URLs');

    $mediaResource = new ReflectionMethod(Cards::class, 'mediaResource');
    $mediaResource->setAccessible(true);
    $assert($mediaResource->invoke(null, [
        'id' => 4,
        'hash' => 'not-a-public-hash',
        'file_url' => 'https://private.example/secret-video.mp4',
        'mime_type' => 'video/mp4',
        'metadata' => ['poster_url' => 'https://private.example/secret-poster.jpg'],
    ], 1, true) === null, 'public hydration must reject files without a stable hash URL');
    $publicMedia = $mediaResource->invoke(null, [
        'id' => 5,
        'hash' => 'dddddddddddddddd',
        'file_url' => 'https://private.example/legacy.jpg',
        'mime_type' => 'image/jpeg',
        'metadata' => ['poster_url' => 'https://private.example/secret-poster.jpg'],
    ], 1, true);
    $assert($publicMedia['url'] === '/media/dddddddddddddddd', 'public media must use the stable hash URL');
    $assert($publicMedia['poster_url'] === null, 'public hydration must drop unverified poster URLs');

    $whereIn = array_values(array_filter(
        Db::$lastReferenceQuery?->calls ?? [],
        static fn(array $call): bool => $call[0] === 'whereIn' && $call[1] === 'resource_id'
    ));
    $assert(count($whereIn) === 1 && $whereIn[0][2] === ['10', '20'], 'the reference query must batch every card id');

    $fileWhereIn = array_values(array_filter(
        Files::$lastQuery?->calls ?? [],
        static fn(array $call): bool => $call[0] === 'whereIn'
    ));
    $assert(in_array(['whereIn', 'id', [2, 1]], $fileWhereIn, true), 'the file query must batch ledger file ids');
    $assert(in_array(['whereIn', 'ref_id', [20]], $fileWhereIn, true), 'the same file query must include legacy card ids');

    fwrite(STDOUT, "PASS card media hydration batches references and files\n");
}
