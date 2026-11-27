<?php
declare(strict_types=1);

namespace app\modules\Platform\Notifications\Public;

use app\modules\Platform\Notifications\Application\ManageSystemNoticeBatches;
use app\modules\Platform\User\Public\NotificationRecipients;
use app\shared\Authorization\GrantSet;

/** 受权管理端发布系统通告的唯一用例入口。 */
final class SystemNoticeBatches
{
    public static function prepare(array $input, int $actorId, GrantSet $grants, string $ip = ''): array { return app(ManageSystemNoticeBatches::class)->prepare($input, $actorId, $grants, $ip); }
    public static function get(int $batchId, int $actorId): array { return app(ManageSystemNoticeBatches::class)->get($batchId, $actorId); }
    public static function latest(int $actorId, ?string $requestKey = null): ?array { return app(ManageSystemNoticeBatches::class)->latest($actorId, $requestKey); }
    public static function confirm(int $batchId, int $actorId, GrantSet $grants, string $ip = ''): array { return app(ManageSystemNoticeBatches::class)->confirm($batchId, $actorId, $grants, $ip); }
    public static function retry(int $batchId, int $actorId, GrantSet $grants, array $input, string $ip = ''): array { return app(ManageSystemNoticeBatches::class)->retry($batchId, $actorId, $grants, $input, $ip); }
    public static function candidates(string $query, int $limit = 20): array { return NotificationRecipients::candidates($query, $limit); }
}
