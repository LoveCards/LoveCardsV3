<?php
declare(strict_types=1);

namespace app\modules\Operations\SystemLifecycle\Exception;

/** 外部进程是否结束尚不可证明，禁止以失败重试重放副作用。 */
final class ExecutionUncertainException extends \RuntimeException {}
