<?php

namespace app\modules\Platform\Library\Public;

use app\modules\Platform\Library\Application\ManageLibrary;
use app\modules\Platform\Library\Contract\LibraryRepository;
use app\shared\Resource\ResourceRef;

final class Library
{
    private static function useCase(): ManageLibrary
    {
        return new ManageLibrary(app(LibraryRepository::class));
    }

    public static function save(int $userId, ResourceRef $resource): bool { $ok=self::useCase()->save($userId, $resource); if($ok) self::event('library.saved',$userId,$resource); return $ok; }
    public static function remove(int $userId, ResourceRef $resource): bool { $ok=self::useCase()->remove($userId, $resource); if($ok) self::event('library.removed',$userId,$resource); return $ok; }
    public static function recordView(int $userId, ResourceRef $resource): void { self::useCase()->recordView($userId, $resource); self::event('library.viewed',$userId,$resource); }
    public static function saved(int $userId, ?string $cursor = null, int $limit = 20): array { return self::useCase()->saved($userId, $cursor, $limit); }
    public static function history(int $userId, ?string $cursor = null, int $limit = 20): array { return self::useCase()->history($userId, $cursor, $limit); }
    public static function clearHistory(int $userId): int { return self::useCase()->clearHistory($userId); }
    private static function event(string $type,int $userId,ResourceRef $resource): void { app(\app\modules\Platform\Eventing\Public\DomainEventBus::class)->publish($type,$resource->type(),$resource->id(),['user_id'=>$userId],$userId); }
}
