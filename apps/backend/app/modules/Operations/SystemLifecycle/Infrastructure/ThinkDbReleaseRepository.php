<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Infrastructure;

use app\modules\Operations\SystemLifecycle\Contract\ReleaseRepository;
use app\modules\Operations\SystemLifecycle\Domain\VerifiedReleaseManifest;
use think\facade\Db;

final class ThinkDbReleaseRepository implements ReleaseRepository
{
    public function sequenceFloor(?string $excludingReleaseId = null): int
    {
        $query = Db::table('system_releases');
        if ($excludingReleaseId !== null) $query->where('release_id', '<>', $excludingReleaseId);
        return (int) ($query->max('sequence') ?? 0);
    }

    public function find(string $releaseId): ?array
    {
        $row = Db::table('system_releases')->where('release_id', $releaseId)->find();
        return $row ?: null;
    }

    public function stage(VerifiedReleaseManifest $release): void
    {
        $manifest = $release->manifest();
        $existing = $this->find($manifest->releaseId());
        if ($existing !== null) {
            $this->assertSameRelease($existing, $release);
            return;
        }

        $now = $release->verifiedAt()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
        try {
            Db::table('system_releases')->insert([
                'release_id' => $manifest->releaseId(),
                'sequence' => $manifest->sequence(),
                'version' => $manifest->version(),
                'manifest_checksum' => $manifest->documentChecksum(),
                'manifest_json' => $manifest->canonicalDocument(),
                'status' => 'staged',
                'created_by' => null,
                'verified_at' => $now,
                'activated_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (\Throwable $exception) {
            $existing = $this->find($manifest->releaseId());
            if ($existing === null) throw $exception;
            $this->assertSameRelease($existing, $release);
        }
    }

    public function activate(string $releaseId, ?string $previousReleaseId): void
    {
        Db::startTrans();
        try {
            $target = Db::table('system_releases')->where('release_id', $releaseId)->lock(true)->find();
            if ($target === null || !in_array((string) $target['status'], ['staged', 'active'], true)) {
                throw new \RuntimeException('Release 账本中不存在可激活记录');
            }
            $activeRows = Db::table('system_releases')->where('status', 'active')->lock(true)->select()->toArray();
            $activeIds = array_values(array_map(static fn(array $row): string => (string) $row['release_id'], $activeRows));
            if ($previousReleaseId !== null && (string) $target['status'] !== 'active'
                && ($activeIds === [] || !in_array($previousReleaseId, $activeIds, true))) {
                throw new \RuntimeException('Release 账本当前 active 与升级计划 previous_release 不一致');
            }
            if ($previousReleaseId === null && $activeIds !== [] && !in_array($releaseId, $activeIds, true)) {
                throw new \RuntimeException('Release 账本已有 active，但升级计划缺少 previous_release');
            }
            $now = date('Y-m-d H:i:s.u');
            Db::table('system_releases')
                ->where('status', 'active')
                ->where('release_id', '<>', $releaseId)
                ->update(['status' => 'superseded', 'updated_at' => $now]);
            $updated = Db::table('system_releases')->where('release_id', $releaseId)->update([
                'status' => 'active',
                'activated_at' => $target['activated_at'] ?: $now,
                'updated_at' => $now,
            ]);
            if ($updated !== 1 && (string) $target['status'] !== 'active') {
                throw new \RuntimeException('Release 激活账本更新失败');
            }
            Db::commit();
        } catch (\Throwable $exception) {
            Db::rollback();
            throw $exception;
        }
    }

    public function rollback(string $releaseId, ?string $restoredReleaseId): void
    {
        Db::startTrans();
        try {
            $now = date('Y-m-d H:i:s.u');
            Db::table('system_releases')->where('release_id', $releaseId)->update([
                'status' => 'rolled_back',
                'updated_at' => $now,
            ]);
            if ($restoredReleaseId !== null) {
                $updated = Db::table('system_releases')->where('release_id', $restoredReleaseId)->update([
                    'status' => 'active',
                    'updated_at' => $now,
                ]);
                if ($updated !== 1) throw new \RuntimeException('恢复 Release 账本记录不存在');
            }
            Db::commit();
        } catch (\Throwable $exception) {
            Db::rollback();
            throw $exception;
        }
    }

    /** @param array<string, mixed> $existing */
    private function assertSameRelease(array $existing, VerifiedReleaseManifest $release): void
    {
        $manifest = $release->manifest();
        if ((int) $existing['sequence'] !== $manifest->sequence()
            || !hash_equals((string) $existing['manifest_checksum'], $manifest->documentChecksum())) {
            throw new \RuntimeException('同名 Release 账本记录与已验签 Manifest 冲突');
        }
    }
}
