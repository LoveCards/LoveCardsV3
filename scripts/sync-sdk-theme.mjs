import { copyFile, mkdir } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'
import path from 'node:path'

const root = fileURLToPath(new URL('..', import.meta.url))
const source = path.join(root, 'packages/sdk/dist/lovecards.umd.js')
const target = path.join(
  root,
  'apps/backend/public/theme/default-ssr/assets/lovecards.umd.js',
)

await mkdir(path.dirname(target), { recursive: true })
await copyFile(source, target)
console.log(`Synced ${path.relative(root, source)} -> ${path.relative(root, target)}`)
