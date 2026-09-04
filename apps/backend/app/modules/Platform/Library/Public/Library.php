<?php

namespace app\modules\Platform\Library\Public;

use app\api\ApiException;
use app\modules\Content\Cards\Public\Cards;
use app\modules\Platform\Eventing\Public\DomainEventBus;
use app\modules\Platform\Library\Application\ManageLibrary;
use app\modules\Platform\Library\Contract\LibraryRepository;
use app\shared\Resource\ResourceRef;
use app\shared\Transaction\TransactionManager;

final class Library
{
    private static function useCase(): ManageLibrary
    {
        return new ManageLibrary(app(LibraryRepository::class));
    }

    public static function save(int $userId, ResourceRef $resource): bool
    {
        return app(TransactionManager::class)->run(function () use ($userId, $resource): bool {
            $created = self::useCase()->save($userId, $resource);
            if ($created) {
                self::event('library.saved', $userId, $resource, self::savedPayload($userId, $resource));
            }
            return $created;
        });
    }

    public static function remove(int $userId, ResourceRef $resource): bool { return self::useCase()->remove($userId, $resource); }
    public static function recordView(int $userId, ResourceRef $resource): void { self::useCase()->recordView($userId, $resource); }
    public static function saved(int $userId, ?string $cursor = null, int $limit = 20): array { return self::useCase()->saved($userId, $cursor, $limit); }
    public static function history(int $userId, ?string $cursor = null, int $limit = 20): array { return self::useCase()->history($userId, $cursor, $limit); }
    public static function clearHistory(int $userId): int { return self::useCase()->clearHistory($userId); }
    /** @return array<string, int> */
    private static function savedPayload(int $userId, ResourceRef $resource): array
    {
        $payload = ['user_id' => $userId, 'actor_id' => $userId];
        if ($resource->type() !== 'card' || !ctype_digit($resource->id())) {
            return $payload;
        }

        $cardId = (int) $resource->id();
        if ($cardId < 1) return $payload;
        $payload['card_id'] = $cardId;

        try {
            $card = Cards::get($cardId);
        } catch (ApiException) {
            // 收藏能力保持通用；不可公开访问的卡片只是不产生作者通知。
            return $payload;
        }
        $ownerId = (int) ($card['user_id'] ?? 0);
        if ($ownerId > 0) $payload['owner_id'] = $ownerId;
        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private static function event(string $type, int $userId, ResourceRef $resource, array $payload = []): void
    {
        app(DomainEventBus::class)->publish(
            $type,
            $resource->type(),
            $resource->id(),
            ['user_id' => $userId] + $payload,
            $userId
        );
    }
}
