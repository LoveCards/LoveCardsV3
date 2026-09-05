<?php
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
require $root . '/app/modules/Content/Cards/Service/CardVisibilityPolicy.php';

use app\modules\Content\Cards\Service\CardVisibilityPolicy;

final class VisibilityQueryProbe
{
    /** @var array<int,array{sql:string,bind:array}> */
    public array $raw = [];

    public function whereRaw(string $sql, array $bind = []): self
    {
        $this->raw[] = ['sql' => $sql, 'bind' => $bind];
        return $this;
    }

    public function where(...$arguments): self
    {
        return $this;
    }
}

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$routeSegment = static function (string $source, string $start, string $next): string {
    $from = strpos($source, $start);
    $to = $from === false ? false : strpos($source, $next, $from + strlen($start));
    if ($from === false || $to === false) return '';
    return substr($source, $from, $to - $from);
};

$query = new VisibilityQueryProbe();
CardVisibilityPolicy::apply($query, 42);
$assert(count($query->raw) === 3, 'authenticated visibility must install all three policy predicates');
foreach ($query->raw as $predicate) {
    $assert($predicate['bind'] === [], 'raw visibility predicates must not use ThinkORM named binds');
}
$assert(str_contains($query->raw[1]['sql'], 'vis_b.actor_id = 42'), 'authenticated block predicate must contain the normalized viewer id');
$assert(str_contains($query->raw[2]['sql'], 'cards.user_id = 42'), 'authenticated audience predicate must contain the normalized viewer id');

$cardsRoutes = file_get_contents($root . '/app/api/route/cards.php');
$commentsRoutes = file_get_contents($root . '/app/api/route/comments.php');
$cardComments = $routeSegment($cardsRoutes, "Route::get('cards/:id/comments'", "Route::get('cards/:id'");
$commentDetail = $routeSegment($commentsRoutes, "Route::get('comments/:id'", "Route::post('comments/:id/like'");
$assert(str_contains($cardComments, 'middleware(OptionalJwtAuth::class)'), 'public card comments must resolve an optional authenticated viewer');
$assert(str_contains($commentDetail, 'middleware(OptionalJwtAuth::class)'), 'public comment detail must resolve an optional authenticated viewer');

fwrite(STDOUT, "PASS authenticated card visibility regression\n");
