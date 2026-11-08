import { spawn } from 'node:child_process'

/**
 * 读取服务器的 utf8mb4 默认排序规则，不连接测试库。
 * MySQL 5.7 通常返回 utf8mb4_general_ci，MySQL 8 通常返回
 * utf8mb4_0900_ai_ci。把这个值传给隔离 PHP 进程，避免迁移时将
 * mysqlnd 的连接默认值与表或数据库排序规则混用。
 */
export async function detectUtf8mb4Collation(php, env, spawnProcess = spawn) {
  const explicit = String(env.PHP_DATABASE_COLLATION || process.env.PHP_DATABASE_COLLATION || '').trim()
  if (explicit) {
    if (!/^[A-Za-z0-9_]+$/.test(explicit)) throw new Error('PHP_DATABASE_COLLATION is invalid')
    return explicit
  }

  const script = [
    '$pdo=new PDO("mysql:host=".getenv("DATABASE_HOSTNAME").";port=".getenv("DATABASE_HOSTPORT"),(string)getenv("DATABASE_USERNAME"),(string)getenv("DATABASE_PASSWORD"),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);',
    '$value=$pdo->query("SELECT COLLATION_NAME FROM information_schema.COLLATIONS WHERE CHARACTER_SET_NAME=\'utf8mb4\' AND IS_DEFAULT=\'Yes\' LIMIT 1")->fetchColumn();',
    'if(!is_string($value)||$value===\'\'||preg_match("/^[A-Za-z0-9_]+$/",$value)!==1){fwrite(STDERR,"Unable to detect utf8mb4 collation\\n");exit(1);}echo $value;',
  ].join('')

  return await new Promise((resolve, reject) => {
    const child = spawnProcess(php, ['-r', script], {
      env: { ...process.env, ...env },
      stdio: ['ignore', 'pipe', 'pipe'],
      windowsHide: true,
    })
    let stdout = ''
    let stderr = ''
    child.stdout.setEncoding('utf8')
    child.stderr.setEncoding('utf8')
    child.stdout.on('data', (chunk) => { stdout += chunk })
    child.stderr.on('data', (chunk) => { stderr += chunk })
    child.once('error', reject)
    child.once('exit', (code) => {
      const value = stdout.trim()
      if (code === 0 && value) return resolve(value)
      reject(new Error(`Unable to detect utf8mb4 collation${stderr.trim() ? `: ${stderr.trim()}` : ''}`))
    })
  })
}
