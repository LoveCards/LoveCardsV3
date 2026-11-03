import { access, readdir, readFile, stat } from 'node:fs/promises'
import { createHash } from 'node:crypto'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(fileURLToPath(new URL('..', import.meta.url)))
const bundle = path.join(root, 'apps', 'backend', 'public', 'admin')
const source = path.join(root, 'apps', 'admin', '.output', 'public')
const required = ['index.html', '_nuxt']

for (const entry of required) {
  const target = path.join(bundle, entry)
  await access(target)
  if (entry === '_nuxt' && !(await stat(target)).isDirectory()) throw new Error(`${target} is not a directory`)
}

async function filesUnder(directory, prefix = '') {
  const rows = []
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    const relative = path.join(prefix, entry.name)
    const absolute = path.join(directory, entry.name)
    if (entry.isDirectory()) rows.push(...await filesUnder(absolute, relative))
    else if (entry.isFile()) rows.push(relative)
  }
  return rows
}

async function digest(file) {
  const hash = createHash('sha256')
  hash.update(await readFile(file))
  return hash.digest('hex')
}

const htmlFiles = (await filesUnder(bundle)).filter(file => file.toLowerCase().endsWith('.html'))
for (const relative of htmlFiles) {
  const html = await readFile(path.join(bundle, relative), 'utf8')
  if (/127\.0\.0\.1(?::\d+)?|localhost(?::\d+)?/i.test(html)) {
    throw new Error(`Admin bundle contains a loopback API origin: ${relative}`)
  }
  if (/environment:\s*["']development["']/i.test(html)) {
    throw new Error(`Admin bundle contains a development environment marker: ${relative}`)
  }
}

try {
  await access(source)
  const [sourceFiles, bundleFiles] = await Promise.all([filesUnder(source), filesUnder(bundle)])
  const normalize = files => files.map(file => file.split(path.sep).join('/')).sort()
  const expected = normalize(sourceFiles)
  const actual = normalize(bundleFiles)
  if (JSON.stringify(expected) !== JSON.stringify(actual)) {
    throw new Error('Backend admin bundle is stale: file manifest differs from apps/admin/.output/public')
  }
  for (const relative of expected) {
    const [sourceHash, bundleHash] = await Promise.all([
      digest(path.join(source, relative)),
      digest(path.join(bundle, relative)),
    ])
    if (sourceHash !== bundleHash) {
      throw new Error(`Backend admin bundle is stale: ${relative}`)
    }
  }
} catch (error) {
  if (error?.code === 'ENOENT') {
    throw new Error('Admin source bundle is missing; run the production admin generate step before checking the served bundle')
  }
  throw error
}

console.log('Admin bundle is present, safe, and synchronized with the generated source bundle')
