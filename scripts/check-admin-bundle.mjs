import { access, stat } from 'node:fs/promises'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(fileURLToPath(new URL('..', import.meta.url)))
const bundle = path.join(root, 'apps', 'backend', 'public', 'admin')
const required = ['index.html', '_nuxt']

for (const entry of required) {
  const target = path.join(bundle, entry)
  await access(target)
  if (entry === '_nuxt' && !(await stat(target)).isDirectory()) throw new Error(`${target} is not a directory`)
}
console.log('Admin bundle is present and structurally valid')
