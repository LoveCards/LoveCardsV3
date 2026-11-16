<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Public;

/**
 * 事件队列的治理目录。
 *
 * 队列按处理副作用和运行依赖分类，不按业务模块一一对应，也不表示资源优先级。
 * 新模块接入前必须先在这里登记队列语义、允许的执行模式和基础设施依赖。
 */
final class QueueCatalog
{
    /** @var array<string, array{owner: string, purpose: string, boundary: string, dependencies: string[], execution_modes: string[]}> */
    private const DEFINITIONS = [
        'notifications' => [
            'owner' => 'Platform/Notifications',
            'purpose' => '生成或更新站内通知记录',
            'boundary' => '短事务型业务副作用',
            'dependencies' => ['mysql'],
            'execution_modes' => [ExecutionMode::REQUEST_SAFE],
        ],
        'realtime' => [
            'owner' => 'Platform/Eventing',
            'purpose' => '向浏览器发送不含业务正文的主动变化提示',
            'boundary' => '外部实时传输与信号资格',
            'dependencies' => ['mysql', 'redis', 'realtime-gateway'],
            'execution_modes' => [ExecutionMode::DAEMON_REQUIRED],
        ],
        'storage' => [
            'owner' => 'Platform/Files',
            'purpose' => '执行文件或对象存储生命周期清理',
            'boundary' => '后台资源操作与长退避重试',
            'dependencies' => ['mysql', 'storage-driver'],
            'execution_modes' => [ExecutionMode::DAEMON_REQUIRED],
        ],
    ];

    /** @return array<string, array{owner: string, purpose: string, boundary: string, dependencies: string[], execution_modes: string[]}> */
    public static function definitions(): array
    {
        return self::DEFINITIONS;
    }

    /** @return string[] */
    public static function names(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    /**
     * 校验 Handler 的队列与执行模式组合。
     *
     * 队列目录是有意的白名单；不能通过拼写一个新 queue_name 绕过治理审查。
     */
    public static function assertCompatible(string $queueName, string $executionMode): void
    {
        $definition = self::DEFINITIONS[$queueName] ?? null;
        if ($definition === null) {
            throw new \InvalidArgumentException("未登记的 Event Queue：{$queueName}");
        }
        if (!in_array($executionMode, $definition['execution_modes'], true)) {
            throw new \InvalidArgumentException("Event Queue {$queueName} 不允许执行模式：{$executionMode}");
        }
    }
}
