<?php
declare(strict_types=1);

namespace app\modules\Platform\Config\Public;

use app\shared\SystemPaths\SystemPaths;
use think\facade\Env;

/** 部署级非秘密身份；同站实例共用，克隆站点必须不同，禁止后台热改。 */
final class DeploymentNamespace
{
    private function __construct(private string $prefix) {}

    public static function current(?SystemPaths $paths = null): self
    {
        $native = getenv('LC_DEPLOYMENT_ID');
        $id = $native !== false ? $native : (function_exists('app') ? Env::get('LC_DEPLOYMENT_ID', '') : '');
        if (is_string($id) && $id !== '') return self::fromId($id);

        if ($paths === null && !function_exists('app')) {
            throw new \InvalidArgumentException('部署环境缺少有效 LC_DEPLOYMENT_ID，且尚未完成安装');
        }
        $paths ??= app(SystemPaths::class);
        if (!$paths instanceof SystemPaths) throw new \RuntimeException('无法解析实例持久目录');
        $stateRoot = $paths->stateRoot();
        if (is_link($stateRoot)) throw new \RuntimeException('实例状态目录不能是 symlink');
        $lock = $stateRoot . DIRECTORY_SEPARATOR . 'install.lock';
        if (is_link($lock)) throw new \RuntimeException('安装锁不能是 symlink');
        if (is_file($lock)) {
            $size = filesize($lock);
            if ($size === false || $size < 1 || $size > 256) throw new \RuntimeException('安装锁大小不合法');
            $content = (string) file_get_contents($lock);
            if (preg_match('/^LoveCards\.cn\RLC_DEPLOYMENT_ID=([a-z0-9_-]{16,64})\R?$/D', $content, $matches) === 1) {
                return self::fromId($matches[1]);
            }
            if ($content !== "LoveCards.cn\n") throw new \RuntimeException('安装锁内容不合法');
            return self::legacy($paths);
        }

        $legacyLock = $paths->activeReleaseRoot() . DIRECTORY_SEPARATOR . 'lock.txt';
        if (!is_link($legacyLock) && is_file($legacyLock)) return self::legacy($paths);
        throw new \InvalidArgumentException('部署环境缺少有效 LC_DEPLOYMENT_ID，且尚未完成安装');
    }

    private static function legacy(SystemPaths $paths): self
    {
        // 旧安装锁没有独立 ID；持久实例根在 Release 切换后保持不变。
        $root = str_replace('\\', '/', $paths->instanceRoot());
        if (preg_match('/^[A-Za-z]:/', $root) === 1) $root = strtolower($root);
        return self::fromId('legacy-' . substr(hash('sha256', $root), 0, 32));
    }

    public static function fromId(string $id): self
    {
        if (preg_match('/^[a-z0-9][a-z0-9_-]{15,63}$/D', $id) !== 1) {
            throw new \InvalidArgumentException('部署环境缺少有效 LC_DEPLOYMENT_ID，请由部署管理员配置稳定且独立的站点身份');
        }
        return new self('lc:' . substr(hash('sha256', $id), 0, 32) . ':');
    }

    public function realtimeChannel(): string { return $this->prefix . 'realtime'; }
    public function cachePrefix(): string { return $this->prefix . 'cache:'; }
    public function scheduledLock(): string { return $this->prefix . 'eventing:scheduled'; }
}
