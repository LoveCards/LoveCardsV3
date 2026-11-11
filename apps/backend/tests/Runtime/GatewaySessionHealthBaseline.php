<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use app\modules\Operations\Runtime\Infrastructure\RealtimeGatewayProbe;

// 真实 HTTP 探测旧 Gateway 与非法安全期限；仅 fixture 服务器使用临时文件。
$listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($listener === false) throw new RuntimeException($error);
$address = stream_socket_get_name($listener, false);
fclose($listener);
$dir = sys_get_temp_dir() . '/lc-gateway-session-' . bin2hex(random_bytes(5));
mkdir($dir);
file_put_contents($dir . '/router.php', '<?php header("Content-Type: application/json"); readfile(__DIR__ . "/health.json");');
$process = proc_open([PHP_BINARY, '-S', $address, $dir . '/router.php'], [0=>['pipe','r'],1=>['file',$dir.'/server.log','a'],2=>['file',$dir.'/server.log','a']], $pipes);
if (!is_resource($process)) throw new RuntimeException('Cannot launch health fixture');
fclose($pipes[0]);
try {
    for ($i=0; $i<100; $i++) {
        $socket = @stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
        if ($socket !== false) { fclose($socket); break; }
        usleep(10000);
    }
    $probe = new RealtimeGatewayProbe();
    $fetch = new ReflectionMethod($probe, 'fetchHealth');
    foreach ([null,0,-1,61,'60',true,1.5,60] as $ttl) {
        $payload = ['protocol'=>'lc-realtime/1','cursor'=>true,'auth'=>['ticket'],'ready'=>true,'transport'=>'redis','redis_config_version'=>'test'];
        if ($ttl !== null) $payload['session_ttl_max_seconds']=$ttl;
        file_put_contents($dir . '/health.json', json_encode($payload));
        $result = $fetch->invoke($probe, 'http://' . $address, 'fixture-token', 'test');
        if ($ttl === 60) {
            if ($result['ready'] !== true) throw new RuntimeException('Valid session health rejected: ' . json_encode($result));
        } elseif ($result['ready'] !== false || $result['reason_code'] !== 'gateway-protocol-mismatch') {
            throw new RuntimeException('Unsafe Gateway accepted: ' . json_encode($ttl));
        }
    }
    echo "PASS Gateway session health baseline\n";
} finally {
    proc_terminate($process);
    proc_close($process);
    foreach (['router.php','health.json','server.log'] as $file) if (is_file($dir . '/' . $file)) unlink($dir . '/' . $file);
    rmdir($dir);
}
