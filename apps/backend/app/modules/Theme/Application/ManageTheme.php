<?php
declare(strict_types=1);

namespace app\modules\Theme\Application;

use app\modules\Theme\Service\ThemeManager;
use app\modules\Theme\Contract\ThemeMutationLock;
use app\shared\Observability\AuditLogger;
use app\shared\Transaction\TransactionManager;

/** 管理端主题变更的唯一用例入口；运行时读取不经过此处。 */
final class ManageTheme
{
    public function __construct(private AuditLogger $audit, private TransactionManager $transactions, private ThemeMutationLock $lock) {}

    public function install(string $zipPath, int $actorId): array
    {
        return $this->filesystem('theme.upload', null, $actorId, [
            'archive_checksum' => hash_file('sha256', $zipPath),
            'archive_size' => filesize($zipPath),
        ], static fn (): array => ThemeManager::install($zipPath));
    }

    public function delete(string $id, string $release, int $actorId): void
    {
        $this->filesystem('theme.delete', $id . ':' . $release, $actorId, [], static function () use ($id, $release): void {
            ThemeManager::delete($id, $release);
        });
    }

    public function activate(string $id, string $release, int $actorId, string $hostname): void
    {
        $this->database('theme.activate', $id . ':' . $release, $actorId, ['hostname' => $hostname], static function () use ($id, $release): void {
            ThemeManager::activate($id, $release);
        });
    }

    public function updateConfig(string $id, string $release, array $values, int $actorId, string $hostname): void
    {
        // 只记录键名，不把配置值、密钥或 URI 凭据写入审计。
        $this->database('theme.config', $id . ':' . $release, $actorId, [
            'hostname' => $hostname, 'changed_keys' => array_keys($values),
        ], static function () use ($id, $release, $values): void {
            ThemeManager::updateConfig($id, $release, $values);
        });
    }

    private function database(string $action, string $resourceId, int $actorId, array $metadata, callable $change): void
    {
        $operationId = bin2hex(random_bytes(16));
        // 锁必须覆盖事务提交；否则删除可能在未提交的绑定不可见时移除 Release。
        $this->lock->run(fn () => $this->transactions->run(function () use ($action, $resourceId, $actorId, $metadata, $change, $operationId): void {
            $change();
            try {
                $this->audit->record($actorId, $action . '.completed', 'theme_release', $resourceId, $metadata + ['operation_id' => $operationId]);
            } catch (\Throwable $error) {
                throw new ThemeOperationException('主题变更未提交：审计记录失败，请稍后重试。', $operationId, 'theme_audit_unavailable', 'none', $error);
            }
        }));
    }

    private function filesystem(string $action, ?string $resourceId, int $actorId, array $metadata, callable $change): mixed
    {
        $operationId = bin2hex(random_bytes(16));
        $metadata['operation_id'] = $operationId;
        // 请求审计先落盘；失败时禁止安装或删除文件。
        try {
            $this->audit->record($actorId, $action . '.requested', 'theme_release', $resourceId, $metadata);
        } catch (\Throwable $error) {
            throw new ThemeOperationException('主题操作未执行：审计不可用，请稍后重试。', $operationId, 'theme_audit_unavailable', 'none', $error);
        }

        try {
            $result = $this->lock->run($change);
        } catch (\Throwable $error) {
            try {
                $this->audit->record($actorId, $action . '.failed', 'theme_release', $resourceId, $metadata + ['failure_type' => $error::class, 'side_effects' => 'possible']);
            } catch (\Throwable $auditError) {
                throw new ThemeOperationException('主题操作未完成，且结果审计失败；文件状态可能已变化，请刷新核对后再操作。', $operationId, 'theme_result_unrecorded', 'possible', $auditError);
            }
            throw new ThemeOperationException($error->getMessage() . '；请刷新核对文件状态后再操作。', $operationId, 'theme_operation_incomplete', 'possible', $error);
        }

        if (is_array($result)) {
            $resourceId = (string) $result['id'] . ':' . (string) $result['release'];
        }
        try {
            $this->audit->record($actorId, $action . '.completed', 'theme_release', $resourceId, $metadata + ['side_effects' => 'applied']);
        } catch (\Throwable $error) {
            throw new ThemeOperationException('主题操作已执行，但结果审计失败；请刷新核对，勿直接重复操作。', $operationId, 'theme_result_unrecorded', 'applied', $error);
        }
        return $result;
    }
}
