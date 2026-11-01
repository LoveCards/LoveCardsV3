<?php

namespace app\modules\Content\Cards\Public;

use app\modules\Content\Cards\Service\Cards as CardsService;

final class Cards
{
    public static function requiresApproval(): bool { return CardsService::requiresApproval(); }
    public static function pendingStatus(): int { return CardsService::pendingStatus(); }
    public static function hotList(): array { return CardsService::hotList(); }
    public static function list(array $params, array $caps = []): array { return CardsService::list($params, $caps); }
    public static function get(int $id, array $caps = []): array { return CardsService::get($id, $caps); }
    public static function count(): int { return (int) CardsService::count(); }
}
