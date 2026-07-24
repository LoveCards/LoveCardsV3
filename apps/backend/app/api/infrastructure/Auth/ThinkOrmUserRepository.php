<?php

namespace app\api\infrastructure\Auth;

use app\api\application\Auth\AuthUser;
use app\api\application\Auth\UserRepository;
use app\api\model\Users as UsersModel;

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
        $user = UsersModel::create([
            'number' => $number,
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'roles_id' => $roleIds,
            'status' => $status,
            'password' => $passwordHash,
        ]);

        return $this->map($user);
    }

    private function map($user, bool $includePassword = true): ?AuthUser
    {
        if (!$user) {
            return null;
        }

        $roleIds = is_array($user->roles_id)
            ? $user->roles_id
            : (json_decode($user->roles_id, true) ?: []);

        return new AuthUser(
            (int) $user->id,
            (int) $user->status,
            $includePassword ? (string) $user->password : '',
            $roleIds
        );
    }
}
