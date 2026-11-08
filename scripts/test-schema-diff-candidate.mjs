import assert from 'node:assert/strict'
import { mkdtemp, readFile, rm, writeFile } from 'node:fs/promises'
import os from 'node:os'
import path from 'node:path'
import { spawn } from 'node:child_process'

const root = await mkdtemp(path.join(os.tmpdir(), 'lovecards-schema-diff-'))
const before = path.join(root, 'before.sql')
const after = path.join(root, 'after.sql')
const output = path.join(root, 'candidate.json')
await writeFile(before, `CREATE TABLE \`users\` (\n  \`id\` INT NOT NULL,\n  \`name\` VARCHAR(20) NOT NULL,\n  PRIMARY KEY (\`id\`),\n  KEY \`idx_name\` (\`name\`)\n) ENGINE=InnoDB;\n`)
await writeFile(after, `CREATE TABLE \`users\` (\n  \`id\` BIGINT NOT NULL,\n  \`name\` VARCHAR(50) NOT NULL,\n  \`email\` VARCHAR(120) NULL,\n  PRIMARY KEY (\`id\`),\n  KEY \`idx_email\` (\`email\`)\n) ENGINE=InnoDB;\nCREATE TABLE \`cards\` (\n  \`id\` INT NOT NULL,\n  PRIMARY KEY (\`id\`)\n) ENGINE=InnoDB;\n`)
const run = spawn(process.execPath, ['scripts/generate-schema-diff-candidate.mjs', '--from', before, '--to', after, '--output', output], { cwd: path.resolve('.') })
let stderr = ''
run.stderr.on('data', (chunk) => { stderr += chunk })
const exitCode = await new Promise((resolve) => run.on('close', resolve))
assert.equal(exitCode, 0, stderr)
const candidate = JSON.parse(await readFile(output, 'utf8'))
assert.equal(candidate.review_required, true)
assert.equal(candidate.migration_authoring_required, true)
assert.equal(candidate.executable_sql, false)
assert.equal(candidate.summary.change_count, 6)
assert.ok(candidate.changes.some((change) => change.kind === 'table' && change.action === 'added' && change.table === 'cards'))
assert.ok(candidate.changes.some((change) => change.kind === 'column' && change.action === 'changed' && change.table === 'users' && change.name === 'id'))
assert.ok(candidate.changes.some((change) => change.kind === 'column' && change.action === 'added' && change.name === 'email'))
assert.ok(candidate.changes.some((change) => change.kind === 'index' && change.action === 'removed' && change.name === 'idx_name'))
assert.ok(candidate.changes.some((change) => change.kind === 'index' && change.action === 'added' && change.name === 'idx_email'))
assert.equal(candidate.changes.some((change) => 'sql' in change), false)
await assert.rejects(() => writeFile(output, '{}', { flag: 'wx' }))

const unsupported = path.join(root, 'unsupported.sql')
const unsupportedOutput = path.join(root, 'unsupported.json')
await writeFile(unsupported, 'CREATE TABLE `users` (\n  `id` INT NOT NULL,\n  CHECK (`id` > 0)\n) ENGINE=InnoDB;\n')
const unsupportedRun = spawn(process.execPath, ['scripts/generate-schema-diff-candidate.mjs', '--from', before, '--to', unsupported, '--output', unsupportedOutput], { cwd: path.resolve('.') })
let unsupportedStderr = ''
unsupportedRun.stderr.on('data', (chunk) => { unsupportedStderr += chunk })
const unsupportedExitCode = await new Promise((resolve) => unsupportedRun.on('close', resolve))
assert.equal(unsupportedExitCode, 1)
assert.match(unsupportedStderr, /Unsupported table definition/)

const structural = path.join(root, 'structural.sql')
const structuralOutput = path.join(root, 'structural.json')
await writeFile(structural, 'CREATE TABLE `users` (`id` INT NOT NULL) ENGINE=InnoDB;\nALTER TABLE `users` ADD COLUMN `name` VARCHAR(20);\n')
const structuralRun = spawn(process.execPath, ['scripts/generate-schema-diff-candidate.mjs', '--from', before, '--to', structural, '--output', structuralOutput], { cwd: path.resolve('.') })
let structuralStderr = ''
structuralRun.stderr.on('data', (chunk) => { structuralStderr += chunk })
const structuralExitCode = await new Promise((resolve) => structuralRun.on('close', resolve))
assert.equal(structuralExitCode, 1)
assert.match(structuralStderr, /Unsupported structural SQL statement/)

const standaloneIndex = path.join(root, 'standalone-index.sql')
await writeFile(standaloneIndex, 'CREATE TABLE `users` (`id` INT NOT NULL) ENGINE=InnoDB;\nCREATE UNIQUE INDEX `idx_id` ON `users` (`id`);\n')
const standaloneRun = spawn(process.execPath, ['scripts/generate-schema-diff-candidate.mjs', '--from', before, '--to', standaloneIndex], { cwd: path.resolve('.') })
let standaloneStderr = ''
standaloneRun.stderr.on('data', (chunk) => { standaloneStderr += chunk })
assert.equal(await new Promise((resolve) => standaloneRun.on('close', resolve)), 1)
assert.match(standaloneStderr, /Unsupported structural SQL statement/)

await rm(root, { recursive: true, force: true })
console.log('Schema diff candidate baseline passed.')
