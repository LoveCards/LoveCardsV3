import assert from 'node:assert/strict'
import { EventEmitter } from 'node:events'
import { PassThrough } from 'node:stream'
import { detectUtf8mb4Collation } from './lib/mysql-collation.mjs'

function fakePhp(value, exitCode = 0) {
  return () => {
    const child = new EventEmitter()
    child.stdout = new PassThrough()
    child.stderr = new PassThrough()
    queueMicrotask(() => {
      if (value) child.stdout.end(value)
      else child.stdout.end()
      child.stderr.end(exitCode === 0 ? '' : 'database unavailable')
      child.emit('exit', exitCode)
    })
    return child
  }
}

assert.equal(
  await detectUtf8mb4Collation('fake-php', {}, fakePhp('utf8mb4_general_ci')),
  'utf8mb4_general_ci',
)
assert.equal(
  await detectUtf8mb4Collation('fake-php', {}, fakePhp('utf8mb4_0900_ai_ci')),
  'utf8mb4_0900_ai_ci',
)
assert.equal(
  await detectUtf8mb4Collation('fake-php', { PHP_DATABASE_COLLATION: 'utf8mb4_general_ci' }, fakePhp('')),
  'utf8mb4_general_ci',
)
await assert.rejects(
  detectUtf8mb4Collation('fake-php', {}, fakePhp('', 1)),
  /Unable to detect utf8mb4 collation/,
)
await assert.rejects(
  detectUtf8mb4Collation('fake-php', { PHP_DATABASE_COLLATION: 'bad;collation' }, fakePhp('')),
  /PHP_DATABASE_COLLATION is invalid/,
)
console.log('MySQL utf8mb4 collation detection tests passed.')
