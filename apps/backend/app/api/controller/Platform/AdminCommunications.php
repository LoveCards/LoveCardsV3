<?php
declare(strict_types=1);

namespace app\api\controller\Platform;

use app\api\controller\BaseController;
use app\modules\Operations\Administration\Public\CommunicationsManagement;

/**
 * 通信运行时管理入口：通知策略目录的查看与 CAS 启停。
 */
final class AdminCommunications extends BaseController
{
    private function uid(): int { return (int) request()->auth->uid(); }

    public function policies() { return $this->respond(CommunicationsManagement::policies()); }

    public function applyPolicies() { return $this->respond(CommunicationsManagement::applyPolicies($this->validated(), $this->uid())); }
}
