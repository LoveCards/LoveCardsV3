import { access } from 'node:fs/promises'
import { execFile, spawn } from 'node:child_process'
import { promisify } from 'node:util'
import net from 'node:net'
import path from 'node:path'
import process from 'node:process'
import { fileURLToPath } from 'node:url'
import { phpUploadIniArgs, phpUploadIniSummary } from './lib/php-upload-limits.mjs'
import { MIN_SUPPORTED_PHP_VERSION_ID, MAX_SUPPORTED_PHP_VERSION_ID, parsePhpVersionId } from './lib/php-runtime.mjs'

const execFileAsync = promisify(execFile)
const root = path.resolve(fileURLToPath(new URL('..', import.meta.url)))
const host = process.env.LOVECARDS_DEV_BACKEND_HOST || '127.0.0.1'
const port = Number(process.env.LOVECARDS_DEV_BACKEND_PORT || 6001)

function isListening() {
  return new Promise((resolve) => {
    const socket = net.createConnection({ host, port })
    socket.once('connect', () => {
      socket.destroy()
      resolve(true)
    })
    socket.once('error', () => resolve(false))
  })
}

async function findPhp() {
  const candidates = [
    process.env.PHP_BINARY,
    'php',
    'C:\\software\\phpstudy_pro\\Extensions\\php\\php8.2.0nts\\php.exe',
    'C:\\software\\phpstudy_pro\\Extensions\\php\\php8.0.2nts\\php.exe',
    'C:\\Program Files\\phpstudy_pro\\Extensions\\php\\php8.2.0nts\\php.exe',
    'C:\\Program Files\\phpstudy_pro\\Extensions\\php\\php8.0.2nts\\php.exe',
  ].filter(Boolean)

  for (const candidate of candidates) {
    try {
      if (candidate !== 'php') await access(candidate)
      const { stdout } = await execFileAsync(candidate, ['-r', 'echo PHP_VERSION_ID;'])
      const versionId = parsePhpVersionId(stdout)
      if (versionId !== null && versionId >= MIN_SUPPORTED_PHP_VERSION_ID && versionId < MAX_SUPPORTED_PHP_VERSION_ID) return candidate
      if (process.env.LOVECARDS_ALLOW_UNSUPPORTED_PHP === '1' && versionId !== null && versionId >= 80000 && versionId < MAX_SUPPORTED_PHP_VERSION_ID) return candidate
    } catch {
      // Try the next configured PHP binary.
    }
  }

  throw new Error('未找到 PHP 8.1+。请设置 PHP_BINARY，或临时设置 LOVECARDS_ALLOW_UNSUPPORTED_PHP=1。')
}

if (await isListening()) {
  console.log(`Backend already listening at http://${host}:${port}`)
  setInterval(() => {}, 1000)
} else {
  const php = await findPhp()
  const phpIniArgs = phpUploadIniArgs(process.env)
  const phpIniSummary = phpUploadIniSummary(process.env)
  console.log(`Backend PHP upload limits: post_max_size=${phpIniSummary.post_max_size}, upload_max_filesize=${phpIniSummary.upload_max_filesize}`)
  const backendRoot = path.join(root, 'apps', 'backend')
  const publicRoot = path.join(backendRoot, 'public')
  // ThinkPHP's `think run` command starts a second, bare `php -S` process.
  // Startup `-d` directives would be lost at that boundary, so launch the
  // built-in server directly with the selected PHP binary. router.php still
  // enters public/index.php, where the immutable Release pointer is consumed.
  const child = spawn(php, [
    ...phpIniArgs,
    '-S', `${host}:${port}`,
    '-t', publicRoot,
    path.join(publicRoot, 'router.php'),
  ], {
    cwd: backendRoot,
    env: {
      ...process.env,
      PATH: `${path.dirname(php)}${process.platform === 'win32' ? ';' : ':'}${process.env.PATH || ''}`,
    },
    stdio: 'inherit',
    windowsHide: true,
  })

  const stop = () => {
    if (!child.killed) child.kill()
  }
  process.once('SIGINT', stop)
  process.once('SIGTERM', stop)
  child.once('exit', (code, signal) => process.exit(code ?? (signal ? 1 : 0)))
}
