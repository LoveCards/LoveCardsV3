<?php
declare(strict_types=1);
namespace app\api\controller\Platform;
use app\api\controller\BaseController;
use app\modules\Platform\Auth\Application\AccountRecovery;
final class Recovery extends BaseController {
    private function service(): AccountRecovery { return app(AccountRecovery::class); }
    public function issue(){ $p=$this->validated(); return $this->respond($this->service()->issue((string)($p['account']??''), $this->ip())); }
    public function reset(){ $p=$this->validated(); $this->service()->reset((int)$p['challenge_id'],(string)$p['code'],(string)$p['password'], $this->ip()); return $this->respond(); }
}
