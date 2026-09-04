<?php

declare(strict_types=1);

namespace app\modules\Platform\Eventing\Domain;

final class DeliveryFailure
{
    private bool $terminal;
    private string $error;
    private \DateTimeImmutable $availableAt;

    public function __construct(bool $terminal, string $error, \DateTimeImmutable $availableAt)
    {
        $error = trim($error);
        if ($error === '') {
            throw new \InvalidArgumentException('Delivery failure error 不能为空');
        }
        if (strlen($error) > 2048) {
            $error = function_exists('mb_strcut')
                ? mb_strcut($error, 0, 2048, 'UTF-8')
                : substr($error, 0, 2048);
        }
        $this->terminal = $terminal;
        $this->error = $error;
        $this->availableAt = $availableAt;
    }

    public function terminal(): bool { return $this->terminal; }
    public function error(): string { return $this->error; }
    public function availableAt(): \DateTimeImmutable { return $this->availableAt; }
}
