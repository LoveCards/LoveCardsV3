<?php
declare(strict_types=1);

namespace app\modules\Operations\Runtime\Contract;

/**
 * 能力探测端口。probe 不得抛异常逃逸，失败必须转成 reason_code。
 *
 * @return array{ready:bool|null,provider:?string,reason_code:?string,note:?array}
 */
interface RuntimeProbe
{
    public function key(): string;

    /** @param array<string,mixed> $settings 归一化后的运行参数 */
    public function probe(array $settings): array;
}
