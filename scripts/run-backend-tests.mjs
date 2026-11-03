import fs from 'node:fs/promises'
import path from 'node:path'
import { spawnSync } from 'node:child_process'
import { resolveSupportedPhp } from './lib/php-runtime.mjs'

const root = process.cwd()
const testRoot = path.join(root, 'apps', 'backend', 'tests')
const { binary: php } = resolveSupportedPhp()
const files = []

async function walk(dir) {
  for (const entry of await fs.readdir(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name)
    if (entry.isDirectory()) await walk(full)
    else if (entry.name.endsWith('.php')) files.push(full)
  }
}

await walk(testRoot)
files.sort()
if (files.length === 0) {
  console.error(`No backend tests found under ${path.relative(root, testRoot)}.`)
  process.exit(1)
}
let failed = 0
for (const file of files) {
  const result = spawnSync(php, [file], { cwd: root, stdio: 'inherit' })
  if (result.error || result.status !== 0) failed++
}
if (failed) {
  console.error(`Backend tests failed: ${failed}/${files.length}`)
  process.exitCode = 1
} else {
  console.log(`Backend tests passed: ${files.length}/${files.length}`)
}
