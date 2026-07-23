import { readFile } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'
import path from 'node:path'

const root = fileURLToPath(new URL('..', import.meta.url))
const source = path.join(root, 'packages/sdk/dist/lovecards.umd.js')
const target = path.join(
  root,
  'apps/backend/public/theme/default-ssr/assets/lovecards.umd.js',
)

const [sourceContent, targetContent] = await Promise.all([
  readFile(source),
  readFile(target),
])

if (!sourceContent.equals(targetContent)) {
  console.error('SDK theme asset is stale. Run: npm run sync:sdk-theme')
  process.exit(1)
}

console.log('SDK theme asset is current.')
