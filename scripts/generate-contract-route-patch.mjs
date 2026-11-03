import fs from 'node:fs'
import path from 'node:path'
import { spawnSync } from 'node:child_process'
import { resolveSupportedPhp } from './lib/php-runtime.mjs'

const root = process.cwd()
const inventory = JSON.parse(fs.readFileSync(path.join(root, 'contracts', 'api-inventory.json'), 'utf8'))
const { binary: php } = resolveSupportedPhp()
const metadataResult = spawnSync(php, [path.join(root, 'scripts', 'export-contract-metadata.php')], { cwd: root, encoding: 'utf8' })
if (metadataResult.status !== 0) throw new Error(metadataResult.stderr || metadataResult.stdout)
const metadata = JSON.parse(metadataResult.stdout)

const resourceByShortName = new Map()
for (const [className, value] of Object.entries(metadata.resources || {})) {
  if (!resourceByShortName.has(value.shortName)) resourceByShortName.set(value.shortName, className)
}

function phpString(value) {
  return `'${String(value).replaceAll('\\', '\\\\').replaceAll("'", "\\'")}'`
}

function className(value) {
  if (!value) return null
  if (value === 'ApiResource') return 'app\\shared\\Transport\\Http\\ApiResource'
  return resourceByShortName.get(value) || null
}

function requestContract(route) {
  let validator = route.validators?.[0] || null
  let scene = route.validatorScene || null
  if (!validator && route.action === 'batch') {
    validator = 'app\\shared\\Transport\\Validation\\BatchOperationInput'
    scene = 'BatchOperate'
  }
  if (!validator && route.response?.shape === 'paginated') {
    validator = 'app\\shared\\Transport\\Validation\\PaginationInput'
    scene = 'Defult'
  }
  if (!validator) return 'null'

  const details = metadata.validators?.[validator]
  if (!scene && details) {
    const scenes = Object.keys(details.allScenes || {})
    if (scenes.length === 1) scene = scenes[0]
  }
  if (!scene) return 'null'
  return `['validate' => ${phpString(validator)}, 'scene' => ${phpString(scene)}]`
}

function responseContract(route) {
  const response = route.response || {}
  const status = Number(response.status || 200)
  const shape = response.shape === 'paginated' ? 'paginated' : response.shape === 'collection' ? 'collection' : status === 204 ? 'void' : 'item'
  const resource = status === 204 ? 'null' : className(response.resource)
  if (status !== 204 && !resource) throw new Error(`No Resource class found for ${route.name}: ${response.resource}`)
  return `['resource' => ${resource === 'null' ? 'null' : phpString(resource)}, 'shape' => ${phpString(shape)}, 'status' => ${status}]`
}

const routeFiles = fs.readdirSync(path.join(root, 'apps', 'backend', 'app', 'api', 'route')).filter((name) => name.endsWith('.php'))
const byFile = new Map()
for (const route of inventory.routes) {
  if (route.contractSource === 'backend') continue
  const file = routeFiles
    .map((name) => `apps/backend/app/api/route/${name}`)
    .find((candidate) => fs.readFileSync(path.join(root, candidate), 'utf8').includes(`->name('${route.name}')`))
  if (!file) throw new Error(`Route file not found: ${route.name}`)
  if (!byFile.has(file)) byFile.set(file, [])
  byFile.get(file).push(route)
}

const patches = ['*** Begin Patch']
for (const [file, routes] of byFile) {
  const source = fs.readFileSync(path.join(root, file), 'utf8')
  for (const route of routes) {
    const marker = `->name('${route.name}')`
    if (!source.includes(marker)) throw new Error(`Route name not found in ${file}: ${route.name}`)
    const contract = `->setOption('contract', ['request' => ${requestContract(route)}, 'response' => ${responseContract(route)}])`
    patches.push(`*** Update File: ${path.join(root, file).replaceAll('\\', '/')}`)
    patches.push('@@')
    patches.push(`     ${marker}`)
    patches.push(`+    ${contract}`)
  }
}
patches.push('*** End Patch')
console.log(patches.join('\n'))
