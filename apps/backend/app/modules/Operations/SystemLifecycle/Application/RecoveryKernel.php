<?php
declare(strict_types=1);
namespace app\modules\Operations\SystemLifecycle\Application;
use app\modules\Operations\SystemLifecycle\Contract\ReleasePointer;
use app\shared\SystemPaths\SystemPaths;

/** Stable, dependency-light release pointer and recovery operations. */
final class RecoveryKernel implements ReleasePointer
{
    public function __construct(private SystemPaths $paths) {}
    public function activeReleaseId(): ?string
    {
        $pointer=$this->paths->activeReleasePointer();
        if (is_link($pointer) || !is_file($pointer)) return null;
        $id=trim((string)file_get_contents($pointer));
        $root = $this->paths->releasesRoot();
        if (is_link($root) || !is_dir($root)) return null;
        $release = preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/',$id) === 1 ? $this->paths->releaseRoot($id) : '';
        $releaseReal = $release !== '' ? realpath($release) : false;
        $rootReal = realpath($root);
        return $releaseReal !== false && $rootReal !== false && $this->isWithin($releaseReal, $rootReal) ? $id : null;
    }
    public function activate(string $releaseId): void
    {
        $release=$this->paths->releaseRoot($releaseId);
        $releasesRoot = $this->paths->releasesRoot();
        if (is_link($releasesRoot) || !is_dir($releasesRoot) || !is_dir($release) || is_link($release) || !is_file($release . DIRECTORY_SEPARATOR . 'release.json')) throw new \RuntimeException('Release 不存在或缺少 Manifest');
        $releaseReal = realpath($release);
        $rootReal = realpath($releasesRoot);
        if ($releaseReal === false || $rootReal === false || !$this->isWithin($releaseReal, $rootReal)) throw new \RuntimeException('Release 路径越界');
        $manifest = json_decode((string) file_get_contents($release . DIRECTORY_SEPARATOR . 'release.json'), true);
        if (!is_array($manifest) || (string) ($manifest['release_id'] ?? '') !== $releaseId) {
            throw new \RuntimeException('Release Manifest 与目录 ID 不一致');
        }
        $this->writePointer($releaseId . "\n");
    }

    public function restore(string $backupPath, string $expectedChecksum): ?string
    {
        if (preg_match('/^[a-f0-9]{64}$/', strtolower($expectedChecksum)) !== 1
            || !is_file($backupPath) || is_link($backupPath)) {
            throw new \RuntimeException('Release 指针备份不可恢复');
        }
        $real = realpath($backupPath);
        $backupRoot = realpath($this->paths->backupsRoot());
        if ($real === false || $backupRoot === false || !$this->isWithin($real, $backupRoot)) {
            throw new \RuntimeException('Release 指针备份路径越界');
        }
        $checksum = hash_file('sha256', $backupPath);
        if ($checksum === false || !hash_equals(strtolower($expectedChecksum), $checksum)) {
            throw new \RuntimeException('Release 指针备份校验失败');
        }
        $value = trim((string) file_get_contents($backupPath));
        if ($value === '') {
            $this->writePointer("\n");
            return null;
        }
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/', $value) !== 1) {
            throw new \RuntimeException('Release 指针备份内容不合法');
        }
        $this->activate($value);
        return $value;
    }

    /** @return array<string, string|null> */
    public function recoveryState(): array { return ['active_release_id'=>$this->activeReleaseId(),'pointer'=>$this->paths->activeReleasePointer(),'recovery_root'=>$this->paths->recoveryRoot()]; }

    private function writePointer(string $value): void
    {
        $pointer = $this->paths->activeReleasePointer();
        $root = dirname($pointer);
        if (is_link($root)) throw new \RuntimeException('实例持久目录不能是 symlink');
        if (is_link($pointer)) throw new \RuntimeException('active Release 指针不能是 symlink');
        if (!is_dir($root) && !mkdir($root,0750,true) && !is_dir($root)) throw new \RuntimeException('无法创建实例目录');
        $tmp=$pointer.'.next.'.bin2hex(random_bytes(8));
        $handle = fopen($tmp, 'xb');
        if ($handle === false) throw new \RuntimeException('无法创建 active Release 临时指针');
        try {
            $offset = 0;
            $length = strlen($value);
            while ($offset < $length) {
                $written = fwrite($handle, substr($value, $offset));
                if ($written === false || $written === 0) throw new \RuntimeException('写入 active Release 指针失败');
                $offset += $written;
            }
            if (!fflush($handle) || (function_exists('fsync') && !fsync($handle))) {
                throw new \RuntimeException('active Release 指针无法同步');
            }
        } finally {
            fclose($handle);
        }
        @chmod($tmp, 0640);
        if (!rename($tmp, $pointer)) {
            @unlink($tmp);
            throw new \RuntimeException('切换 active Release 失败');
        }
    }

    private function isWithin(string $candidate, string $root): bool
    {
        $candidate = str_replace('\\', '/', rtrim($candidate, '/\\'));
        $root = str_replace('\\', '/', rtrim($root, '/\\'));
        if (DIRECTORY_SEPARATOR === '\\') { $candidate = strtolower($candidate); $root = strtolower($root); }
        return $candidate === $root || strncmp($candidate, $root . '/', strlen($root) + 1) === 0;
    }
}
