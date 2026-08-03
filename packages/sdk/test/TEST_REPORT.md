# SDK Endpoint Test Report

> Generated: 2026-08-03T18:39:32.621Z

## Summary

| Metric | Count |
|--------|-------|
| Passed | 116 |
| Failed | 0 |
| Skipped | 2 |
| Total | 118 |
| Pass Rate | 98.3% |

## Phase 0: Bootstrap (5/5)

| # | Test | Status | Detail |
|---|------|--------|--------|
| 1 | session.guest() → guest token | ✅ PASS | - |
| 2 | session.login() → admin token | ✅ PASS | - |
| 3 | session.register() → user token | ✅ PASS | - |
| 4 | cards.list() → collect cardId | ✅ PASS | - |
| 5 | tags.list() → collect tagId | ✅ PASS | - |

## Phase 1: Public Endpoints (14/14)

| # | Test | Status | Detail |
|---|------|--------|--------|
| 1 | GET /cards → cards.list() | ✅ PASS | - |
| 2 | GET /cards/:id → cards.get() | ✅ PASS | - |
| 3 | GET /cards/hot → cards.hot() | ✅ PASS | - |
| 4 | GET /cards/search → cards.search() | ✅ PASS | - |
| 5 | GET /cards/search with search_keys → cards.search() | ✅ PASS | - |
| 6 | GET /cards/:id/comments → comments.cardList() | ✅ PASS | - |
| 7 | GET /tags → tags.list() | ✅ PASS | - |
| 8 | GET /tags/:id → tags.get() | ✅ PASS | - |
| 9 | GET /captcha/config → captcha.config() | ✅ PASS | - |
| 10 | GET /theme/config → theme.publicConfig() | ✅ PASS | - |
| 11 | GET /tags → theme.tags() (compat proxy) | ✅ PASS | - |
| 12 | theme.tags() returns same shape as tags.list() | ✅ PASS | - |
| 13 | POST /session/captcha → session.captcha() | ✅ PASS | - |
| 14 | GET /comments/:id → comments.get() | ✅ PASS | - |

## Phase 2: Auth-Only (admin) (7/7)

| # | Test | Status | Detail |
|---|------|--------|--------|
| 1 | GET /session/check → session.check() | ✅ PASS | - |
| 2 | GET /users/me → users.me() | ✅ PASS | - |
| 3 | PATCH /users/me → users.updateMe() | ✅ PASS | - |
| 4 | POST /users/me/password → users.updatePassword() | ✅ PASS | - |
| 5 | GET /users/me/cards → cards.listOwn() | ✅ PASS | - |
| 6 | GET /users/me/comments → comments.listOwn() | ✅ PASS | - |
| 7 | POST /session/logout → session.logout() | ✅ PASS | - |

## Phase 3: Protected CRUD (admin) (42/44)

| # | Test | Status | Detail |
|---|------|--------|--------|
| 1 | POST /cards → cards.create() | ✅ PASS | - |
| 2 | cards.list search matches the created card | ✅ PASS | - |
| 3 | empty cards.list search is equivalent to the default list | ✅ PASS | - |
| 4 | PATCH /cards/:id → cards.update() | ✅ PASS | - |
| 5 | GET /cards/:id → verify update | ✅ PASS | - |
| 6 | POST /cards/:id/like → cards.like() | ✅ PASS | - |
| 7 | POST /tags → tags.create() | ✅ PASS | - |
| 8 | PATCH /tags/:id → tags.update() | ✅ PASS | - |
| 9 | DELETE /tags/:id → tags.delete() | ✅ PASS | - |
| 10 | POST /cards/:id/comments → comments.create() | ✅ PASS | - |
| 11 | PATCH /comments/:id → comments.update() | ⏭️ SKIP | - |
| 12 | DELETE /comments/:id → comments.delete() | ⏭️ SKIP | - |
| 13 | GET /roles → roles.list() | ✅ PASS | - |
| 14 | GET /roles/:id → roles.get() | ✅ PASS | - |
| 15 | GET /roles/:id/capabilities → roles.getCapabilities() | ✅ PASS | - |
| 16 | POST /roles → roles.create() | ✅ PASS | - |
| 17 | PATCH /roles/:id → roles.update() | ✅ PASS | - |
| 18 | POST /roles/:id/capabilities → roles.assignCapabilities() | ✅ PASS | - |
| 19 | GET /roles/:id/capabilities → verify assigned | ✅ PASS | - |
| 20 | DELETE /roles/:id → roles.delete() | ✅ PASS | - |
| 21 | POST /roles/reseed → roles.reseed() | ✅ PASS | - |
| 22 | GET /permissions → permissions.list() | ✅ PASS | - |
| 23 | GET /permissions/all → permissions.all() | ✅ PASS | - |
| 24 | GET /dashboard → dashboard.index() | ✅ PASS | - |
| 25 | GET /config → config.list() | ✅ PASS | - |
| 26 | GET /config/groups → config.groups() | ✅ PASS | - |
| 27 | POST /config → config.update() | ✅ PASS | - |
| 28 | GET /files → files.list() | ✅ PASS | - |
| 29 | POST /files/direct → files.direct() | ✅ PASS | - |
| 30 | GET /storage/types → storage.types() | ✅ PASS | - |
| 31 | GET /storage/channels → storage.channels() | ✅ PASS | - |
| 32 | GET /storage/channel-stats → storage.channelStats() | ✅ PASS | - |
| 33 | GET /sender/types → sender.types() | ✅ PASS | - |
| 34 | GET /sender/channels → sender.channels() | ✅ PASS | - |
| 35 | GET /sender/templates → sender.templates() | ✅ PASS | - |
| 36 | GET /captcha/types → captcha.types() | ✅ PASS | - |
| 37 | GET /captcha/drivers → captcha.drivers() | ✅ PASS | - |
| 38 | GET /theme/list → theme.list() | ✅ PASS | - |
| 39 | GET /theme/config → theme.config() | ✅ PASS | - |
| 40 | PUT /theme/config → theme.updateConfig() | ✅ PASS | - |
| 41 | GET /users → users.list() | ✅ PASS | - |
| 42 | GET /users/:id → users.get() | ✅ PASS | - |
| 43 | PATCH /users/:id → users.update() | ✅ PASS | - |
| 44 | DELETE /cards/:id → cards.delete() | ✅ PASS | - |

## Phase 4: User Permissions (10/10)

| # | Test | Status | Detail |
|---|------|--------|--------|
| 1 | user: GET /users/me → self profile | ✅ PASS | - |
| 2 | user: GET /cards/list → public access | ✅ PASS | - |
| 3 | user: GET /users → should succeed (user has users.read) | ✅ PASS | - |
| 4 | user: GET /dashboard → should 403 (no dashboard.read) | ✅ PASS | - |
| 5 | user: GET /roles → should 403 (no roles.read) | ✅ PASS | - |
| 6 | user: GET /permissions → should 403 (no permissions.read) | ✅ PASS | - |
| 7 | user: GET /config → should 403 (no config.read) | ✅ PASS | - |
| 8 | user: GET /storage/types → should 403 (no storage.read) | ✅ PASS | - |
| 9 | user: GET /sender/types → should 403 (no sender.read) | ✅ PASS | - |
| 10 | user: GET /theme/list → should 403 (no theme.read) | ✅ PASS | - |

## Phase 5: Guest Permissions (7/7)

| # | Test | Status | Detail |
|---|------|--------|--------|
| 1 | guest: POST /cards → should 403 | ✅ PASS | - |
| 2 | guest: GET /users → should 403 | ✅ PASS | - |
| 3 | guest: GET /dashboard → should 403 | ✅ PASS | - |
| 4 | guest: GET /roles → should 403 | ✅ PASS | - |
| 5 | guest: GET /config → should 403 | ✅ PASS | - |
| 6 | guest: GET /files → should succeed (guest has files.read) | ✅ PASS | - |
| 7 | guest: GET /theme/list → should 403 | ✅ PASS | - |

## Phase 6: Batch Operations (9/9)

| # | Test | Status | Detail |
|---|------|--------|--------|
| 1 | POST /cards/batch → cards.batch(top) | ✅ PASS | - |
| 2 | POST /cards/batch → cards.batch(unset_top) | ✅ PASS | - |
| 3 | POST /cards/batch → cards.batch(approve) | ✅ PASS | - |
| 4 | POST /comments/batch → comments.batch(approve) | ✅ PASS | - |
| 5 | POST /tags/batch → tags.batch(approve) | ✅ PASS | - |
| 6 | POST /users/batch → users.batch(approve) | ✅ PASS | - |
| 7 | POST /files/batch → files.batch(hard_delete) | ✅ PASS | - |
| 8 | batch empty ids → should 400 | ✅ PASS | - |
| 9 | batch invalid method → should 400 | ✅ PASS | - |

## Phase 7: Edge Cases (11/11)

| # | Test | Status | Detail |
|---|------|--------|--------|
| 1 | GET /cards/999999 → 404 | ✅ PASS | - |
| 2 | GET /comments/999999 → 404 | ✅ PASS | - |
| 3 | GET /tags/999999 → 404 | ✅ PASS | - |
| 4 | GET /users/999999 → backend behavior (no 404 for nonexistent) | ✅ PASS | - |
| 5 | GET /roles/999999 → 404 | ✅ PASS | - |
| 6 | GET /files/999999 → 404 | ✅ PASS | - |
| 7 | POST /cards empty content → 400 | ✅ PASS | - |
| 8 | PATCH /cards/999999 → 404 | ✅ PASS | - |
| 9 | DELETE /cards/999999 → backend behavior (no 404 for nonexistent) | ✅ PASS | - |
| 10 | no token → protected endpoint → 401 | ✅ PASS | - |
| 11 | invalid token → 401 | ✅ PASS | - |

## Phase 8: Lifecycle Hooks (8/8)

| # | Test | Status | Detail |
|---|------|--------|--------|
| 1 | hook: ctor: beforeRequest+afterResponse fire on request | ✅ PASS | - |
| 2 | hook: runtime: register and fire | ✅ PASS | - |
| 3 | hook: unsubscribe prevents firing | ✅ PASS | - |
| 4 | hook: beforeRequest abort interrupts request | ✅ PASS | - |
| 5 | hook: beforeRequest can add header | ✅ PASS | - |
| 6 | hook: onError fires on 404 | ✅ PASS | - |
| 7 | hook: afterResponse error does not break request | ✅ PASS | - |
| 8 | hook: dedup fires hook once | ✅ PASS | - |

## Phase 9: Cleanup (3/3)

| # | Test | Status | Detail |
|---|------|--------|--------|
| 1 | cleanup: delete test card | ✅ PASS | - |
| 2 | cleanup: delete test tag | ✅ PASS | - |
| 3 | cleanup: delete test role | ✅ PASS | - |
