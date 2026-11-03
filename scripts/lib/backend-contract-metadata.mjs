import path from 'node:path'
import { spawnSync } from 'node:child_process'
import { resolveSupportedPhp } from './php-runtime.mjs'

export function loadBackendContractMetadata(root, candidates = null) {
  const binaries = [...new Set((candidates || [
    process.env.PHP_BINARY || 'php',
  ]).filter(Boolean))]
  const failures = []
  for (const binary of binaries) {
    let php
    try {
      php = resolveSupportedPhp({ binary }).binary
    } catch (error) {
      failures.push(`${binary}: ${error.message}`)
      continue
    }
    const result = spawnSync(php, [path.join(root, 'scripts/export-contract-metadata.php')], { cwd: root, encoding: 'utf8' })
    if (result.status !== 0 || !result.stdout?.trim()) {
      failures.push(`${binary}: ${result.error?.message || result.stderr?.trim() || `exit ${result.status}`}`)
      continue
    }
    try {
      const metadata = JSON.parse(result.stdout)
      if (!metadata || typeof metadata.resources !== 'object' || typeof metadata.validators !== 'object') throw new Error('invalid metadata shape')
      if (!Object.keys(metadata.resources).length || !Object.keys(metadata.validators).length) throw new Error('backend metadata is unexpectedly empty')
      return metadata
    } catch (error) {
      failures.push(`${binary}: ${error.message}`)
    }
  }
  throw new Error(`Unable to export backend contract metadata:\n${failures.join('\n')}`)
}
