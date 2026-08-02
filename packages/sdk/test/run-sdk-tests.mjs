const required = ['LOVECARDS_SDK_TEST_BASE_URL', 'LOVECARDS_SDK_TEST_ADMIN_ACCOUNT', 'LOVECARDS_SDK_TEST_ADMIN_PASSWORD']

if (process.env.LOVECARDS_SDK_TEST_ISOLATED !== '1') {
  throw new Error('Refusing SDK integration tests: set LOVECARDS_SDK_TEST_ISOLATED=1 for a disposable local environment')
}
for (const name of required) {
  if (!process.env[name]) throw new Error(`Refusing SDK integration tests: missing ${name}`)
}
const url = new URL(process.env.LOVECARDS_SDK_TEST_BASE_URL)
if (!['127.0.0.1', 'localhost'].includes(url.hostname)) {
  throw new Error('Refusing SDK integration tests: base URL must use localhost or 127.0.0.1')
}
if (!url.pathname.replace(/\/$/, '').endsWith('/api')) {
  throw new Error('Refusing SDK integration tests: base URL must end with /api')
}
await import('./integration-suite.mjs')
