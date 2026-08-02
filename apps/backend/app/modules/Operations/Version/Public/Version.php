<?php

namespace app\modules\Operations\Version\Public;

use app\modules\Operations\Version\Service\VersionService;

final class Version
{
    public static function current(): array { return VersionService::public(); }
}
