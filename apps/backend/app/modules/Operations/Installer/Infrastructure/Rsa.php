<?php

namespace app\modules\Operations\Installer\Infrastructure;

use app\shared\SystemPaths\SystemPaths;

class Rsa
{
    /**
     * 生成公私钥对
     *
     * @return array|bool
     */
    public static function Generate()
    {
        try {
            //创建公私钥
            $res = openssl_pkey_new();
            //获取私钥
            openssl_pkey_export($res, $private_key);
            //获取公钥
            $public_key = openssl_pkey_get_details($res)['key'];
            //组合rsa
            $rsa = [
                'public' => $public_key,
                'private' => $private_key,
            ];

            return $rsa;
        } catch (\Throwable $th) {
            return false;
        }
    }

    /**
     * 更新密钥对
     *
     * @param string $public
     * @param string $private
     * @return boolean
     */
    public static function UpdataRsa($public, $private, ?SystemPaths $paths = null): bool
    {
        try {
            $public = (string) $public;
            $private = (string) $private;
            if ($public === '' || $private === '' || strlen($public) > 1048576 || strlen($private) > 1048576) {
                return false;
            }
            $paths = PersistentFile::paths($paths);
            // Readers only select persistent keys when both files exist.
            PersistentFile::write($paths->keysRoot(), 'private.pem', $private, 0600);
            PersistentFile::write($paths->keysRoot(), 'public.pem', $public, 0640);
            return true;
        } catch (\Throwable $th) {
            return false;
        }
    }
}
