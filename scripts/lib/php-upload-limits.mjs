/**
 * Resolve the PHP request-size directives used by the local development and
 * isolated E2E servers.
 *
 * PHP parses multipart bodies before the application starts, so these values
 * must be passed at process startup (`php -d ...`). Keeping the defaults here
 * aligned with ReleaseArchiveLimits prevents a valid signed Release ZIP from
 * being truncated by the bundled PHP runtime.
 */

const DEFAULT_POST_MAX_SIZE = '768M'
const DEFAULT_UPLOAD_MAX_FILESIZE = '512M'

const SIZE_UNITS = Object.freeze({
  '': 1,
  k: 1024,
  m: 1024 ** 2,
  g: 1024 ** 3,
  t: 1024 ** 4,
})
function parseSize(value, name) {
  const text = String(value ?? '').trim()
  const match = /^(\d+(?:\.\d+)?)\s*([kmgt]?)b?$/i.exec(text)
  if (!match) throw new Error(`${name} must be a positive PHP size (for example 512M)`)
  const bytes = Number(match[1]) * (SIZE_UNITS[match[2].toLowerCase()] || 0)
  if (!Number.isSafeInteger(bytes) || bytes <= 0) throw new Error(`${name} must be a positive PHP size`)
  return bytes
}

function assertDirectiveName(name) {
  if (!/^[A-Za-z][A-Za-z0-9_.-]*$/.test(name)) {
    throw new Error(`Invalid PHP ini directive name: ${name}`)
  }
}

function assertDirectiveValue(name, value) {
  if (value === null || value === undefined || typeof value === 'object') {
    throw new Error(`PHP ini directive ${name} must have a scalar value`)
  }
  const text = String(value)
  if (text === '' || /[\u0000\r\n]/.test(text)) {
    throw new Error(`PHP ini directive ${name} has an invalid value`)
  }
  return text
}

/**
 * Parse LOVECARDS_DEV_PHP_INI.
 *
 * Accepted forms are either a JSON array of `name=value` strings or a JSON
 * object of `{ "name": "value" }`. JSON is deliberate: the value is passed
 * directly to spawn(), never through a shell, on Windows or Unix alike.
 */
function parseConfiguredIni(raw) {
  if (raw === undefined || raw === null || String(raw).trim() === '') return []
  let parsed
  try {
    parsed = JSON.parse(String(raw))
  } catch {
    throw new Error('LOVECARDS_DEV_PHP_INI must be a JSON array or object')
  }

  if (Array.isArray(parsed)) {
    return parsed.map((entry, index) => {
      if (typeof entry !== 'string') throw new Error(`LOVECARDS_DEV_PHP_INI[${index}] must be name=value`)
      const separator = entry.indexOf('=')
      if (separator <= 0) throw new Error(`LOVECARDS_DEV_PHP_INI[${index}] must be name=value`)
      return [entry.slice(0, separator).trim(), entry.slice(separator + 1)]
    })
  }

  if (parsed && typeof parsed === 'object') {
    return Object.entries(parsed)
  }
  throw new Error('LOVECARDS_DEV_PHP_INI must be a JSON array or object')
}

function normalizedMap(env = process.env) {
  const values = new Map([
    ['post_max_size', env.LOVECARDS_DEV_PHP_POST_MAX_SIZE || DEFAULT_POST_MAX_SIZE],
    ['upload_max_filesize', env.LOVECARDS_DEV_PHP_UPLOAD_MAX_FILESIZE || DEFAULT_UPLOAD_MAX_FILESIZE],
  ])

  for (const [name, value] of parseConfiguredIni(env.LOVECARDS_DEV_PHP_INI)) {
    values.set(String(name).trim(), value)
  }

  for (const [name, value] of values) {
    assertDirectiveName(name)
    values.set(name, assertDirectiveValue(name, value))
  }

  const post = values.get('post_max_size')
  const upload = values.get('upload_max_filesize')
  const postBytes = parseSize(post, 'post_max_size')
  const uploadBytes = parseSize(upload, 'upload_max_filesize')
  if (postBytes <= uploadBytes) {
    throw new Error(`post_max_size (${post}) must be larger than upload_max_filesize (${upload})`)
  }
  return values
}

/** Return argv entries suitable for `spawn(php, [...args, 'think', ...])`. */
export function phpUploadIniArgs(env = process.env) {
  const values = normalizedMap(env)
  return [...values].flatMap(([name, value]) => ['-d', `${name}=${value}`])
}

/** Return a redaction-safe summary useful in startup diagnostics. */
export function phpUploadIniSummary(env = process.env) {
  const values = normalizedMap(env)
  return {
    post_max_size: values.get('post_max_size'),
    upload_max_filesize: values.get('upload_max_filesize'),
  }
}

export const PHP_UPLOAD_DEFAULTS = Object.freeze({
  post_max_size: DEFAULT_POST_MAX_SIZE,
  upload_max_filesize: DEFAULT_UPLOAD_MAX_FILESIZE,
})
