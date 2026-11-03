<?php
declare(strict_types=1);

namespace app\shared\Observability;

/** FPM 请求结束或 Worker Job 结束时必须调用 clear()。 */
final class LogContextScope implements LogContextProvider
{
    private ?LogContext $context = null;

    public function set(LogContext $context): void { $this->context = $context; }
    public function clear(): void { $this->context = null; }
    public function current(): LogContext { return $this->context ?? LogContext::empty(); }
}
