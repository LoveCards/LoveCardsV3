<?php
declare(strict_types=1);

namespace app\api\controller\Platform;

use app\api\ApiException;
use app\api\controller\BaseController;
use app\modules\Platform\Notifications\Public\AdminNotifications;

final class Notifications extends BaseController
{
    public function index()
    {
        return $this->respond(AdminNotifications::paginate($this->validated()));
    }

    public function get(string $id)
    {
        $parsedId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($parsedId === false) throw ApiException::notFound('站内通知不存在');
        $notification = AdminNotifications::get($parsedId);
        if ($notification === null) throw ApiException::notFound('站内通知不存在');
        return $this->respond($notification);
    }
}
