# SDK Endpoint Test Report

> Generated: 2026-08-24T08:16:45.223Z

## Summary

| Metric | Count |
|--------|-------|
| Passed | 134 |
| Failed | 0 |
| Skipped | 24 |
| Total | 158 |
| Pass Rate | 84.8% |

## Phase 0: Bootstrap (4/5)

| # | Test | Status | Detail |
|---|------|--------|--------|
| 1 | session.guest() → guest token | ⏭️ SKIP | - |
| 2 | session.login() → admin token | ✅ PASS | - |
| 3 | session.register() → user token | ✅ PASS | - |
| 4 | cards.list() → collect cardId | ✅ PASS | - |
| 5 | tags.list() → collect tagId | ✅ PASS | - |

## Phase 1: Public Endpoints (10/15)

| # | Test | Status | Detail |
|---|------|--------|--------|
| 1 | GET /session/policy → session.policy() | ✅ PASS | - |
| 2 | GET /cards → cards.list() | ✅ PASS | - |
| 3 | GET /cards/:id → cards.get() | ⏭️ SKIP | - |
| 4 | GET /cards/hot → cards.hot() | ✅ PASS | - |
| 5 | GET /cards/search → cards.search() | ✅ PASS | - |
| 6 | GET /cards/search with search_keys → cards.search() | ✅ PASS | - |
| 7 | GET /cards/:id/comments → comments.cardList() | ⏭️ SKIP | - |
| 8 | GET /tags → tags.list() | ✅ PASS | - |
| 9 | GET /tags/:id → tags.get() | ⏭️ SKIP | - |
| 10 | GET /captcha/config → captcha.config() | ✅ PASS | - |
| 11 | GET /theme/config → theme.publicConfig() | ⏭️ SKIP | - |
| 12 | GET /tags → theme.tags() (compat proxy) | ✅ PASS | - |
| 13 | theme.tags() returns same shape as tags.list() | ✅ PASS | - |
| 14 | POST /session/captcha → session.captcha() | ✅ PASS | - |
| 15 | GET /comments/:id → comments.get() | ⏭️ SKIP | - |

## Phase 2: Auth-Only (admin) (7/8)

| # | Test | Status | Detail |
|---|------|--------|--------|
| 1 | GET /session/check → session.check() | ✅ PASS | - |
| 2 | GET /me/cards/:id → cards.getOwn() | ⏭️ SKIP | - |
| 3 | GET /users/me → users.me() | ✅ PASS | - |
| 4 | PATCH /users/me → users.updateMe() | ✅ PASS | - |
| 5 | POST /users/me/password → users.updatePassword() | ✅ PASS | - |
| 6 | GET /users/me/cards → cards.listOwn() | ✅ PASS | - |
| 7 | GET /users/me/comments → comments.listOwn() | ✅ PASS | - |
| 8 | POST /session/logout → session.logout() | ✅ PASS | - |

## Phase 2b: Community Request Scenes (14/14)

| # | Test | Status | Detail |
|---|------|--------|--------|
| 1 | GET /me/library without params → library.list() | ✅ PASS | - |
| 2 | GET /me/history without params → library.history() | ✅ PASS | - |
| 3 | GET /me/notifications without params → notifications.list() | ✅ PASS | - |
| 4 | GET /me/notifications?category=engagement → unified notification list | ✅ PASS | - |
| 5 | GET /me/notifications/unread → notifications.unread() | ✅ PASS | - |
| 6 | GET /me/notifications/stream without params → notifications.stream() | ✅ PASS | - |
| 7 | POST /me/notifications/read requires only ids | ✅ PASS | - |
| 8 | POST /me/notifications/read-all without body | ✅ PASS | - |
| 9 | GET /me/conversations without params → messages.conversations() | ✅ PASS | - |
| 10 | POST /me/messages validates only message fields | ✅ PASS | - |
| 11 | POST /me/messages then GET conversation messages without params | ✅ PASS | - |
| 12 | draft create, list, get, update and delete use only draft fields | ✅ PASS | - |
| 13 | GET and POST /me/privacy use only preference fields | ✅ PASS | - |
| 14 | POST /reports validates only report fields | ✅ PASS | - |

## Phase 3: Protected CRUD (admin) (59/66)

| # | Test | Status | Detail |
|---|------|--------|--------|
| 1 | POST /cards → cards.create() | ✅ PASS | - |
| 2 | library save/remove and history record use ResourceRef query/body contracts | ✅ PASS | - |
| 3 | POST /reports accepts a valid card report | ✅ PASS | - |
| 4 | cards.list search matches the created card | ✅ PASS | - |
| 5 | empty cards.list search is equivalent to the default list | ✅ PASS | - |
| 6 | PATCH /cards/:id → cards.update() | ✅ PASS | - |
| 7 | GET /cards/:id → verify update | ✅ PASS | - |
| 8 | POST /cards/:id/like → cards.like() | ⏭️ SKIP | - |
| 9 | POST /tags → tags.create() | ✅ PASS | - |
| 10 | PATCH /tags/:id → tags.update() | ✅ PASS | - |
| 11 | DELETE /tags/:id → tags.delete() | ✅ PASS | - |
| 12 | POST /cards/:id/comments → comments.create() | ⏭️ SKIP | - |
| 13 | PATCH /admin/comments/:id → comments.moderateManaged() | ⏭️ SKIP | - |
| 14 | DELETE /comments/:id → comments.delete() | ⏭️ SKIP | - |
| 15 | GET /roles → roles.list() | ✅ PASS | - |
| 16 | GET /roles/:id → roles.get() | ✅ PASS | - |
| 17 | GET /roles/:id/grants → roles.getGrants() | ✅ PASS | - |
| 18 | POST /roles → roles.create() | ✅ PASS | - |
| 19 | PATCH /roles/:id → roles.update() | ✅ PASS | - |
| 20 | POST /roles/:id/grants → roles.assignGrants() | ✅ PASS | - |
| 21 | GET /roles/:id/grants → verify assigned | ✅ PASS | - |
| 22 | DELETE /roles/:id → roles.delete() | ✅ PASS | - |
| 23 | POST /roles/reseed → roles.reseed() | ✅ PASS | - |
| 24 | GET /permissions → permissions.list() | ✅ PASS | - |
| 25 | GET /permissions/all → permissions.all() | ✅ PASS | - |
| 26 | GET /dashboard → dashboard.index() | ✅ PASS | - |
| 27 | GET /config → config.list() | ✅ PASS | - |
| 28 | GET /config/groups → config.groups() | ✅ PASS | - |
| 29 | POST /config → config.update() | ✅ PASS | - |
| 30 | GET /admin/files → files.listManaged() | ✅ PASS | - |
| 31 | POST /files/direct → files.direct() | ✅ PASS | - |
| 32 | GET /storage/types → storage.types() | ✅ PASS | - |
| 33 | GET /storage/channels → storage.channels() | ✅ PASS | - |
| 34 | GET /storage/channel-stats → storage.channelStats() | ✅ PASS | - |
| 35 | GET /sender/types → sender.types() | ✅ PASS | - |
| 36 | GET /sender/channels → sender.channels() | ✅ PASS | - |
| 37 | GET /sender/templates → sender.templates() | ✅ PASS | - |
| 38 | GET /captcha/types → captcha.types() | ✅ PASS | - |
| 39 | GET /captcha/drivers → captcha.drivers() | ✅ PASS | - |
| 40 | GET /theme/list → theme.list() | ✅ PASS | - |
| 41 | GET /theme/config → theme.config() | ⏭️ SKIP | - |
| 42 | card media_file_ids produce ordered stable media metadata | ✅ PASS | - |
| 43 | hiding a card revokes its stable public media URL | ✅ PASS | - |
| 44 | PUT /theme/config → theme.updateConfig() | ⏭️ SKIP | - |
| 45 | comment replies reject a parent from another card | ✅ PASS | - |
| 46 | public comment detail hides moderation states | ✅ PASS | - |
| 47 | GET /admin/users → users.listManaged() | ✅ PASS | - |
| 48 | GET /admin/users/:id → users.getManaged() | ✅ PASS | - |
| 49 | PATCH /admin/users/:id → users.updateManaged() | ✅ PASS | - |
| 50 | GET /admin/operations/overview → operations.overview() | ✅ PASS | - |
| 51 | GET /admin/operations/migrations → operations.migrations() | ✅ PASS | - |
| 52 | POST /admin/operations/migrations/run safely checkpoints settled migrations | ✅ PASS | - |
| 53 | POST /admin/operations/migrations/run rejects direct Contract execution | ✅ PASS | - |
| 54 | GET /admin/operations/runtime → operations.runtime() | ✅ PASS | - |
| 55 | GET /admin/operations/deliveries → operations.deliveries() | ✅ PASS | - |
| 56 | GET /admin/operations/notifications → operations.notificationStats() | ✅ PASS | - |
| 57 | GET /admin/audit-logs → operations.auditLogs() | ✅ PASS | - |
| 58 | GET /admin/operations/upgrades → operations.upgrades() | ✅ PASS | - |
| 59 | GET /admin/community/insights → community.insights() | ✅ PASS | - |
| 60 | GET /admin/recovery → users.recovery() | ✅ PASS | - |
| 61 | GET /admin/users/:id/security → users.security() | ✅ PASS | - |
| 62 | GET /admin/users/:id/sessions → users.sessions() | ✅ PASS | - |
| 63 | GET /admin/reports and /admin/reports/:id → reports read model | ✅ PASS | - |
| 64 | POST /admin/reports/:id/transition → reports.transition() | ✅ PASS | - |
| 65 | GET /admin/files/:id/usage → files.usage() | ⏭️ SKIP | - |
| 66 | DELETE /cards/:id → cards.delete() | ✅ PASS | - |

## Phase 4: User Permissions (10/10)

| # | Test | Status | Detail |
|---|------|--------|--------|
| 1 | user: GET /users/me → self profile | ✅ PASS | - |
| 2 | user: GET /cards/list → public access | ✅ PASS | - |
| 3 | user: GET /admin/users → should be denied | ✅ PASS | - |
| 4 | user: GET /dashboard → should 403 (no dashboard.read) | ✅ PASS | - |
| 5 | user: GET /roles → should 403 (no roles.read) | ✅ PASS | - |
| 6 | user: GET /permissions → should 403 (no permissions.read) | ✅ PASS | - |
| 7 | user: GET /config → should 403 (no config.read) | ✅ PASS | - |
| 8 | user: GET /storage/types → should 403 (no storage.read) | ✅ PASS | - |
| 9 | user: GET /sender/types → should 403 (no sender.read) | ✅ PASS | - |
| 10 | user: GET /theme/list → should 403 (no theme.read) | ✅ PASS | - |

## Phase 5: Guest Permissions (0/7)

| # | Test | Status | Detail |
|---|------|--------|--------|
| 1 | guest: POST /cards → should 403 | ⏭️ SKIP | - |
| 2 | guest: GET /users → should 403 | ⏭️ SKIP | - |
| 3 | guest: GET /dashboard → should 403 | ⏭️ SKIP | - |
| 4 | guest: GET /roles → should 403 | ⏭️ SKIP | - |
| 5 | guest: GET /config → should 403 | ⏭️ SKIP | - |
| 6 | guest: GET /admin/files → should be denied | ⏭️ SKIP | - |
| 7 | guest: GET /theme/list → should 403 | ⏭️ SKIP | - |

## Phase 6: Batch Operations (6/9)

| # | Test | Status | Detail |
|---|------|--------|--------|
| 1 | POST /admin/cards/batch → cards.batchManaged(top) | ⏭️ SKIP | - |
| 2 | POST /admin/cards/batch → cards.batchManaged(unset_top) | ⏭️ SKIP | - |
| 3 | POST /admin/cards/batch → cards.batchManaged(approve) | ⏭️ SKIP | - |
| 4 | POST /admin/comments/batch → comments.batchManaged(approve) | ✅ PASS | - |
| 5 | POST /admin/tags/batch → tags.batchManaged(approve) | ✅ PASS | - |
| 6 | POST /admin/users/batch → users.batchManaged(approve) | ✅ PASS | - |
| 7 | POST /admin/files/batch → files.batchManaged(hard_delete) | ✅ PASS | - |
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
| 7 | POST /cards empty content → 422 | ✅ PASS | - |
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

## Phase 9: Cleanup (5/5)

| # | Test | Status | Detail |
|---|------|--------|--------|
| 1 | cleanup: delete test card | ✅ PASS | - |
| 2 | cleanup: delete thread-safety fixtures | ✅ PASS | - |
| 3 | cleanup: delete typed-media fixtures | ✅ PASS | - |
| 4 | cleanup: delete test tag | ✅ PASS | - |
| 5 | cleanup: delete test role | ✅ PASS | - |
