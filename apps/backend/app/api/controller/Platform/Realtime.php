<?php
declare(strict_types=1);
namespace app\api\controller\Platform;
use app\api\controller\BaseController;
use app\modules\Platform\Notifications\Public\Notifications;
final class Realtime extends BaseController {
 public function stream(){ $uid=(int)request()->auth->uid(); $params=$this->validated(); $last=(int)($params['after']??0); $page=Notifications::list($uid,null,20,false); $items=array_values(array_filter($page['data']??[],static fn($row)=>(int)($row['id']??0)>$last)); $payload='event: notifications\n'.'data: '.json_encode(['items'=>$items,'next_cursor'=>$page['next_cursor']??null],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'\n\n'; return response($payload,200,['Content-Type'=>'text/event-stream; charset=utf-8','Cache-Control'=>'no-cache','X-Accel-Buffering'=>'no']); }
}
