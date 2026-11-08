#!/usr/bin/env node
/**
 * 发行构建辅助工具：比较两个结构 SQL 文件并输出待审核候选清单。
 *
 * 这个工具故意不生成 SQL、不连接数据库、不写迁移账本，也不被运行时安装器调用。
 * 所有删除/新增都保留为独立候选，绝不猜测字段重命名或数据转换。
 */
import { createHash } from 'node:crypto'
import { readFile, writeFile } from 'node:fs/promises'

function usage(message) {
  if (message) console.error(`Error: ${message}`)
  console.error('Usage: node scripts/generate-schema-diff-candidate.mjs --from old.sql --to schema.sql [--output candidate.json]')
  process.exitCode = 2
}

function parseArgs(argv) {
  const args = {}
  for (let i = 0; i < argv.length; i += 1) {
    const value = argv[i]
    if (!value.startsWith('--')) return usage(`Unexpected argument: ${value}`)
    const name = value.slice(2)
    if (name === 'help') { usage(); return null }
    const next = argv[i + 1]
    if (!next || next.startsWith('--')) return usage(`Missing value for --${name}`)
    args[name] = next
    i += 1
  }
  if (!args.from || !args.to) return usage('--from and --to are required')
  if (Object.keys(args).some((key) => !['from', 'to', 'output'].includes(key))) return usage('Unknown option')
  return args
}

function stripComments(sql) {
  return sql
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/^\s*--[^\n]*(?:\n|$)/gm, '')
    .replace(/^\s*#[^\n]*(?:\n|$)/gm, '')
}

function splitDefinitions(body) {
  const parts = []
  let start = 0
  let depth = 0
  let quote = null
  for (let i = 0; i < body.length; i += 1) {
    const char = body[i]
    if (quote) {
      if (char === '\\') i += 1
      else if (char === quote) quote = null
      continue
    }
    if (char === "'" || char === '"' || char === '`') { quote = char; continue }
    if (char === '(') depth += 1
    else if (char === ')') depth -= 1
    else if (char === ',' && depth === 0) {
      if (body.slice(start, i).trim()) parts.push(body.slice(start, i).trim())
      start = i + 1
    }
  }
  if (body.slice(start).trim()) parts.push(body.slice(start).trim())
  return parts
}

function normalizeDefinition(value) {
  return value.replace(/\s+/g, ' ').trim()
}

function parseSchema(sql) {
  const tables = {}
  const clean = stripComments(sql)
  const tablePattern = /CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`([^`]+)`\s*\(([\s\S]*?)\)\s*ENGINE\s*=\s*([^;]+);/gim
  let parsedTableCount = 0
  for (const match of clean.matchAll(tablePattern)) {
    parsedTableCount += 1
    const name = match[1]
    const definitions = splitDefinitions(match[2])
    const columns = {}
    const indexes = {}
    const foreignKeys = {}
    for (const raw of definitions) {
      const definition = normalizeDefinition(raw)
      const column = definition.match(/^`([^`]+)`\s+(.+)$/)
      if (column) {
        columns[column[1]] = normalizeDefinition(column[2])
        continue
      }
      const index = definition.match(/^(PRIMARY KEY)\s*(.*)$/i) || definition.match(/^(UNIQUE KEY|KEY|INDEX)\s+`?([^` (]+)`?\s*(.*)$/i)
      if (index) {
        if (index[1].toUpperCase() === 'PRIMARY KEY') indexes.PRIMARY = normalizeDefinition(`${index[1].toUpperCase()} ${index[2]}`)
        else indexes[index[2]] = normalizeDefinition(`${index[1].toUpperCase()} ${index[2]} ${index[3]}`)
        continue
      }
      const foreign = definition.match(/^CONSTRAINT\s+`([^`]+)`\s+(.+)$/i)
      if (foreign) foreignKeys[foreign[1]] = normalizeDefinition(foreign[2])
      else throw new Error(`Unsupported table definition in ${name}: ${definition.slice(0, 120)}`)
    }
    tables[name] = {
      engine: normalizeDefinition(match[3]),
      columns,
      indexes,
      foreign_keys: foreignKeys,
    }
  }
  // 兼容旧版 data.sql 将外键拆成 ALTER TABLE 的写法；只接受完整的 ADD CONSTRAINT 外键语句。
  const structuralStatements = [...clean.matchAll(/(?:^|;)\s*(ALTER|DROP|RENAME|TRUNCATE|CREATE\s+(?!TABLE\b))\b[^;]*/gim)]
  for (const statementMatch of structuralStatements) {
    const statement = normalizeDefinition(statementMatch[0].replace(/^;/, ''))
    const alterForeignKey = statement.match(/^ALTER\s+TABLE\s+`([^`]+)`\s+ADD\s+CONSTRAINT\s+`([^`]+)`\s+(FOREIGN\s+KEY\s*\([^)]+\)\s+REFERENCES\s+`[^`]+`\s*\([^)]+\)(?:\s+ON\s+(?:DELETE|UPDATE)\s+(?:RESTRICT|CASCADE|SET NULL|NO ACTION)){0,2})$/i)
    if (!alterForeignKey) throw new Error(`Unsupported structural SQL statement; refusing incomplete candidate: ${statement}`)
    const table = tables[alterForeignKey[1]]
    if (!table) throw new Error(`ALTER TABLE references unknown table: ${alterForeignKey[1]}`)
    table.foreign_keys[alterForeignKey[2]] = normalizeDefinition(alterForeignKey[3])
  }
  const declaredTableCount = [...clean.matchAll(/\bCREATE\s+TABLE\b/gi)].length
  if (declaredTableCount !== parsedTableCount) {
    throw new Error(`Unable to parse ${declaredTableCount - parsedTableCount} CREATE TABLE statement(s); refusing incomplete candidate`)
  }
  if (Object.keys(tables).length === 0) throw new Error('No CREATE TABLE definitions found')
  return tables
}

function changedEntries(kind, before, after) {
  const changes = []
  for (const name of Object.keys(before).sort()) {
    if (!(name in after)) changes.push({ kind, action: 'removed', name, before: before[name] })
    else if (before[name] !== after[name]) changes.push({ kind, action: 'changed', name, before: before[name], after: after[name] })
  }
  for (const name of Object.keys(after).sort()) {
    if (!(name in before)) changes.push({ kind, action: 'added', name, after: after[name] })
  }
  return changes
}

function diffSchemas(before, after) {
  const changes = []
  for (const name of Object.keys(before).sort()) {
    if (!(name in after)) changes.push({ kind: 'table', action: 'removed', table: name })
  }
  for (const name of Object.keys(after).sort()) {
    if (!(name in before)) changes.push({ kind: 'table', action: 'added', table: name })
  }
  for (const name of Object.keys(before).filter((table) => table in after).sort()) {
    for (const change of changedEntries('column', before[name].columns, after[name].columns)) changes.push({ table: name, ...change })
    for (const change of changedEntries('index', before[name].indexes, after[name].indexes)) changes.push({ table: name, ...change })
    for (const change of changedEntries('foreign_key', before[name].foreign_keys, after[name].foreign_keys)) changes.push({ table: name, ...change })
    if (before[name].engine !== after[name].engine) changes.push({ kind: 'table_option', action: 'changed', table: name, name: 'engine', before: before[name].engine, after: after[name].engine })
  }
  return changes
}

function digest(text) { return createHash('sha256').update(text).digest('hex') }

const args = parseArgs(process.argv.slice(2))
if (args) {
  try {
    const [fromSql, toSql] = await Promise.all([readFile(args.from, 'utf8'), readFile(args.to, 'utf8')])
    const changes = diffSchemas(parseSchema(fromSql), parseSchema(toSql))
    const candidate = {
      schema_diff_version: 1,
      source: { path: args.from, sha256: digest(fromSql) },
      target: { path: args.to, sha256: digest(toSql) },
      summary: {
        change_count: changes.length,
        added: changes.filter((change) => change.action === 'added').length,
        removed: changes.filter((change) => change.action === 'removed').length,
        changed: changes.filter((change) => change.action === 'changed').length,
      },
      changes,
      review_required: true,
      migration_authoring_required: true,
      executable_sql: false,
      execution: '候选清单只供发行审查；必须由维护者写入带 checksum 的显式 migration 后，才可进入 UpgradeCoordinator。',
    }
    const output = JSON.stringify(candidate, null, 2) + '\n'
    if (args.output) await writeFile(args.output, output, { encoding: 'utf8', flag: 'wx' })
    else process.stdout.write(output)
  } catch (error) {
    console.error(`Schema diff candidate failed: ${error.message}`)
    process.exitCode = 1
  }
}
