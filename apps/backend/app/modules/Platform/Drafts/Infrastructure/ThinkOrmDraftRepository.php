<?php
declare(strict_types=1);
namespace app\modules\Platform\Drafts\Infrastructure;
use app\modules\Platform\Drafts\Contract\DraftRepository;
use think\facade\Db;
final class ThinkOrmDraftRepository implements DraftRepository {
 public function save(int $u,?int $id,string $t,string $c,array $p=[]):int { $now=date('Y-m-d H:i:s'); $title= function_exists('mb_substr') ? mb_substr(trim($t),0,255) : substr(trim($t),0,255); $data=['user_id'=>$u,'title'=>$title,'content'=>$c,'payload'=>json_encode($p,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'updated_at'=>$now]; if($id){$n=Db::table('card_drafts')->where('id',$id)->where('user_id',$u)->whereNull('deleted_at')->update($data); if($n!==1) throw new \RuntimeException('草稿不存在'); return $id;} $data['created_at']=$now; return (int)Db::table('card_drafts')->insertGetId($data); }
 public function find(int $u,int $id):?array{$r=Db::table('card_drafts')->where('id',$id)->where('user_id',$u)->whereNull('deleted_at')->find(); return $r?:null;}
 public function list(int $u,?string $c=null,int $l=20):array{$q=Db::table('card_drafts')->where('user_id',$u)->whereNull('deleted_at')->order('updated_at','desc')->order('id','desc')->limit(min(100,max(1,$l))); if($c!==null){$q->where('id','<',(int)$c);} return $q->select()->toArray();}
 public function delete(int $u,int $id):bool{return Db::table('card_drafts')->where('id',$id)->where('user_id',$u)->whereNull('deleted_at')->update(['deleted_at'=>date('Y-m-d H:i:s')])===1;}
}
