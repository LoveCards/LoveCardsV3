<?php
declare(strict_types=1);

namespace app\command;

use app\modules\Operations\SystemLifecycle\Application\UpgradeCoordinator;
use app\shared\Observability\AuditLogger;
use think\console\Command;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Input;
use think\console\Output;

final class SystemUpgrade extends Command
{
    protected function configure(): void
    {
        $this->setName('lc:system:upgrade')->setDescription('Create or resume a durable LoveCards Release upgrade')
            ->addArgument('action', Argument::OPTIONAL, 'create|advance|run|approve|recover', 'run')
            ->addArgument('value', Argument::OPTIONAL, 'ZIP path or Upgrade Plan ID', '')
            ->addOption('contract', 'c', null, 'Allow explicitly approved contract migration work')
            ->addOption('max-advances', null, Option::VALUE_OPTIONAL, 'Maximum plan advances', '1000');
    }

    protected function execute(Input $input, Output $output): int
    {
        $action = strtolower((string) $input->getArgument('action'));
        $value = (string) $input->getArgument('value');
        $coordinator = app(UpgradeCoordinator::class);
        $allowContract = (bool) $input->getOption('contract');
        if ($action === 'advance' && $allowContract) {
            throw new \InvalidArgumentException('advance 不接受提前 Contract 授权，请在等待 Gate 后执行 approve');
        }

        // CLI 由部署权限授权，但与 HTTP 一样先记录实际计划/迁移身份；审计失败禁止推进。
        $beforeAdvance = static function (array $metadata) use ($action): void {
            app(AuditLogger::class)->record(null, 'system.upgrade.advance.requested', 'system_upgrade',
                (string) $metadata['plan_id'], $metadata + ['source' => 'cli', 'command_action' => $action]);
        };

        $result = match ($action) {
            'create' => $coordinator->createPlan($value),
            'advance' => $coordinator->advance($value, $beforeAdvance),
            'run' => $coordinator->runUntilSettled($value, $allowContract, max(1, min(10000, (int) $input->getOption('max-advances'))), $beforeAdvance),
            'approve' => $coordinator->approveContract($value),
            'recover' => $coordinator->recover($value),
            default => throw new \InvalidArgumentException('Unknown system upgrade action: ' . $action),
        };
        $data = is_object($result) && method_exists($result, 'toArray') ? $result->toArray() : $result;
        $output->writeln(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        return 0;
    }
}
