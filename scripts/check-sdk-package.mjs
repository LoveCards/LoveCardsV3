import { readFile, readdir, stat } from 'node:fs/promises'
import path from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'

const root = fileURLToPath(new URL('..', import.meta.url))
const sdkRoot = path.join(root, 'packages/sdk')
const manifest = JSON.parse(await readFile(path.join(sdkRoot, 'package.json'), 'utf8'))
const generatedResources = (await readdir(path.join(sdkRoot, 'src', 'generated', 'resources')))
  .filter((name) => name.endsWith('.ts'))
  .map((name) => path.basename(name, '.ts'))
  .sort()

const declaredEntries = new Map([
  ['main', manifest.main],
  ['module', manifest.module],
  ['types', manifest.types],
  ['exports.import', manifest.exports?.['.']?.import],
  ['exports.require', manifest.exports?.['.']?.require],
  ['exports.types', manifest.exports?.['.']?.types],
  ['exports.default', manifest.exports?.['.']?.default],
])

const normalizeEntry = (value) => String(value || '').replace(/^\.\//, '').replaceAll('\\', '/')
const failures = []

for (const [label, declared] of declaredEntries) {
  const normalized = normalizeEntry(declared)
  if (!normalized.startsWith('dist/')) {
    failures.push(`${label} must point into dist/: ${declared || '<missing>'}`)
    continue
  }

  const artifact = path.resolve(sdkRoot, normalized)
  if (artifact !== sdkRoot && !artifact.startsWith(`${sdkRoot}${path.sep}`)) {
    failures.push(`${label} escapes the SDK package output: ${declared}`)
    continue
  }

  try {
    if (!(await stat(artifact)).isFile()) failures.push(`${label} is not a file: ${declared}`)
  } catch {
    failures.push(`${label} points to a missing build artifact: ${declared}`)
  }
}

if (!Array.isArray(manifest.files) || !manifest.files.includes('dist')) {
  failures.push('package files must include dist')
}

const pairs = [
  ['main', 'exports.require'],
  ['module', 'exports.import'],
  ['module', 'exports.default'],
  ['types', 'exports.types'],
]
for (const [left, right] of pairs) {
  if (normalizeEntry(declaredEntries.get(left)) !== normalizeEntry(declaredEntries.get(right))) {
    failures.push(`${left} and ${right} must reference the same artifact`)
  }
}

try {
  const moduleEntry = path.resolve(sdkRoot, normalizeEntry(manifest.module))
  const sdk = await import(`${pathToFileURL(moduleEntry).href}?check=${Date.now()}`)
  const publicApi = sdk.PUBLIC_API
  const generated = sdk.generatedApiContract
  if (!publicApi || typeof publicApi !== 'object' || !generated || typeof generated !== 'object') {
    failures.push('built SDK must export PUBLIC_API and generatedApiContract objects')
  } else {
    for (const [operationId, entry] of Object.entries(publicApi)) {
      const contract = generated[operationId]
      if (!contract) {
        failures.push(`PUBLIC_API references unknown operation: ${operationId}`)
        continue
      }
      if (entry.method !== contract.method) failures.push(`PUBLIC_API method drift for ${operationId}`)
      if (entry.path !== `/api${contract.path}`) failures.push(`PUBLIC_API path drift for ${operationId}: ${entry.path}`)
    }
  }
  if (typeof sdk.createClient !== 'function') {
    failures.push('built SDK must export createClient')
  } else {
    const client = sdk.createClient({
      apiUrl: 'http://127.0.0.1/api',
      tokenStore: { get: () => null, set: () => {}, clear: () => {} },
    })
    for (const resource of generatedResources) {
      if (!Object.prototype.hasOwnProperty.call(client, resource)) {
        failures.push(`generated SDK resource is not mounted on LCClient: ${resource}`)
      }
    }
  }
} catch (error) {
  failures.push(`unable to inspect built SDK exports: ${error instanceof Error ? error.message : String(error)}`)
}

if (failures.length) {
  console.error(`SDK package artifact checks failed:\n- ${failures.join('\n- ')}`)
  process.exitCode = 1
} else {
  console.log(`SDK package artifact checks passed (${declaredEntries.size} declared entries, ${generatedResources.length} mounted resources).`)
}
