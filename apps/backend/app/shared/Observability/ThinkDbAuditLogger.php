<?php
declare(strict_types=1);

namespace app\shared\Observability;

use app\shared\SystemPaths\PersistentConfiguration;
use think\facade\Db;

final class ThinkDbAuditLogger implements AuditLogger
{
    public function __construct(
        private SensitiveDataSanitizer $sanitizer,
        private LogContextProvider $contextProvider
    ) {}

    public function record(?int $actorId, string $action, string $resourceType, ?string $resourceId, array $metadata = []): void
    {
        $action = trim($action);
        $resourceType = trim($resourceType);
        if ($action === '' || $resourceType === '') {
            throw new \InvalidArgumentException('审计 action 和 resource_type 不得为空');
        }

        $ipAddress = isset($metadata['_ip']) ? trim((string) $metadata['_ip']) : null;
        unset($metadata['_ip']);
        $context = $this->contextProvider->current();

        // AuditLogger 专用于安全审计。写入失败必须向上传播，让调用方在
        // 同一数据库事务中回滚高风险业务写；普通技术日志由
        // ThinkPhpLoggerAdapter 保持 fail-open。
        Db::table('audit_logs')->insert([
            'actor_id' => $actorId,
            'action' => substr($action, 0, 128),
            'resource_type' => substr($resourceType, 0, 64),
            'resource_id' => $resourceId !== null ? substr($resourceId, 0, 128) : null,
            'request_id' => $context->requestId(),
            'correlation_id' => $context->correlationId(),
            'ip_hash' => $ipAddress !== null && $ipAddress !== ''
                ? hash_hmac('sha256', $ipAddress, $this->hashKey())
                : null,
            'payload' => json_encode(
                $this->sanitizer->sanitizeContext($metadata),
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ),
            'created_at' => date('Y-m-d H:i:s.u'),
        ]);
    }

    private function hashKey(): string
    {
        $configured = (string) env('app.audit_hash_key', '');
        if ($configured !== '') {
            return $configured;
        }

        $privateKey = (string) config('jwt.privateKey', '');
        if ($privateKey !== '') {
            $path = PersistentConfiguration::resolvePath($privateKey, app()->getRootPath());
            $contents = @file_get_contents($path);
            if (is_string($contents) && $contents !== '') {
                return hash('sha256', $contents, true);
            }
        }

        // 安装早期尚未生成 RSA Key 时仍保持单向摘要；正式安装完成后会
        // 自动切换为实例私钥派生的 salt。
        return hash('sha256', app()->getRootPath(), true);
    }
}
