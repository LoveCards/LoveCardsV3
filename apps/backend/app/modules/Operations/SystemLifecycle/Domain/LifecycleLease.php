<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Domain;

/**
 * The single process-wide lease for schema and Release lifecycle work.
 *
 * Manual migration requests and Release upgrades must share this name.  A
 * separate lease would allow a migration request between two HTTP upgrade
 * advances to mutate the same schema concurrently.
 */
final class LifecycleLease
{
    public const NAME = 'system.lifecycle.upgrade';
    public const TTL_SECONDS = 900;

    private function __construct() {}
}
