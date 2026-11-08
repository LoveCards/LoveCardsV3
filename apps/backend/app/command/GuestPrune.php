<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

/**
 * 访客账号清理：新访客机制每次铸造一次性身份，长期闲置后由本命令级联回收
 * （会话、草稿等经外键 ON DELETE CASCADE 一并清除）。
 * 只删除访客角色下闲置超过天数的账号，绝不触碰普通注册账号。
 */
final class GuestPrune extends Command
{
    protected function configure(): void
    {
        $this->setName('lc:guest:prune')
            ->addOption('days', 'd', Option::VALUE_OPTIONAL, '闲置天数阈值', '30')
            ->setDescription('Prune idle guest-role accounts with cascaded sessions and drafts');
    }

    /**
     * 清理条件导出为纯函数，保证角色过滤永远存在（测试直接断言该结构）。
     *
     * @return array{role: int, before: string}
     */
    public static function conditions(int $days, int $guestRoleId): array
    {
        if ($days < 1) {
            throw new \InvalidArgumentException('days must be positive');
        }
        if ($guestRoleId < 1) {
            throw new \InvalidArgumentException('guest role anchor is missing');
        }

        return [
            'role' => $guestRoleId,
            'before' => (new \DateTimeImmutable("-{$days} days"))->format('Y-m-d H:i:s'),
        ];
    }

    protected function execute(Input $input, Output $output): int
    {
        $days = (int) $input->getOption('days');
        $conditions = self::conditions($days, (int) config('system.system_roles.guest'));

        // 角色真源是 user_roles 关联表（users.roles_id 为遗留列，现网恒为 NULL）。
        $ids = Db::table('users')
            ->whereNull('deleted_at')
            ->where('created_at', '<', $conditions['before'])
            ->whereIn('id', function ($query) use ($conditions) {
                $query->table('user_roles')
                    ->where('role_id', $conditions['role'])
                    ->field('user_id');
            })
            ->column('id');

        $deleted = 0;
        foreach (array_chunk($ids, 500) as $chunk) {
            Db::startTrans();
            try {
                Db::table('user_roles')->whereIn('user_id', $chunk)->delete();
                // auth_sessions、草稿等经外键 ON DELETE CASCADE 一并清除。
                $deleted += Db::table('users')->whereIn('id', $chunk)->delete();
                Db::commit();
            } catch (\Throwable $exception) {
                Db::rollback();
                throw $exception;
            }
        }

        $output->writeln('pruned guest accounts: ' . $deleted);
        return 0;
    }
}
