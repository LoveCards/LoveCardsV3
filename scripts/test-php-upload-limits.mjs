import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import path from 'node:path'
import { fileURLToPath } from 'node:url'
import { phpUploadIniArgs, phpUploadIniSummary, PHP_UPLOAD_DEFAULTS } from './lib/php-upload-limits.mjs'

const root = path.resolve(fileURLToPath(new URL('..', import.meta.url)))

const defaults = phpUploadIniSummary({})
assert.deepEqual(defaults, PHP_UPLOAD_DEFAULTS)
assert.deepEqual(phpUploadIniArgs({}), [
  '-d', 'post_max_size=768M',
  '-d', 'upload_max_filesize=512M',
])

const objectConfig = phpUploadIniSummary({
  LOVECARDS_DEV_PHP_POST_MAX_SIZE: '640M',
  LOVECARDS_DEV_PHP_UPLOAD_MAX_FILESIZE: '480M',
  LOVECARDS_DEV_PHP_INI: JSON.stringify({ max_execution_time: '120' }),
})
assert.deepEqual(objectConfig, {
  post_max_size: '640M',
  upload_max_filesize: '480M',
})
assert.deepEqual(phpUploadIniArgs({
  LOVECARDS_DEV_PHP_INI: JSON.stringify(['post_max_size=600M', 'upload_max_filesize=500M', 'max_execution_time=120']),
}), [
  '-d', 'post_max_size=600M',
  '-d', 'upload_max_filesize=500M',
  '-d', 'max_execution_time=120',
])

assert.throws(
  () => phpUploadIniSummary({ LOVECARDS_DEV_PHP_POST_MAX_SIZE: '512M', LOVECARDS_DEV_PHP_UPLOAD_MAX_FILESIZE: '512M' }),
  /must be larger/
)
assert.throws(
  () => phpUploadIniArgs({ LOVECARDS_DEV_PHP_INI: 'post_max_size=768M' }),
  /JSON array or object/
)
assert.throws(
  () => phpUploadIniArgs({ LOVECARDS_DEV_PHP_INI: JSON.stringify(['post_max_size=768M\nX']) }),
  /invalid value/
)
assert.throws(
  () => phpUploadIniArgs({ LOVECARDS_DEV_PHP_INI: JSON.stringify(['-d post_max_size=768M']) }),
  /Invalid PHP ini directive name/
)

const developmentServer = await readFile(path.join(root, 'scripts', 'dev-backend.mjs'), 'utf8')
assert.match(developmentServer, /spawn\(php, \[\s*\.\.\.phpIniArgs,\s*'-S'/s)
assert.doesNotMatch(developmentServer, /spawn\(php, \[[^\]]*'think'\s*,\s*'run'/s)

console.log('PHP upload-limit resolver regression passed.')
