<?php

namespace app\modules\Platform\Files\Public;

use app\modules\Platform\Files\Contract\FileRepository;

final class Files
{
    public static function repository(): FileRepository { return app(FileRepository::class); }
}
