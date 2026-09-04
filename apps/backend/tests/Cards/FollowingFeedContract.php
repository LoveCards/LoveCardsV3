<?php
declare(strict_types=1);

namespace think\facade {
    final class FollowingQuery
    {
        public function __construct(private string $table) {}
        public function alias(...$args): self { return $this; }
        public function join(...$args): self { return $this; }
        public function where(...$args): self { return $this; }
        public function whereNull(...$args): self { return $this; }
        public function whereIn(...$args): self { return $this; }
        public function field(...$args): self { return $this; }
        public function column(...$args): array
        {
            return $this->table === 'user_follows' ? [2, 3, 3] : [3];
        }
    }

    final class Db
    {
        public static function table(string $table): FollowingQuery
        {
            return new FollowingQuery($table);
        }
    }
}

namespace {
    $root = dirname(__DIR__, 2);
    require $root . '/vendor/autoload.php';
    require $root . '/vendor/topthink/framework/src/helper.php';
    require $root . '/app/api/controller/BaseController.php';
    require $root . '/app/modules/Content/Cards/Transport/Api/FollowingFeed.php';
    require $root . '/app/modules/Content/Cards/Service/Cards.php';

    use app\api\controller\BaseController;
    use app\modules\Content\Cards\Service\Cards;
    use app\modules\Content\Cards\Transport\Api\FollowingFeed;

    final class FollowingFeedValidationController extends BaseController
    {
        public function validateList(array $input): array
        {
            return $this->param(FollowingFeed::class, FollowingFeed::$all_scene['list'], $input);
        }
    }

    $controller = (new ReflectionClass(FollowingFeedValidationController::class))->newInstanceWithoutConstructor();
    $assert = static function (bool $condition, string $message): void {
        if (!$condition) throw new RuntimeException($message);
    };

    $assert($controller->validateList([]) === [], 'empty following feed query should use server defaults');
    $assert($controller->validateList(['page' => '2', 'list_rows' => '50']) === ['page' => '2', 'list_rows' => '50'], 'following feed pagination should remain query-compatible');
    $schemas = FollowingFeed::requestSchemas();
    $assert(($schemas['list']['additionalProperties'] ?? true) === false, 'following feed must reject discovery-only filters');
    $assert(($schemas['list']['properties']['list_rows']['maximum'] ?? null) === 100, 'following feed page size must be capped at 100');

    $activeFollowees = new ReflectionMethod(Cards::class, 'activeFolloweeIds');
    $activeFollowees->setAccessible(true);
    $assert($activeFollowees->invoke(null, 9) === [2], 'private followees must be excluded while active public/followers users remain eligible');

    $routeSource = file_get_contents($root . '/app/api/route/cards.php');
    $assert(str_contains($routeSource, "Route::get('me/feed/following', 'Content.Cards/listFollowing')"), 'following feed route must be declared');
    $assert(str_contains($routeSource, "->name('cards.listFollowing')"), 'following feed route must have a stable SDK operation name');

    fwrite(STDOUT, "PASS following feed API contract\n");
}
