<?php
declare(strict_types=1);
namespace app\modules\Platform\Drafts\Application;
use app\modules\Platform\Drafts\Contract\DraftRepository;
final class ManageDrafts { public function __construct(private DraftRepository $repository) {} public function save(int $u, ?int $id, string $t, string $c, array $p=[]): int { return $this->repository->save($u,$id,$t,$c,$p); } public function find(int $u,int $id): ?array{return $this->repository->find($u,$id);} public function list(int $u,?string $c=null,int $l=20):array{return $this->repository->list($u,$c,$l);} public function delete(int $u,int $id):bool{return $this->repository->delete($u,$id);} }
