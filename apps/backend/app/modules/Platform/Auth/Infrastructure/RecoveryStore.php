<?php

declare(strict_types=1);

namespace app\modules\Platform\Auth\Infrastructure;

use app\modules\Platform\Auth\Contract\RecoveryRepository;
use think\facade\Db;

final class RecoveryStore implements RecoveryRepository
{
    public function issue(string $account, \DateTimeImmutable $at, int $ttlSeconds): array
    {
        $user = Db::table('users')
            ->where(function ($query) use ($account): void {
                $query->where('number', $account)
                    ->whereOr('username', $account)
                    ->whereOr('email', $account)
                    ->whereOr('phone', $account);
            })
            ->whereNull('deleted_at')
            ->field('id')
            ->find();

        $result = ['challenge_id' => null, 'expires_in' => $ttlSeconds];
        if ($user === null) {
            return $result;
        }

        $code = (string) random_int(100000, 999999);
        $challengeId = (int) Db::table('security_challenges')->insertGetId([
            'user_id' => (int) $user['id'],
            'purpose' => 'password_recovery',
            'subject_hash' => hash('sha256', strtolower($account)),
            'secret_hash' => hash('sha256', $code),
            'attempts' => 0,
            'expires_at' => $this->utc($at->modify('+' . $ttlSeconds . ' seconds')),
            'consumed_at' => null,
            'created_at' => $this->utc($at),
        ]);
        $result['challenge_id'] = $challengeId;
        if ((bool) config('app.app_debug')) {
            $result['debug_code'] = $code;
        }
        return $result;
    }

    public function lockChallenge(int $challengeId): ?array
    {
        $row = Db::table('security_challenges')->where('id', $challengeId)->lock(true)->find();
        return $row ?: null;
    }

    public function incrementAttempts(int $challengeId): void
    {
        $updated = Db::table('security_challenges')->where('id', $challengeId)->whereNull('consumed_at')->update([
            'attempts' => Db::raw('attempts + 1'),
        ]);
        if ($updated !== 1) {
            throw new \RuntimeException('恢复挑战尝试次数写入失败');
        }
    }

    public function updatePassword(int $userId, string $passwordHash, \DateTimeImmutable $at): bool
    {
        return Db::table('users')->where('id', $userId)->whereNull('deleted_at')->update([
            'password' => $passwordHash,
            'security_version' => Db::raw('security_version + 1'),
            'credentials_changed_at' => $this->utc($at),
            'updated_at' => $this->utc($at),
        ]) === 1;
    }

    public function consume(int $challengeId, \DateTimeImmutable $at): void
    {
        $updated = Db::table('security_challenges')->where('id', $challengeId)->whereNull('consumed_at')->update([
            'consumed_at' => $this->utc($at),
        ]);
        if ($updated !== 1) {
            throw new \RuntimeException('恢复挑战消费失败');
        }
    }

    private function utc(\DateTimeImmutable $value): string
    {
        return $value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
