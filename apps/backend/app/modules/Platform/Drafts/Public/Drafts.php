<?php
declare(strict_types=1);
namespace app\modules\Platform\Drafts\Public;
use app\modules\Platform\Drafts\Application\ManageDrafts;
use app\modules\Platform\Drafts\Contract\DraftRepository;
final class Drafts { private static function u():ManageDrafts{return new ManageDrafts(app(DraftRepository::class));} public static function save(int $u,?int $id,string $t,string $c,array $p=[]):int{return self::u()->save($u,$id,$t,$c,$p);} public static function find(int $u,int $id):?array{return self::u()->find($u,$id);} public static function list(int $u,?string $c=null,int $l=20):array{return self::u()->list($u,$c,$l);} public static function delete(int $u,int $id):bool{return self::u()->delete($u,$id);} }
