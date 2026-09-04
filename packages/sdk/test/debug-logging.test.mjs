import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { fileURLToPath } from 'node:url'
import path from 'node:path'

const source = path.resolve(fileURLToPath(new URL('../src/resources/base.ts', import.meta.url)))
const text = await readFile(source, 'utf8')

assert.doesNotMatch(text, /console\.log\([^\n]*(?:config\.(?:data|params)|response\.data)/)
assert.match(text, /console\.log\(`\[LC\] \$\{method\} \$\{url\}`\s*,\s*\{ requestId, attempt \}\)/)
assert.match(text, /console\.log\(`\[LC\] \$\{response\.status\}`\s*,\s*\{ requestId, elapsedMs/)
console.log('SDK debug logging regression passed.')
