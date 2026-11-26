<?php
declare(strict_types=1);

namespace app\modules\Theme\Application;

/** 文件变更与审计不能共享事务，错误响应必须保留可核对的结果。 */
final class ThemeOperationException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $operationId,
        public readonly string $reasonCode,
        public readonly string $sideEffects,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function details(): array
    {
        return ['operation_id' => $this->operationId, 'reason_code' => $this->reasonCode, 'side_effects' => $this->sideEffects];
    }
}
