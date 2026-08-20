import { chromium } from 'playwright'
import { readdir } from 'node:fs/promises'
import path from 'node:path'

const originUrl = new URL(process.env.LOVECARDS_THEME_ORIGIN || 'http://127.0.0.1:6001')
if (!['127.0.0.1', 'localhost', '::1'].includes(originUrl.hostname)) {
  throw new Error('Refusing theme E2E tests: LOVECARDS_THEME_ORIGIN must use localhost or 127.0.0.1')
}
if (originUrl.pathname !== '/' || originUrl.search || originUrl.hash || originUrl.username || originUrl.password) {
  throw new Error('Refusing theme E2E tests: LOVECARDS_THEME_ORIGIN must be a bare local origin')
}
const origin = originUrl.origin
const executablePath = process.env.CHROME_EXECUTABLE
  || (process.platform === 'win32' ? 'C:/Program Files/Google/Chrome/Application/chrome.exe' : '')
const account = process.env.LOVECARDS_THEME_TEST_ACCOUNT || ''
const password = process.env.LOVECARDS_THEME_TEST_PASSWORD || ''
if (Boolean(account) !== Boolean(password)) {
  throw new Error('Theme E2E credentials must provide both account and password')
}
if (account && process.env.LOVECARDS_THEME_TEST_ISOLATED !== '1') {
  throw new Error('Refusing mutating theme E2E tests: set LOVECARDS_THEME_TEST_ISOLATED=1')
}
const browser = await chromium.launch({ headless: true, ...(executablePath ? { executablePath } : {}) })
const page = await browser.newPage()
const failures = []
const cleanupFailures = []
let createdCardId = null
let fixtureCardId = null
let fixtureToken = ''
page.on('pageerror', (error) => failures.push(`pageerror: ${error.message}`))
page.on('response', (response) => {
  if (response.status() >= 400 && !response.url().includes('/favicon')) failures.push(`${response.status()} ${response.request().method()} ${response.url()}`)
})

async function visit(path) {
  const response = await page.goto(`${origin}${path}`, { waitUntil: 'networkidle' })
  if (!response || response.status() !== 200) throw new Error(`${path} did not return SPA entry: ${response?.status()}`)
  const bootstrap = await page.locator('#lc-bootstrap').textContent()
  if (!bootstrap) throw new Error(`${path} missing lc-bootstrap`)
  const context = JSON.parse(bootstrap)
  if (context.mode !== 'spa' || !context.theme || !context.release || context.apiUrl !== '/api') throw new Error(`${path} bootstrap contract invalid`)
  return context
}

async function createDetailFixture() {
  if (!account || !password) {
    throw new Error('public cards API did not provide a detail fixture; set LOVECARDS_THEME_TEST_ACCOUNT and LOVECARDS_THEME_TEST_PASSWORD')
  }
  const loginResponse = await page.request.post(`${origin}/api/session/login`, {
    data: { account, password },
  })
  if (!loginResponse.ok()) throw new Error(`theme detail fixture login failed: ${loginResponse.status()}`)
  const loginPayload = await loginResponse.json()
  fixtureToken = String(loginPayload?.data?.token || '')
  if (!fixtureToken) throw new Error('theme detail fixture login did not return a token')

  const createResponse = await page.request.post(`${origin}/api/me/cards`, {
    headers: { Authorization: `Bearer ${fixtureToken}`, 'X-Token': fixtureToken },
    data: { content: `theme-e2e-detail-${Date.now()}` },
  })
  if (!createResponse.ok()) throw new Error(`theme detail fixture creation failed: ${createResponse.status()}`)
  const createPayload = await createResponse.json()
  fixtureCardId = Number(createPayload?.data?.id)
  if (!Number.isInteger(fixtureCardId) || fixtureCardId < 1) throw new Error('theme detail fixture creation did not return a card id')
  return fixtureCardId
}

try {
  const root = await visit('/')
  const explore = await visit('/explore')
  const cardsResponse = await page.request.get(`${origin}/api/cards?page=1&list_rows=1`)
  if (!cardsResponse.ok()) throw new Error(`public cards API failed: ${cardsResponse.status()}`)
  const cardsPayload = await cardsResponse.json()
  const cardId = cardsPayload?.data?.[0]?.id || await createDetailFixture()
  const detail = await visit(`/post/${cardId}`)
  for (const [name, context] of [['root', root], ['explore', explore], ['detail', detail]]) {
    if (context.release !== root.release || context.theme !== root.theme) throw new Error(`${name} release context changed during deep route fallback`)
  }

  const entryAssets = await page.evaluate(() => [...document.querySelectorAll('script[src], link[rel="stylesheet"]')].map((node) => node.src || node.href))
  if (!entryAssets.some((url) => url.includes('/_lc/theme-assets/'))) throw new Error('SPA entry assets did not resolve through the release asset gateway')
  for (const assetUrl of entryAssets) {
    const response = await page.request.get(assetUrl)
    if (!response.ok() || !response.headers()['cache-control']?.includes('immutable')) throw new Error(`entry asset is not immutable: ${assetUrl}`)
  }

  const imageName = (await readdir(path.resolve('apps/default-theme/dist/assets'))).find((name) => /\.(?:png|jpe?g|gif|webp|avif|svg)$/i.test(name))
  if (!imageName) throw new Error('built theme does not contain an image asset')
  const imageResponse = await page.request.get(`${origin}${root.assetsBase}dist/assets/${encodeURIComponent(imageName)}`)
  if (!imageResponse.ok() || !imageResponse.headers()['content-type']?.startsWith('image/')) throw new Error('theme image asset did not load with an image MIME')

  if (account && password) {
    await page.goto(`${origin}/auth/login`, { waitUntil: 'networkidle' })
    await page.getByLabel('手机号、邮箱或用户名').fill(account)
    await page.getByLabel('密码').fill(password)
    await Promise.all([
      page.waitForURL((url) => url.pathname === '/me'),
      page.locator('.auth-form button[type="submit"]').click(),
    ])
    await page.locator('.profile-hero').waitFor()

    const marker = `theme-e2e-${Date.now()}`
    await page.goto(`${origin}/compose`, { waitUntil: 'networkidle' })
    await page.getByLabel('正文').fill(marker)
    await Promise.all([
      page.waitForURL((url) => /^\/post\/\d+$/.test(url.pathname)),
      page.locator('.compose-actions button[type="submit"]').click(),
    ])
    createdCardId = Number(new URL(page.url()).pathname.split('/').pop())
    if (!Number.isInteger(createdCardId)) throw new Error('publish flow did not navigate to a created card')
    await page.getByText(marker, { exact: true }).waitFor()

    await page.getByLabel('写下你的回复').fill(`${marker}-comment`)
    await page.locator('.comment-chat-composer button').click()
    await page.getByText(/评论已发布/).first().waitFor()

    const deleted = await page.evaluate(async (id) => {
      const token = localStorage.getItem('token')
      const response = await fetch(`/api/me/cards/${id}`, { method: 'DELETE', headers: { Authorization: `Bearer ${token}`, 'X-Token': token } })
      return response.status
    }, createdCardId)
    if (deleted !== 204) throw new Error(`created card cleanup failed: ${deleted}`)
    createdCardId = null
  }

  if (failures.length) throw new Error(failures.join('\n'))
  console.log(`SPA theme E2E passed: entry fallback, public API, deep routes, bootstrap and release assets${account && password ? ', login, publish and comments' : ''}`)
} finally {
  if (createdCardId) {
    try {
      const status = await page.evaluate(async (id) => {
        const token = localStorage.getItem('token')
        const response = await fetch(`/api/me/cards/${id}`, { method: 'DELETE', headers: { Authorization: `Bearer ${token}`, 'X-Token': token } })
        return response.status
      }, createdCardId)
      if (status !== 204) cleanupFailures.push(`created card cleanup returned ${status}`)
    } catch (error) {
      cleanupFailures.push(`created card cleanup failed: ${error instanceof Error ? error.message : String(error)}`)
    }
  }
  if (fixtureCardId && fixtureToken) {
    try {
      const response = await page.request.delete(`${origin}/api/me/cards/${fixtureCardId}`, {
        headers: { Authorization: `Bearer ${fixtureToken}`, 'X-Token': fixtureToken },
      })
      if (response.status() !== 204) cleanupFailures.push(`detail fixture cleanup returned ${response.status()}`)
    } catch (error) {
      cleanupFailures.push(`detail fixture cleanup failed: ${error instanceof Error ? error.message : String(error)}`)
    }
  }
  await browser.close()
  if (cleanupFailures.length) throw new Error(cleanupFailures.join('\n'))
}
