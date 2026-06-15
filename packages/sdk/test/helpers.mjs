import { createClient, isApiError } from '../dist/lovecards.es.js'

export const BASE_URL = 'http://127.0.0.1:8001/api'

let passed = 0
let failed = 0
let skipped = 0
const results = []
let currentPhase = ''

export function setPhase(name) {
  currentPhase = name
  console.log(`\n${'='.repeat(60)}`)
  console.log(`  Phase: ${name}`)
  console.log(`${'='.repeat(60)}`)
}

export function makeClient(token) {
  return createClient({
    apiUrl: BASE_URL,
    tokenStore: token ? {
      get: () => token,
      set: () => {},
      clear: () => {},
    } : undefined,
    debug: false,
  })
}

export async function test(name, fn) {
  try {
    const result = await fn()
    passed++
    const entry = { phase: currentPhase, name, status: 'PASS', detail: '', result }
    results.push(entry)
    console.log(`  ✅ ${name}`)
    return result
  } catch (e) {
    if (e === 'SKIP') {
      skipped++
      results.push({ phase: currentPhase, name, status: 'SKIP', detail: '', result: null })
      console.log(`  ⏭️  ${name} (skipped)`)
      return null
    }
    failed++
    const detail = isApiError(e) ? `[${e.status}] ${e.message}` : (e.message || String(e))
    results.push({ phase: currentPhase, name, status: 'FAIL', detail, result: null })
    console.log(`  ❌ ${name}: ${detail}`)
    return null
  }
}

export function assert(condition, msg) {
  if (!condition) throw new Error(msg || 'Assertion failed')
}

export function assertType(val, type, msg) {
  if (typeof val !== type) throw new Error(msg || `Expected ${type}, got ${typeof val}`)
}

export function assertShape(obj, fields, msg) {
  for (const f of fields) {
    if (!(f in obj)) throw new Error(msg || `Missing field: ${f}`)
  }
}

export async function assertApiError(fn, expectedStatus, msg) {
  try {
    await fn()
    throw new Error(msg || `Expected API error ${expectedStatus}, but succeeded`)
  } catch (e) {
    if (e.message === (msg || `Expected API error ${expectedStatus}, but succeeded`)) throw e
    if (isApiError(e)) {
      if (expectedStatus && e.status !== expectedStatus) {
        throw new Error(msg || `Expected status ${expectedStatus}, got ${e.status}: ${e.message}`)
      }
      return e
    }
    throw new Error(msg || `Expected ApiError, got: ${e.message}`)
  }
}

export function sleep(ms) {
  return new Promise(r => setTimeout(r, ms))
}

export function summary() {
  const total = passed + failed + skipped
  console.log(`\n${'='.repeat(60)}`)
  console.log(`  RESULTS: ${passed} passed / ${failed} failed / ${skipped} skipped / ${total} total`)
  console.log(`${'='.repeat(60)}\n`)
  return { passed, failed, skipped, total, results }
}

export function generateReport(data) {
  const lines = []
  lines.push('# SDK Endpoint Test Report')
  lines.push('')
  lines.push(`> Generated: ${new Date().toISOString()}`)
  lines.push('')
  lines.push('## Summary')
  lines.push('')
  lines.push(`| Metric | Count |`)
  lines.push(`|--------|-------|`)
  lines.push(`| Passed | ${data.passed} |`)
  lines.push(`| Failed | ${data.failed} |`)
  lines.push(`| Skipped | ${data.skipped} |`)
  lines.push(`| Total | ${data.total} |`)
  lines.push(`| Pass Rate | ${data.total > 0 ? ((data.passed / data.total) * 100).toFixed(1) : 0}% |`)
  lines.push('')

  const phases = [...new Set(data.results.map(r => r.phase))]
  for (const phase of phases) {
    const items = data.results.filter(r => r.phase === phase)
    const phasePass = items.filter(r => r.status === 'PASS').length
    const phaseFail = items.filter(r => r.status === 'FAIL').length
    lines.push(`## ${phase} (${phasePass}/${items.length})`)
    lines.push('')
    lines.push('| # | Test | Status | Detail |')
    lines.push('|---|------|--------|--------|')
    items.forEach((r, i) => {
      const icon = r.status === 'PASS' ? '✅' : r.status === 'FAIL' ? '❌' : '⏭️'
      lines.push(`| ${i + 1} | ${r.name} | ${icon} ${r.status} | ${r.detail || '-'} |`)
    })
    lines.push('')
  }

  const failures = data.results.filter(r => r.status === 'FAIL')
  if (failures.length > 0) {
    lines.push('## Failures')
    lines.push('')
    for (const f of failures) {
      lines.push(`- **${f.name}** (${f.phase}): ${f.detail}`)
    }
    lines.push('')
  }

  return lines.join('\n')
}
