<?php

namespace app\modules\Operations\Dashboard\Service;

use think\facade\Db;
use app\shared\Infrastructure\Cache\CacheManager;
use app\modules\Operations\Version\Public\Version;

use app\modules\Content\Cards\Public\Cards;
use app\modules\Content\Comments\Public\Comments;
use app\modules\Content\Likes\Public\Likes;
use app\modules\Operations\Dashboard\Domain\WorkbenchPolicy;
use app\modules\Platform\Files\Domain\FileConstants;
use app\shared\Authorization\GrantSet;

class Dashboard
{
    public static function index(GrantSet $grants): array
    {
        $notice = self::getNotice();

        [$count, $chart] = self::summaries($grants);

        return [
            'count' => $count,
            'chart' => $chart,
            'ver' => Version::current(),
            'notice' => $notice,
            'workbench' => [
                'tasks' => self::tasks($grants),
            ],
        ];
    }

    /**
     * 仪表盘汇总同样按 capability 裁剪，避免只在前端隐藏未授权数据。
     *
     * @return array{0:array<string,int>,1:array<int,array{label:string,data:array}>}
     */
    private static function summaries(GrantSet $grants): array
    {
        $count = [];
        $chart = [];
        foreach (WorkbenchPolicy::summaryKeys($grants) as $key) {
            if ($key === 'cards') {
                $count['cards'] = Cards::count();
                $chart[] = ['label' => '卡片', 'data' => self::getChartData('cards')];
            } elseif ($key === 'comments') {
                $count['comments'] = Comments::count();
                $chart[] = ['label' => '评论', 'data' => self::getChartData('comments')];
            } elseif ($key === 'good') {
                $count['good'] = Likes::count();
                $chart[] = ['label' => '点赞', 'data' => self::getChartData('likes')];
            }
        }
        return [$count, $chart];
    }

    /**
     * 只返回当前操作者具备处理能力的待办。角色名不参与授权判断。
     *
     * @return array<int,array{key:string,label:string,description:string,count:int,action_url:string,severity:string}>
     */
    private static function tasks(GrantSet $grants): array
    {
        $tasks = [];
        $allowed = WorkbenchPolicy::taskKeys($grants);
        if (in_array('cards_pending', $allowed, true)) {
            $tasks[] = self::task(
                'cards_pending',
                '待审核卡片',
                '检查等待发布的卡片内容。',
                self::countWhere('cards', static fn ($query) => $query->where('status', Cards::pendingStatus())->whereNull('deleted_at')),
                '/apps/content/cards?filters=%7B%22status%22%3A3%7D',
                'warning'
            );
        }
        if (in_array('comments_pending', $allowed, true)) {
            $tasks[] = self::task(
                'comments_pending',
                '待审核评论',
                '处理审核队列中的评论。',
                self::countWhere('comments', static fn ($query) => $query->where('status', 3)->whereNull('deleted_at')),
                '/apps/content/comments?filters=%7B%22status%22%3A3%7D',
                'warning'
            );
        }
        if (in_array('reports_open', $allowed, true)) {
            $tasks[] = self::task(
                'reports_open',
                '待处理举报',
                '跟进尚未关闭的社区举报。',
                self::countWhere('reports', static fn ($query) => $query->whereIn('status', ['open', 'reviewing'])),
                '/apps/community/reports',
                'warning'
            );
        }
        if (in_array('deliveries_dead', $allowed, true)) {
            $tasks[] = self::task(
                'deliveries_dead',
                '失败投递',
                '检查死信并在确认原因后重试。',
                self::countWhere('event_deliveries', static fn ($query) => $query->where('status', 'dead')),
                '/apps/operations/deliveries',
                'error'
            );
        }
        if (in_array('files_pending', $allowed, true)) {
            $tasks[] = self::task(
                'files_pending',
                '待处理文件',
                '检查仍处于上传或处理中的文件。',
                self::countWhere('files', static fn ($query) => $query->where('upload_status', FileConstants::UPLOAD_PENDING)->whereNull('deleted_at')),
                '/apps/settings/storage/files?filters=%7B%22upload_status%22%3A0%7D',
                'info'
            );
        }
        return $tasks;
    }

    private static function countWhere(string $table, callable $filter): int
    {
        $query = Db::table($table);
        $filter($query);
        return (int) $query->count();
    }

    private static function task(
        string $key,
        string $label,
        string $description,
        int $count,
        string $actionUrl,
        string $severity
    ): array {
        return compact('key', 'label', 'description', 'count', 'severity') + ['action_url' => $actionUrl];
    }

    private static function getNotice(): array
    {
        return CacheManager::get('system', 'Notice', function () {
            $url = 'https://server.lovecards.cn/apiv1/Notice';
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 5,
                    'header' => "User-Agent: PHP\r\n",
                ],
            ]);

            $jsonStr = @file_get_contents($url, false, $ctx);
            if ($jsonStr === false) {
                return [];
            }

            $json = json_decode($jsonStr, true);
            if (!isset($json['ec']) || $json['ec'] !== 200 || !isset($json['data'])) {
                return [];
            }

            return $json['data'];
        }, CacheManager::TTL_LONG * 3);
    }

    private static function getChartData(string $table): array
    {
        for ($i = 1; $i <= 6; $i++) {
            $time = date('Y-m-d', strtotime('-' . $i . 'day'));
            $arr['y'][$i] = Db::table($table)->whereDay('created_at', $time)->count();
            $arr['x'][$i] = $time;
            if ($i == 1) $arr['x'][$i] = '昨日';
        }
        $arr['y'] = array_reverse($arr['y']);
        $arr['x'] = array_reverse($arr['x']);
        return $arr;
    }
}
