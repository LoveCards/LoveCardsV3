<?php

namespace app\modules\Platform\Safety\Public;

use app\modules\Platform\Safety\Application\AdminReports;
use app\modules\Platform\Safety\Application\ManageSafety;
use app\modules\Platform\Safety\Contract\SafetyRepository;
use app\modules\Platform\Safety\Contract\ReportTargetResolver;
use app\modules\Platform\Safety\Domain\PrivacyPreferences;
use app\modules\Platform\Safety\Domain\ReportSubmission;

final class Safety
{
    private static function useCase(): ManageSafety { return new ManageSafety(app(SafetyRepository::class), app(ReportTargetResolver::class)); }
    public static function savePreferences(int $userId, PrivacyPreferences $preferences): void { self::useCase()->savePreferences($userId, $preferences); }
    public static function preferences(int $userId): PrivacyPreferences { return self::useCase()->preferences($userId); }
    public static function addRelation(int $actorId, int $targetId, string $kind): bool { return self::useCase()->addRelation($actorId, $targetId, $kind); }
    public static function removeRelation(int $actorId, int $targetId, string $kind): bool { return self::useCase()->removeRelation($actorId, $targetId, $kind); }
    public static function canMessage(int $senderId, int $recipientId): bool { return self::useCase()->canMessage($senderId, $recipientId); }
    public static function report(ReportSubmission $submission): int { return self::useCase()->report($submission); }
    public static function adminReports(): AdminReports { return app(AdminReports::class); }
}
