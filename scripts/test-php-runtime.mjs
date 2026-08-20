import assert from 'node:assert/strict'
import { parsePhpVersionId, resolveSupportedPhp } from './lib/php-runtime.mjs'

const result = (stdout) => ({ status: 0, stdout, stderr: '', error: undefined })

assert.equal(parsePhpVersionId('80100\n'), 80100)
assert.equal(parsePhpVersionId('89999'), 89999)
assert.equal(parsePhpVersionId('PHP 8.1.0'), null)

assert.deepEqual(
  resolveSupportedPhp({ binary: 'fixture-php', spawn: () => result('80100') }),
  { binary: 'fixture-php', versionId: 80100 },
)
assert.throws(
  () => resolveSupportedPhp({ binary: 'fixture-php-8.0', spawn: () => result('80002') }),
  /PHP 8\.1–8\.x is required/,
)
assert.throws(
  () => resolveSupportedPhp({
    binary: 'missing-php',
    spawn: () => ({ status: null, stdout: '', stderr: '', error: new Error('ENOENT') }),
  }),
  /Unable to run PHP binary "missing-php".*ENOENT/,
)

console.log('PHP runtime fixture tests passed.')
