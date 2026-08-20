<?php

namespace app\modules\Platform\Auth\Infrastructure;

use app\modules\Platform\Auth\Public\AuthUser;
use app\modules\Platform\Auth\Contract\UserRepository;
use app\modules\Platform\User\Infrastructure\Persistence\Model\Users as UsersModel;
use app\modules\Platform\Rbac\Infrastructure\Persistence\Model\UserRoles;
use think\facade\Db;

class ThinkOrmUserRepository implements UserRepository
{
    public function findById(int $id): ?AuthUser
    {
        $user = UsersModel::where('id', $id)
            ->withoutField(['password'])
            ->find();

        return $this->map($user, false);
    }

    public function findByAccount(string $account): ?AuthUser
    {
        $user = UsersModel::where('number', $account)
            ->whereOr('username', $account)
            ->whereOr('email', $account)
            ->whereOr('phone', $account)
            ->find();

        return $this->map($user);
    }

    public function contactExists(string $email, string $phone): bool
    {
        if ($email !== '') {
            return UsersModel::where('email', $email)->find() !== null;
        }
        if ($phone !== '') {
            return UsersModel::where('phone', $phone)->find() !== null;
        }

        return false;
    }

    public function create(
        string $number,
        string $username,
        string $email,
        string $phone,
        string $passwordHash,
        array $roleIds,
        int $status
    ): AuthUser {
        Db::startTrans();
        try {
            $user = UsersModel::create([
                'number' => $number,
                'username' => $username,
                'email' => $email,
                'phone' => $phone,
                'status' => $status,
                'password' => $passwordHash,
            ]);
            $now = date('Y-m-d H:i:s');
            $rows = array_map(static function ($roleId) use ($user, $now) {
                return ['user_id' => (int) $user->id, 'role_id' => (int) $roleId, 'created_at' => $now];
            }, array_values(array_unique($roleIds)));
            if ($rows !== []) UserRoles::insertAll($rows);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }

        return $this->map($user);
    }

    private function map($user, bool $includePassword = true): ?AuthUser
    {
        if (!$user) {
            return null;
        }

        $roleIds = UserRoles::where('user_id', (int) $user->id)->column('role_id') ?: [];

        return new AuthUser(
            (int) $user->id,
            (int) $user->status,
            $includePassword ? (string) $user->password : '',
            $roleIds
        );
    }
}
