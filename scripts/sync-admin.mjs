import { cp, mkdir, rm } from 'node:fs/promises'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(fileURLToPath(new URL('..', import.meta.url)))
const source = path.join(root, 'apps', 'admin', '.output', 'public')
const target = path.join(root, 'apps', 'backend', 'public', 'admin')

await mkdir(source, { recursive: true })
await rm(target, { recursive: true, force: true })
await cp(source, target, { recursive: true })
console.log(`Admin bundle synced to ${target}`)
