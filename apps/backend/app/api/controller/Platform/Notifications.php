<?php
declare(strict_types=1);

namespace app\api\controller\Platform;

use app\api\ApiException;
use app\api\controller\BaseController;
use app\modules\Platform\Notifications\Public\SystemNoticeBatches;
use app\modules\Platform\Rbac\Public\Authorization;

final class Notifications extends BaseController
{
    public function recipientCandidates()
    {
        $input = $this->validated();
        return $this->respond(SystemNoticeBatches::candidates($input['query'], (int) ($input['limit'] ?? 20)));
    }

    public function prepareBatch()
    {
        $input = $this->validated();
        $auth = request()->auth;
        return $this->respond(SystemNoticeBatches::prepare($input, $auth->uid(), Authorization::freshGrants($auth->roleIds()), request()->ip()));
    }

    public function latestBatch()
    {
        $input = $this->validated();
        return $this->respond(SystemNoticeBatches::latest(request()->auth->uid(), $input['request_key'] ?? null));
    }

    public function getBatch(string $id)
    {
        return $this->respond(SystemNoticeBatches::get($this->batchId($id), request()->auth->uid()));
    }

    public function confirmBatch(string $id)
    {
        $this->validated(null, ['id' => $id]);
        $auth = request()->auth;
        return $this->respond(SystemNoticeBatches::confirm($this->batchId($id), $auth->uid(), Authorization::freshGrants($auth->roleIds()), request()->ip()));
    }

    public function retryBatch(string $id)
    {
        $input = $this->validated(null, ['id' => $id]);
        $auth = request()->auth;
        return $this->respond(SystemNoticeBatches::retry($this->batchId($id), $auth->uid(), Authorization::freshGrants($auth->roleIds()), $input, request()->ip()));
    }

    private function batchId(string $id): int
    {
        $parsed = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($parsed === false) throw ApiException::notFound('系统通知批次不存在');
        return $parsed;
    }
}
