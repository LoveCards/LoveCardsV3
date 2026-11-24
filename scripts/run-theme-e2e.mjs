import { chromium } from 'playwright'
import assert from 'node:assert/strict'
import { readdir } from 'node:fs/promises'
import { createRequire } from 'node:module'
import path from 'node:path'

const requireAdmin = createRequire(new URL('../apps/admin/package.json', import.meta.url))
const { expect } = requireAdmin('@playwright/test')

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

async function checkCompactFeedLayout() {
  const viewport = page.viewportSize()
  const longTopic = 'x'.repeat(255)
  const cardsPath = '**/api/cards/search-public?*'
  const recommendedPath = '**/api/cards/hot'
  const topicsPath = '**/api/tags/search?*'
  const author = { uid: '123456789012', username: '测试作者' }
  const profilePath = `**/api/users/${author.uid}/profile`
  const profileCardsPath = `**/api/users/${author.uid}/cards?*`
  const cards = [1, 2, 3].map(id => ({ id, user_id: 1, ...author, content: '布局样例' }))
  const answer = data => route => route.fulfill({
    json: { success: true, data, pagination: { currentPage: 1, itemsPerPage: 12, totalPages: 1, totalItems: data.length } },
  })
  // 样例只作用于当前浏览器，避免依赖共享数据或在数据库中创建长话题。
  await page.route(cardsPath, answer(cards))
  await page.route(topicsPath, answer([{ id: 1, name: longTopic }]))
  await page.route(profilePath, route => route.fulfill({ json: { success: true, data: author } }))
  await page.route(profileCardsPath, answer(cards))
  const assertColumns = (selector, columns) => expect.poll(() => page.evaluate(({ selector, columns }) => {
    const feed = document.querySelector(selector)
    const story = feed?.querySelector('.feed-story')
    if (!story) return false
    const style = getComputedStyle(feed)
    const gap = parseFloat(style.getPropertyValue('--feed-gutter'))
    const contentWidth = feed.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight)
    return contentWidth > 0 && Math.abs(story.getBoundingClientRect().width * columns + gap * (columns - 1) - contentWidth) < 1
      && document.documentElement.scrollWidth <= document.documentElement.clientWidth
  }, { selector, columns }), { message: `${selector} should display ${columns} columns` }).toBe(true)
  try {
    const layout = page.getByRole('combobox', { name: '信息流布局' })
    await page.setViewportSize({ width: 390, height: 844 })
    let releaseRecommended
    const recommendedReady = new Promise(resolve => { releaseRecommended = resolve })
    await page.route(recommendedPath, async route => {
      await recommendedReady
      await route.fulfill({ json: { success: true, data: cards } })
    })
    try {
      await page.goto(origin, { waitUntil: 'domcontentloaded' })
      await page.locator('.home-feed .feed-story-skeleton').first().waitFor()
      await layout.selectOption('single')
      await assertColumns('.home-feed', 1)
      for (const [width, columns] of [[761, 2], [1440, 5]]) {
        await page.setViewportSize({ width, height: 844 })
        await layout.waitFor({ state: 'hidden' })
        await page.locator('.home-page > .feed-toolbar').waitFor({ state: 'hidden' })
        await assertColumns('.home-feed', columns)
      }
    } finally {
      releaseRecommended()
    }
    const homeCards = page.locator('.home-feed .feed-story:not(.feed-story-skeleton)')
    await homeCards.nth(2).waitFor()
    await assertColumns('.home-feed', 5)
    await page.reload({ waitUntil: 'domcontentloaded' })
    await homeCards.nth(2).waitFor()
    await layout.waitFor({ state: 'hidden' })
    await assertColumns('.home-feed', 5)
    await page.setViewportSize({ width: 760, height: 844 })
    assert.equal(await layout.inputValue(), 'single')
    await assertColumns('.home-feed', 1)
    await layout.selectOption('multi')
    await page.setViewportSize({ width: 390, height: 844 })
    await assertColumns('.home-feed', 2)
    await layout.selectOption('single')
    await assertColumns('.home-feed', 1)
    await expect.poll(() => page.evaluate(() => {
      const [first, second] = document.querySelectorAll('.home-feed .feed-story')
      if (!first || !second) return false
      const top = first.getBoundingClientRect()
      const next = second.getBoundingClientRect()
      return Math.abs(top.left - next.left) < 1 && next.top > top.bottom
    }), { message: 'Single-column stories should stack vertically' }).toBe(true)
    await layout.selectOption('multi')

    for (const [path, selector] of [['/search/posts?q=布局', '.search-feed'], [`/user/${author.uid}`, '.public-profile-post-list']]) {
      await page.goto(`${origin}${path}`)
      const stories = page.locator(`${selector} .feed-story:not(.feed-story-skeleton)`)
      await stories.nth(2).waitFor()
      for (const [width, columns] of [[320, 2], [390, 2], [768, 2], [1050, 3], [1240, 4], [1440, 5]]) {
        await page.setViewportSize({ width, height: 844 })
        await assertColumns(selector, columns)
        if (width <= 390) {
          await layout.waitFor()
          const targets = [stories.first().locator('a.story-author')]
          if (selector === '.search-feed') targets.push(page.locator('.search-scope-nav a').first(), page.getByRole('combobox', { name: '搜索内容排序' }))
          for (const target of targets) {
            const box = await target.boundingBox()
            assert.ok(box && box.width >= 44 && box.height >= 44, 'mobile feed and search actions must have a 44px target')
          }
        } else await layout.waitFor({ state: 'hidden' })
      }
    }
    await page.setViewportSize({ width: 390, height: 844 })
    await layout.selectOption('single')
    await assertColumns('.public-profile-post-list', 1)
    await page.goto(`${origin}/search/posts?q=布局`)
    assert.equal(await layout.inputValue(), 'single')
    await assertColumns('.search-feed', 1)
    await page.setViewportSize({ width: 1440, height: 844 })
    await layout.waitFor({ state: 'hidden' })
    await assertColumns('.search-feed', 5)
    await page.setViewportSize({ width: 320, height: 844 })
    assert.equal(await layout.inputValue(), 'single')
    await layout.selectOption('multi')
    let releaseCards
    const waitingForCards = new Promise(resolve => { releaseCards = resolve })
    const delayCards = async route => { await waitingForCards; await answer(cards)(route) }
    await page.route(cardsPath, delayCards)
    try {
      await page.goto(`${origin}/search/posts?q=布局`, { waitUntil: 'commit' })
      await page.locator('.search-feed .feed-story-skeleton').first().waitFor()
      await assertColumns('.search-feed', 2)
      await layout.selectOption('single')
      await assertColumns('.search-feed', 1)
      releaseCards()
      await page.locator('.search-feed .feed-story:not(.feed-story-skeleton)').first().waitFor()
      await assertColumns('.search-feed', 1)
      await layout.selectOption('multi')
    } finally {
      releaseCards()
      await page.unroute(cardsPath, delayCards)
    }
    await page.goto(`${origin}/search/topics?q=布局`)
    const topic = page.getByRole('link', { name: new RegExp(longTopic) })
    await topic.waitFor()
    assert.ok(await topic.evaluate(node => node.getBoundingClientRect().right <= document.documentElement.clientWidth))
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth))
  } finally {
    await page.unroute(recommendedPath)
    await page.unroute(cardsPath)
    await page.unroute(topicsPath)
    await page.unroute(profilePath)
    await page.unroute(profileCardsPath)
    await page.setViewportSize(viewport)
  }
}

async function checkMobileBrowseTasks() {
  const viewport = page.viewportSize()
  const topics = '**/api/tags/search?*'
  await page.route(topics, route => route.fulfill({ json: {
    success: true, data: [{ id: 12345, name: 'journey-sample' }],
    pagination: { currentPage: 1, totalPages: 2, totalItems: 2 },
  } }))
  const expectTaskHeader = async (title) => {
    const header = page.locator('main > .route-task-heading')
    await header.getByText(title, { exact: true }).waitFor()
    assert.equal(await page.locator('.mobile-bottom-nav').count(), 0)
    assert.equal(await page.locator('.product-header').isVisible(), false)
    const back = header.getByRole('link')
    const box = await back.boundingBox()
    assert.ok(box && box.width >= 44 && box.height >= 44)
    return back
  }
  try {
    await page.setViewportSize({ width: 390, height: 844 })
    await page.goto(`${origin}/explore?sort=liked`)
    await page.getByRole('link', { name: '打开搜索' }).click()
    await page.waitForURL('**/search')
    await expectTaskHeader('搜索')
    const searchEntry = page.locator('.mobile-search-entry input')
    await searchEntry.fill('journey')
    await searchEntry.press('Enter')
    await page.waitForURL('**/search?q=journey')
    await page.getByRole('navigation', { name: '搜索结果范围' }).getByRole('link', { name: '话题' }).click()
    await page.getByRole('link', { name: '下一页' }).click()
    await page.waitForURL('**/search/topics?q=journey&page=2')
    await page.getByRole('link', { name: /journey-sample/ }).click()
    await page.waitForURL('**/topic/12345-journey-sample')
    await (await expectTaskHeader('话题')).click()
    await page.waitForURL('**/search/topics?q=journey&page=2')
    await (await expectTaskHeader('搜索')).click()
    await page.waitForURL('**/explore?sort=liked')
    await page.goto(`${origin}/topic/missing-topic-fixture`)
    assert.equal(await (await expectTaskHeader('话题')).getAttribute('href'), '/explore')
    await page.goto(`${origin}/search`)
    assert.equal(await (await expectTaskHeader('搜索')).getAttribute('href'), '/')

    await page.setViewportSize({ width: 1440, height: 900 })
    await page.getByRole('heading', { name: '搜索', level: 1 }).waitFor()
    assert.equal(await page.locator('.product-header').isVisible(), true)
    assert.equal(await page.locator('.route-task-heading').isVisible(), false)
  } finally {
    await page.unroute(topics)
    await page.setViewportSize(viewport)
  }
}

async function checkProductNavigation() {
  const viewport = page.viewportSize()
  const desktop = page.locator('.product-sidebar')
  const top = page.locator('.mobile-product-header')
  const bottom = page.getByRole('navigation', { name: '移动端产品导航' })
  const themeButton = mode => desktop.getByRole('button', { name: `切换主题 · ${mode}` })
  const themeRoot = page.locator('html')
  try {
    await page.emulateMedia({ colorScheme: 'light' })
    for (const width of [761, 1024, 1440]) {
      await page.setViewportSize({ width, height: 850 })
      await page.goto(`${origin}/`)
      const nav = desktop.getByRole('navigation', { name: '主要导航' })
      assert.deepEqual(await nav.getByRole('link').allTextContents(), ['推荐', '发现', '动态', '消息', '发布', '我的'])
      assert.equal(await nav.getByRole('link', { name: '推荐' }).getAttribute('aria-current'), 'page')
      await expect(themeButton('自动')).toBeVisible()
      assert.equal(await top.isVisible(), false)
      assert.equal(await bottom.isVisible(), false)
      const search = page.locator('.desktop-search input')
      await search.waitFor()
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth))
    }
    await themeButton('自动').press('Enter')
    await expect(themeRoot).toHaveAttribute('data-theme', 'light')
    await themeButton('浅色').click()
    await expect(themeRoot).toHaveAttribute('data-theme', 'dark')
    await page.reload()
    await expect(themeButton('深色')).toBeVisible()
    await expect(themeRoot).toHaveAttribute('data-theme', 'dark')
    await themeButton('深色').click()
    await expect(themeButton('自动')).toBeVisible()
    await expect(themeRoot).toHaveAttribute('data-theme', 'light')
    await page.emulateMedia({ colorScheme: 'dark' })
    await expect(themeRoot).toHaveAttribute('data-theme', 'dark')

    await desktop.getByRole('link', { name: '发现' }).click()
    await page.waitForURL('**/explore')
    await desktop.locator('a[href="/explore"][aria-current="page"]').waitFor()
    await page.setViewportSize({ width: 320, height: 760 })
    assert.equal(await desktop.isVisible(), false)
    await top.getByRole('navigation', { name: '内容频道' }).getByRole('link', { name: '发现' }).waitFor()
    assert.equal(await top.locator('.brand').innerText(), 'L')
    assert.deepEqual(await bottom.getByRole('link').allTextContents(), ['首页', '动态', '发布', '消息', '我的'])
    const search = top.getByRole('link', { name: '打开搜索' })
    const box = await search.boundingBox()
    assert.ok(box && box.width >= 44 && box.height >= 44)
    await search.click()
    await page.waitForURL('**/search')
    await bottom.waitFor({ state: 'hidden' })
    await page.getByRole('link', { name: '返回来源页' }).click()
    await page.waitForURL('**/explore')
  } finally {
    await page.emulateMedia({ colorScheme: null })
    await page.setViewportSize(viewport)
  }
}

try {
  const root = await visit('/')
  const explore = await visit('/explore')
  await checkProductNavigation()
  await checkCompactFeedLayout()
  await checkMobileBrowseTasks()
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
    await page.locator('.profile-identity-surface-owner').waitFor()

    const marker = `theme-e2e-${Date.now()}`
    await page.goto(`${origin}/compose`, { waitUntil: 'networkidle' })
    await page.getByRole('combobox', { name: '正文' }).fill(marker)
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
