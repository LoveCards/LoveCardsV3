<?php

declare(strict_types=1);

namespace app\modules\Operations\Dashboard\Domain;

use app\shared\Authorization\GrantSet;
use app\shared\Authorization\Scope;

final class WorkbenchPolicy
{
    /** @return string[] */
    public static function summaryKeys(GrantSet $grants): array
    {
        $keys = [];
        if ($grants->allows('cards.read', Scope::ALL)) {
            $keys[] = 'cards';
            $keys[] = 'good';
        }
        if ($grants->allows('comments.read', Scope::ALL)) $keys[] = 'comments';
        return $keys;
    }

    /** @return string[] */
    public static function taskKeys(GrantSet $grants): array
    {
        $keys = [];
        if ($grants->allows('cards.approve', Scope::ALL)) $keys[] = 'cards_pending';
        if ($grants->allows('comments.moderate', Scope::ALL)) $keys[] = 'comments_pending';
        if ($grants->allows('reports.manage', Scope::GLOBAL)) $keys[] = 'reports_open';
        if ($grants->allows('operations.retry', Scope::GLOBAL)) $keys[] = 'deliveries_dead';
        if ($grants->allows('files.update', Scope::ALL)) $keys[] = 'files_pending';
        return $keys;
    }
}
