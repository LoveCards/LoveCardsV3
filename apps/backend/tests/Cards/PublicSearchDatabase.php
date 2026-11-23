<?php
declare(strict_types=1);

$host = getenv('LC_TEST_DB_HOST');
if (!is_string($host) || $host === '') {
    echo "SKIP public search database integration (LC_TEST_DB_HOST not configured)\n";
    return;
}
if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Search integration requires a local disposable database');
}
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$database = 'lovecards_e2e_search_' . bin2hex(random_bytes(5));
$port = getenv('LC_TEST_DB_PORT') ?: '3306';
$user = getenv('LC_TEST_DB_USER') ?: 'root';
$password = getenv('LC_TEST_DB_PASSWORD') ?: '';
$pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$temporaryRoot = sys_get_temp_dir() . '/' . $database;
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$ids = static fn(array $result): array => array_map('intval', array_column($result['data'], 'id'));

try {
    $pdo->exec("USE `{$database}`");
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $pdo->exec((string) file_get_contents($root . '/database/schema.sql'));
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    foreach (['HOSTNAME' => $host, 'HOSTPORT' => $port, 'DATABASE' => $database, 'USERNAME' => $user, 'PASSWORD' => $password, 'COLLATION' => 'utf8mb4_general_ci'] as $key => $value) {
        putenv('PHP_DATABASE_' . $key . '=' . $value);
    }
    putenv('LC_INSTANCE_ROOT=' . $temporaryRoot . '/instance');
    putenv('LC_INSTANCE_ROOT_RESOLVED=' . $temporaryRoot . '/instance');
    $app = new \think\App($root);
    $app->setEnvName('search-e2e');
    $app->setRuntimePath($temporaryRoot . '/runtime/');
    $app->initialize();
    $app->config->set(['system_roles' => ['guest' => 999]], 'system');
    $assert(\think\facade\Db::query('SELECT DATABASE() AS name')[0]['name'] === $database, 'ORM connection must remain isolated');

    $insertUser = $pdo->prepare('INSERT INTO users (id,uid,number,email,phone,username,password,status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
    foreach ([1 => '城市', 2 => '城市漫步', 3 => '城市屏蔽', 4 => '城市停用', 5 => '城市私密', 6 => '城市游客'] as $id => $name) {
        $insertUser->execute([$id, '10000000000' . $id, (string) $id, '', '', $name, '', $id === 4 ? 1 : 0, '2026-01-01', '2026-01-01']);
    }
    $pdo->exec("INSERT INTO privacy_preferences (user_id,profile_visibility,updated_at) VALUES (5,'private','2026-01-01')");
    $pdo->exec("INSERT INTO roles (id,name,slug,created_at,updated_at) VALUES (999,'search_guest','search_guest','2026-01-01','2026-01-01')");
    $pdo->exec("INSERT INTO user_roles (user_id,role_id,created_at) VALUES (6,999,'2026-01-01')");
    $pdo->exec("INSERT INTO user_safety_relations (actor_id,subject_id,relation,created_at) VALUES (1,3,'block','2026-01-01')");
    $insertCard = $pdo->prepare('INSERT INTO cards (id,user_id,content,data,visibility,status,deleted_at,created_at) VALUES (?,?,?,?,?,?,?,?)');
    $cards = [
        [1, 2, '城市起点', null, 'public', 0, null, '2026-01-01'],
        [2, 2, '我记得城市的灯', null, 'public', 0, null, '2026-01-10'],
        [3, 2, '傍晚的照片', null, 'public', 0, null, '2026-01-12'],
        [4, 2, '', '{"title":"城市旧版"}', 'public', 0, null, '2025-01-01'],
        [5, 1, '城市私密', null, 'private', 0, null, '2026-02-01'],
        [6, 2, '城市关注者', null, 'followers', 0, null, '2026-02-01'],
        [7, 2, '城市隐藏', null, 'public', 2, null, '2026-02-01'],
        [8, 2, '城市删除', null, 'public', 0, '2026-02-02', '2026-02-01'],
        [9, 4, '城市停用作者', null, 'public', 0, null, '2026-02-01'],
        [10, 3, '城市被屏蔽作者', null, 'public', 0, null, '2026-02-01'],
        [11, 2, 'literal %_ value', null, 'public', 0, null, '2026-01-01'],
    ];
    foreach ($cards as $card) $insertCard->execute($card);
    $pdo->exec("INSERT INTO tags (id,aid,name,status) VALUES (1,1,'城市',0),(2,1,'城市漫步',0),(3,1,'我的城市',0),(4,1,'猫',0),(5,1,'秘密',2)");
    $pdo->exec('INSERT INTO tags_map (aid,pid,tag_id) VALUES (1,1,1),(1,3,1),(1,3,4),(1,3,5)');

    $params = ['search_value' => '城市', 'list_rows' => 30];
    $match = \app\modules\Content\Cards\Service\Cards::searchPublic($params, 1);
    $assert($ids($match) === [1, 4, 2, 3], 'public search must rank legacy text and exclude non-public, deleted, disabled and blocked records');
    $latest = \app\modules\Content\Cards\Service\Cards::searchPublic($params + ['sort' => 'latest'], 1);
    $assert($ids($latest) === [3, 2, 1, 4], 'latest sort must apply before pagination');
    $page1 = \app\modules\Content\Cards\Service\Cards::searchPublic(['search_value' => '城市', 'page' => 1, 'list_rows' => 2], 1);
    $page2 = \app\modules\Content\Cards\Service\Cards::searchPublic(['search_value' => '城市', 'page' => 2, 'list_rows' => 2], 1);
    $assert(array_merge($ids($page1), $ids($page2)) === [1, 4, 2, 3] && $page1['total'] === 4, 'count and cross-page rank must use the same public scope');
    $topic = \app\modules\Content\Cards\Service\Cards::searchPublic(['search_value' => '#猫'], 1);
    $assert($ids($topic) === [3], 'single-character exact topics must find related cards');
    $assert($ids(\app\modules\Content\Cards\Service\Cards::searchPublic(['search_value' => '#秘密'], 1)) === [], 'hidden topic names must not expose associations');
    $assert($ids(\app\modules\Content\Cards\Service\Cards::searchPublic(['search_value' => '%_'], 1)) === [11], 'SQL wildcard characters must remain literal');
    $pdo->exec("DELETE FROM user_safety_relations WHERE actor_id=1 AND subject_id=3");
    $pdo->exec("INSERT INTO user_safety_relations (actor_id,subject_id,relation,created_at) VALUES (3,1,'block','2026-01-01')");
    $assert($ids(\app\modules\Content\Cards\Service\Cards::searchPublic($params, 1)) === [1, 4, 2, 3], 'reverse block must also remove results');
    $anonymous = \app\modules\Content\Cards\Service\Cards::searchPublic($params);
    $assert($anonymous['total'] === 5, 'anonymous public search must still exclude private and follower-only cards');
    $users = \app\modules\Platform\User\Service\PublicUserSearch::search($params, 1);
    $assert(array_column($users['data'], 'username') === ['城市', '城市漫步'], 'user ranking and privacy must precede pagination');
    $topics = \app\modules\Content\Tags\Service\Tags::searchPublic($params);
    $assert(array_column($topics['data'], 'name') === ['城市', '城市漫步', '我的城市'], 'topic exact, prefix and contains ranking must be stable');
    echo "PASS public search real database visibility, matching, literal wildcards, ranking and pagination\n";

    $benchmarkRows = (int) (getenv('LC_SEARCH_BENCH_ROWS') ?: 0);
    if ($benchmarkRows > 0 && $benchmarkRows <= 50000) {
        $insert = $pdo->prepare("INSERT INTO cards (id,user_id,content,visibility,status,created_at) VALUES (?,2,?,'public',0,'2026-01-01')");
        $inserted = 0;
        foreach ([$benchmarkRows, $benchmarkRows * 2] as $size) {
            $pdo->beginTransaction();
            for (; $inserted < $size; $inserted++) {
                $text = $inserted % 5 === 0 ? '城市漫步与周末照片' : '今天写下一段普通生活记录';
                $insert->execute([$inserted + 100, $text]);
            }
            $pdo->commit();
            foreach (['城市', '生活', '完全不存在的关键词'] as $term) {
                $timings = [];
                for ($sample = 0; $sample < 20; $sample++) {
                    $started = hrtime(true);
                    \app\modules\Content\Cards\Service\Cards::searchPublic(['search_value' => $term, 'list_rows' => 24], 1);
                    $timings[] = (hrtime(true) - $started) / 1000000;
                }
                sort($timings);
                echo 'SEARCH_BENCH ', json_encode(['rows' => $size + count($cards), 'case' => $term === '城市' ? 'common' : ($term === '生活' ? 'frequent' : 'empty'),
                    'samples' => 20, 'concurrency' => 1, 'service_p95_ms' => round($timings[18], 2)], JSON_UNESCAPED_SLASHES), PHP_EOL;
            }
        }
    }
} finally {
    if (!preg_match('/^lovecards_e2e_search_[a-f0-9]{10}$/D', $database)) throw new LogicException('Unsafe test database name');
    $pdo->exec('DROP DATABASE `' . $database . '`');
}
