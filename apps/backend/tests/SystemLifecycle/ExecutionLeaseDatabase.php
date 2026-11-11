<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use app\modules\Operations\SystemLifecycle\Infrastructure\ThinkDbUpgradeLease;
use app\shared\Infrastructure\Persistence\SqlStatementExecutor;
use think\Container;
use think\DbManager;

// 仅使用独立临时库；连接凭据从环境读取，不输出。
$host=getenv('LC_TEST_DB_HOST') ?: '127.0.0.1';
$port=(int)(getenv('LC_TEST_DB_PORT') ?: 13307);
$user=getenv('LC_TEST_DB_USER') ?: 'root';
$password=getenv('LC_TEST_DB_PASSWORD') ?: '';
$admin=new PDO("mysql:host={$host};port={$port};charset=utf8mb4",$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$database='lovecards_e2e_lease_'.bin2hex(random_bytes(5));
$admin->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4');
$manager=static function () use ($host,$port,$user,$password,$database): DbManager {
    $db=new DbManager();
    $db->setConfig(['default'=>'mysql','connections'=>['mysql'=>[
        'type'=>'mysql','hostname'=>$host,'hostport'=>$port,'database'=>$database,
        'username'=>$user,'password'=>$password,'charset'=>'utf8mb4','prefix'=>'','break_reconnect'=>false,
    ]]]);
    return $db;
};
$bind=static fn(DbManager $db)=>Container::getInstance()->instance('think\DbManager',$db);
$assert=static function(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); };
$a=$manager();$b=$manager();
try {
    $idA=(int)$a->connect()->query('SELECT CONNECTION_ID() AS id')[0]['id'];
    $idB=(int)$b->connect()->query('SELECT CONNECTION_ID() AS id')[0]['id'];
    $assert($idA!==$idB,'fixture must use independent MySQL sessions');
    $bind($a);$first=new ThinkDbUpgradeLease();$now=new DateTimeImmutable('now',new DateTimeZone('UTC'));
    $token=$first->acquire('test.lifecycle',$now,5);
    $assert(is_string($token),'first lease must acquire');
    $assert((new ThinkDbUpgradeLease())->acquire('test.lifecycle',$now->modify('+901 seconds'),5)===null,'shared connection must not reenter through a different service instance');
    $bind($b);$second=new ThinkDbUpgradeLease();
    $assert($second->acquire('test.lifecycle',$now,5)===null,'parallel connection must not acquire');
    // 推进账本时钟超出TTL，仍须被第一会话锁拒绝；不用真实等待900秒。
    $later=$now->modify('+901 seconds');
    $assert($second->acquire('test.lifecycle',$later,5)===null,'expired ledger cannot steal live execution lock');
    $bind($a);$first->release('test.lifecycle',$token);
    $bind($b);$next=$second->acquire('test.lifecycle',$later,5);
    $assert(is_string($next),'explicit release allows next owner');
    $admin->exec('KILL CONNECTION '.$idB);
    $assert($second->renew('test.lifecycle',$next,$later,5)===false,'lost connection cannot renew ownership');
    $bind($a);
    $third=new ThinkDbUpgradeLease();
    $last=$third->acquire('test.lifecycle',$later->modify('+6 seconds'),5);
    $assert(is_string($last),'closed session releases execution lock');
    $third->release('test.lifecycle',$last);
    // 同一受审执行器重复迁移04，证明DDL完成而账本未提交时可重试。
    $a->connect()->execute('CREATE TABLE stream_tickets (ticket_hash char(64) PRIMARY KEY, uid bigint unsigned NOT NULL)');
    $sql=file_get_contents(dirname(__DIR__,2).'/database/migrations/20261111000004.sql');
    SqlStatementExecutor::execute($sql); SqlStatementExecutor::execute($sql);
    $columns=$a->connect()->query("SHOW COLUMNS FROM stream_tickets LIKE 'session_public_id'");
    $assert(count($columns)===1,'ticket migration must be idempotent');
    echo "PASS real database execution lease exclusion, connection loss and ticket migration retry\n";
} finally {
    $a->connect()->close(); $b->connect()->close();
    if (!preg_match('/^lovecards_e2e_lease_[a-f0-9]{10}$/D',$database)) throw new LogicException('Unsafe test database name');
    $admin->exec('DROP DATABASE `'.$database.'`');
}
