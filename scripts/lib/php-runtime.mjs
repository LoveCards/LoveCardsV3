import { spawnSync } from 'node:child_process'

export const MIN_SUPPORTED_PHP_VERSION_ID = 80100
export const MAX_SUPPORTED_PHP_VERSION_ID = 90000

export function parsePhpVersionId(output) {
  const value = String(output ?? '').trim()
  if (!/^\d+$/.test(value)) return null
  const versionId = Number(value)
  return Number.isSafeInteger(versionId) ? versionId : null
}

export function resolveSupportedPhp({ binary = process.env.PHP_BINARY || 'php', spawn = spawnSync } = {}) {
  const selected = String(binary).trim()
  if (!selected) throw new Error('PHP_BINARY must not be empty.')

  const result = spawn(selected, ['-r', 'echo PHP_VERSION_ID;'], { encoding: 'utf8' })
  if (result.error) {
    throw new Error(`Unable to run PHP binary "${selected}". Install PHP 8.1–8.x or set PHP_BINARY: ${result.error.message}`)
  }
  if (result.status !== 0) {
    const detail = result.stderr?.trim() || result.stdout?.trim() || `exit ${result.status}`
    throw new Error(`Unable to query PHP binary "${selected}": ${detail}`)
  }

  const versionId = parsePhpVersionId(result.stdout)
  if (versionId === null) {
    throw new Error(`PHP binary "${selected}" returned an invalid PHP_VERSION_ID: ${result.stdout?.trim() || 'empty output'}`)
  }
  if (versionId < MIN_SUPPORTED_PHP_VERSION_ID || versionId >= MAX_SUPPORTED_PHP_VERSION_ID) {
    throw new Error(`PHP 8.1–8.x is required; binary "${selected}" reported PHP_VERSION_ID ${versionId}.`)
  }

  return { binary: selected, versionId }
}
