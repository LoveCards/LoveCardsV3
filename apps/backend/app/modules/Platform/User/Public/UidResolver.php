<?php
declare(strict_types=1);

namespace app\modules\Platform\User\Public;

use app\api\ApiException;
use think\facade\Db;

final class UidResolver
{
    public static function id(string $uid): int
    {
        if (!preg_match('/^[0-9]{12}$/', $uid)) throw ApiException::notFound('用户不存在', ApiException::CODE_USER_NOT_FOUND);
        $id = Db::table('users')->where('uid', $uid)->whereNull('deleted_at')->value('id');
        if ($id === null) throw ApiException::notFound('用户不存在', ApiException::CODE_USER_NOT_FOUND);
        return (int) $id;
    }
}
