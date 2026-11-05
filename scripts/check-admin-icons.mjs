// 管理端图标存在性闸：扫描 apps/admin 源码中的 mdi-* 字面量，
// 逐一核对 @mdi/font 真实字体清单。历史上两次 CI/线上事故都源于
// 凭空写出不存在的图标名（#41 与 2026-11-04 复查），此处永久封死该类问题。
import { readFile, readdir } from 'node:fs/promises'
import path from 'node:path'
import process from 'node:process'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const adminRoot = path.join(root, 'apps/admin')
// @mdi/font 通常提升到根 node_modules；workspaces 布局变化时回落到 app 内安装位。
const fontCssCandidates = [
  path.join(root, 'node_modules/@mdi/font/css/materialdesignicons.css'),
  path.join(adminRoot, 'node_modules/@mdi/font/css/materialdesignicons.css'),
]
const scanDirs = ['pages', 'components', 'composables', 'utils', 'stores', 'middleware', 'config', 'plugins', 'layouts']

import { existsSync } from 'node:fs'
const fontCss = fontCssCandidates.find(existsSync)
if (!fontCss) {
  console.error('Cannot locate @mdi/font materialdesignicons.css — run npm install first.')
  process.exit(1)
}

async function* walk(dir) {
  for (const entry of await readdir(dir, { withFileTypes: true })) {
    if (entry.name === 'node_modules' || entry.name.startsWith('.')) continue
    const full = path.join(dir, entry.name)
    if (entry.isDirectory()) yield* walk(full)
    else if (/\.(ts|vue|tsx)$/.test(entry.name)) yield full
  }
}

const css = await readFile(fontCss, 'utf8')
const known = new Set([...css.matchAll(/\.mdi-([a-z0-9]+(?:-[a-z0-9]+)*)::before/g)].map(m => m[1]))

const problems = []
for (const dir of scanDirs) {
  const dirRoot = path.join(adminRoot, dir)
  if (!existsSync(dirRoot)) continue
  for await (const file of walk(dirRoot)) {
    const source = await readFile(file, 'utf8')
    for (const match of source.matchAll(/\bmdi-([a-z0-9]+(?:-[a-z0-9]+)*)/g)) {
      if (!known.has(match[1])) {
        problems.push(`${path.relative(root, file)}: mdi-${match[1]}`)
      }
    }
  }
}

if (problems.length > 0) {
  console.error(`Admin icons missing from @mdi/font (${problems.length}):`)
  for (const problem of problems) console.error(`  ${problem}`)
  console.error('Fix: pick a glyph that exists in @mdi/font css (grep ".mdi-x::before") or vendor a new icon set deliberately.')
  process.exit(1)
}
console.log(`Admin icons verified against @mdi/font (${known.size} glyphs available).`)
