import { access } from 'node:fs/promises'
import { execFile, spawn } from 'node:child_process'
import { promisify } from 'node:util'
import net from 'node:net'
import path from 'node:path'
import process from 'node:process'
import { fileURLToPath } from 'node:url'

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
      const { stdout } = await execFileAsync(candidate, ['-r', 'echo PHP_VERSION;'])
      const version = String(stdout).trim()
      if (version && (version.startsWith('8.1.') || version.startsWith('8.2.') || version.startsWith('8.3.') || version.startsWith('8.4.'))) {
        return candidate
      }
      if (process.env.LOVECARDS_ALLOW_UNSUPPORTED_PHP === '1' && version.startsWith('8.')) {
        return candidate
      }
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
  const child = spawn(php, ['think', 'run', '--host', host, '--port', String(port)], {
    cwd: path.join(root, 'apps', 'backend'),
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
