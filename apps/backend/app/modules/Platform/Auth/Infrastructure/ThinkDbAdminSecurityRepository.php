<?php
declare(strict_types=1);

namespace app\modules\Platform\Auth\Infrastructure;

use app\modules\Platform\Auth\Contract\AdminSecurityRepository;
use think\facade\Db;

final class ThinkDbAdminSecurityRepository implements AdminSecurityRepository
{
    public function findUserOverview(int $userId, \DateTimeImmutable $now): ?array
    {
        $user = Db::table('users')
            ->where('id', $userId)
            ->whereNull('deleted_at')
            ->field('id,number,username,email,phone,status,security_version,credentials_changed_at,created_at,updated_at')
            ->find();
        if ($user === null) {
            return null;
        }

        $privacy = Db::table('privacy_preferences')->where('user_id', $userId)->find();
        $active = Db::table('auth_sessions')
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->where('token_expires_at', '>', $this->utc($now))
            ->count();

        return [
            'user' => $user,
            'privacy' => $privacy ?: null,
            'sessions' => [
                'active' => (int) $active,
                'total' => (int) Db::table('auth_sessions')->where('user_id', $userId)->count(),
            ],
            'recovery_challenges' => (int) Db::table('security_challenges')->where('user_id', $userId)->count(),
        ];
    }

    public function listRecoveryChallenges(?string $status, int $page, int $limit, \DateTimeImmutable $now): array
    {
        $query = Db::table('security_challenges')
            ->field('id,user_id,purpose,expires_at,consumed_at,attempts,created_at');
        $timestamp = $this->utc($now);
        if ($status === 'active') {
            $query->whereNull('consumed_at')->where('expires_at', '>', $timestamp);
        } elseif ($status === 'consumed') {
            $query->whereNotNull('consumed_at');
        } elseif ($status === 'expired') {
            $query->whereNull('consumed_at')->where('expires_at', '<=', $timestamp);
        }

        $total = (int) (clone $query)->count('id');
        $items = $query->order('id', 'desc')->page($page, $limit)->select()->toArray();
        return ['items' => $items, 'total' => $total];
    }

    private function utc(\DateTimeImmutable $value): string
    {
        return $value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
