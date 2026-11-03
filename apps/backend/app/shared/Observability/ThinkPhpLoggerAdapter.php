<?php
declare(strict_types=1);

namespace app\shared\Observability;

use Psr\Log\LoggerInterface;

/**
 * 业务层依赖 PSR LoggerInterface；组合根注入 think\Log 实例，禁止业务依赖 Facade。
 */
final class ThinkPhpLoggerAdapter implements LoggerInterface
{
    private LoggerInterface $logger;
    private LogContextProvider $contextProvider;
    private SensitiveDataSanitizer $sanitizer;

    public function __construct(
        LoggerInterface $logger,
        LogContextProvider $contextProvider,
        SensitiveDataSanitizer $sanitizer
    ) {
        $this->logger = $logger;
        $this->contextProvider = $contextProvider;
        $this->sanitizer = $sanitizer;
    }

    public function emergency($message, array $context = []) { $this->log('emergency', $message, $context); }
    public function alert($message, array $context = []) { $this->log('alert', $message, $context); }
    public function critical($message, array $context = []) { $this->log('critical', $message, $context); }
    public function error($message, array $context = []) { $this->log('error', $message, $context); }
    public function warning($message, array $context = []) { $this->log('warning', $message, $context); }
    public function notice($message, array $context = []) { $this->log('notice', $message, $context); }
    public function info($message, array $context = []) { $this->log('info', $message, $context); }
    public function debug($message, array $context = []) { $this->log('debug', $message, $context); }

    public function log($level, $message, array $context = [])
    {
        $safeContext = $this->sanitizer->sanitizeContext($context);
        unset($safeContext['request_id'], $safeContext['correlation_id']);
        $safeContext = array_merge($safeContext, $this->contextProvider->current()->toArray());
        $safeMessage = $this->sanitizer->sanitizeText($this->stringifyMessage($message));
        try {
            $this->logger->log($level, $safeMessage, $safeContext);
        } catch (\Throwable $ignored) {
            // 技术日志不可反向破坏业务流程；渠道故障由运行状态检查报告。
        }
    }

    private function stringifyMessage($message): string
    {
        if (is_string($message)) return $message;
        if (is_scalar($message) || $message === null) return (string) $message;
        if ($message instanceof \Stringable) return (string) $message;
        return '[UNSUPPORTED LOG MESSAGE]';
    }
}
