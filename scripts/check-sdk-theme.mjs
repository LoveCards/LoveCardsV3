import { mkdtemp, readFile, rm } from 'node:fs/promises'
import { createRequire } from 'node:module'
import { tmpdir } from 'node:os'
import { fileURLToPath, pathToFileURL } from 'node:url'
import path from 'node:path'

const root = fileURLToPath(new URL('..', import.meta.url))
const sdkRoot = path.join(root, 'packages/sdk')
const sdkRequire = createRequire(path.join(sdkRoot, 'package.json'))
const { build } = await import(pathToFileURL(sdkRequire.resolve('vite')).href)
const target = path.join(
  root,
  'apps/backend/public/_lc/sdk-v2-lovecards.js',
)
const dist = path.join(sdkRoot, 'dist/lovecards.browser.js')
const contractPath = path.join(root, 'contracts/openapi.json')
const temporaryDir = await mkdtemp(path.join(tmpdir(), 'lovecards-sdk-browser-'))

let expectedContent
try {
  await build({
    root: sdkRoot,
    configFile: path.join(sdkRoot, 'vite.browser.config.ts'),
    configLoader: 'runner',
    logLevel: 'error',
    build: {
      emptyOutDir: true,
      outDir: temporaryDir,
    },
  })
  expectedContent = await readFile(path.join(temporaryDir, 'lovecards.browser.js'))
} finally {
  await rm(temporaryDir, { recursive: true, force: true })
}

const [distContent, targetContent, contractContent] = await Promise.all([
  readFile(dist),
  readFile(target),
  readFile(contractPath, 'utf8'),
])

const failures = []
if (!expectedContent.equals(distContent)) failures.push('SDK dist browser asset is stale. Run: npm run build:sdk')
if (!expectedContent.equals(targetContent)) failures.push('SDK public asset is stale. Run: npm run sync:sdk-theme')

const contract = JSON.parse(contractContent)
const operations = Object.values(contract.paths || {}).flatMap((methods) =>
  Object.values(methods || {})
    .filter((operation) => operation && operation.operationId)
    .map((operation) => operation.operationId),
)
const missing = operations.filter((operation) => !expectedContent.includes(`"${operation}":`))
if (missing.length > 0) {
  failures.push(`Browser SDK is missing generated operations: ${missing.join(', ')}`)
}

if (failures.length > 0) {
  console.error(failures.join('\n'))
  process.exitCode = 1
} else {
  console.log(`SDK public browser asset is current (${operations.length} generated operations present).`)
}
