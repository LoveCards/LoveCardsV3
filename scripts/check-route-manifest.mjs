import fs from 'node:fs/promises'
import path from 'node:path'
import { spawnSync } from 'node:child_process'
import { resolveSupportedPhp } from './lib/php-runtime.mjs'

const root = process.cwd()
const outputPath = path.join(root, 'contracts', 'route-manifest.json')
const checkOnly = process.argv.includes('--check')
const strict = process.argv.includes('--strict')
const { binary: php } = resolveSupportedPhp()

const result = spawnSync(php, [path.join(root, 'scripts', 'export-route-manifest.php')], {
  cwd: root,
  encoding: 'utf8',
})

if (result.status !== 0) {
  throw new Error(`Unable to export backend route manifest:\n${result.stderr || result.stdout}`)
}

let manifest
try {
  manifest = JSON.parse(result.stdout)
} catch (error) {
  throw new Error(`Backend route manifest is not valid JSON: ${error.message}`)
}

if (!Number.isInteger(manifest.routeCount) || !Array.isArray(manifest.routes)) {
  throw new Error('Backend route manifest has an invalid shape')
}

const operationIds = manifest.routes.map((route) => route.name).filter(Boolean)
if (operationIds.length !== new Set(operationIds).size) {
  throw new Error('Backend route manifest contains duplicate operation IDs')
}

if (strict) {
  const missingContracts = manifest.routes.filter((route) => !route.contract).map((route) => route.name || `${route.method} ${route.path}`)
  if (missingContracts.length) {
    throw new Error(`Routes without executable contracts (${missingContracts.length}): ${missingContracts.join(', ')}`)
  }
}

const output = `${JSON.stringify(manifest, null, 2)}\n`
if (checkOnly) {
  let current = ''
  try { current = await fs.readFile(outputPath, 'utf8') } catch {}
  if (current !== output) {
    console.error('Backend route manifest is stale. Run: npm run export:routes')
    process.exitCode = 1
  } else {
    console.log(`Backend route manifest is up to date (${manifest.routeCount} routes).`)
  }
} else {
  await fs.mkdir(path.dirname(outputPath), { recursive: true })
  await fs.writeFile(outputPath, output)
  console.log(`Exported backend route manifest (${manifest.routeCount} routes).`)
}
