<?php

namespace app\modules\Theme\Public;

use app\modules\Content\Cards\Public\Cards;
use app\modules\Content\Comments\Public\Comments;
use app\modules\Content\Tags\Public\Tags;

final class ThemeDataProvider
{
    public static function call(string $operation, array $params = []): mixed
    {
        return match ($operation) {
            'cards.hot' => Cards::hotList(),
            'cards.list' => Cards::list($params),
            'cards.get' => Cards::get((int) ($params['id'] ?? 0)),
            'tags.list' => Tags::list($params),
            'tags.get' => Tags::get((int) ($params['id'] ?? 0)),
            'comments.list' => Comments::listAll($params),
            default => throw new \InvalidArgumentException('Unknown theme data operation: ' . $operation),
        };
    }
}
