<?php

namespace app\modules\Platform\Storage\Public;

use app\modules\Platform\Storage\Service\StorageAdmin;

final class Storage
{
    public static function types(): array { return StorageAdmin::types(); }
    public static function channels(): array { return StorageAdmin::channels(); }
    public static function meta(string $type): ?array { return StorageAdmin::meta($type); }
}
