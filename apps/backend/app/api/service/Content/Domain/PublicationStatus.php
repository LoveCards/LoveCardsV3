<?php

namespace app\api\service\Content\Domain;

final class PublicationStatus
{
    public const NORMAL = 0;
    public const BANNED = 1;
    public const HIDDEN = 2;
    public const PENDING = 3;

    public static function visible(): int
    {
        return self::NORMAL;
    }

    public static function pendingApproval(): int
    {
        return self::PENDING;
    }

    public static function batchCapability(string $method): ?string
    {
        return [
            'top'       => 'cards.pin',
            'unset_top' => 'cards.pin',
            'approve'   => 'cards.approve',
            'ban'       => 'cards.approve',
            'hide'      => 'cards.update',
            'unhide'    => 'cards.update',
            'delete'    => 'cards.delete',
        ][$method] ?? null;
    }

    /**
     * @return array{0: int[], 1: int[]}
     */
    public static function toggleRange(string $method): array
    {
        return match ($method) {
            'approve' => [[self::NORMAL, self::PENDING], [self::BANNED, self::HIDDEN]],
            'ban' => [[self::NORMAL, self::BANNED], [self::HIDDEN, self::PENDING]],
            'hide' => [[self::NORMAL, self::HIDDEN], [self::BANNED, self::PENDING]],
            default => [[], []],
        };
    }
}
