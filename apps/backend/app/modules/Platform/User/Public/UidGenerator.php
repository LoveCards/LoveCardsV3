<?php
declare(strict_types=1);

namespace app\modules\Platform\User\Public;

use think\facade\Db;

/** Generates the site's immutable public user identifier. */
final class UidGenerator
{
    public static function reserve(): string
    {
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $uid = (string) random_int(100000000000, 999999999999);
            try {
                Db::table('user_uid_registry')->insert([
                    'uid' => $uid,
                    'user_id' => null,
                    'issued_at' => date('Y-m-d H:i:s'),
                ]);
                return $uid;
            } catch (\Throwable $exception) {
                if ((int) $exception->getCode() !== 23000) throw $exception;
            }
        }
        throw new \RuntimeException('无法分配唯一用户 UID');
    }
}
