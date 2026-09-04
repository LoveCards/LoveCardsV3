# @lovecards/sdk Endpoint Reference

> 生成快照：2026-08-21。覆盖 25 个 SDK Resource、174 条命名路由（20 public、154 authenticated）。

本文按 `contracts/route-manifest.json` 的 Operation ID 列出当前 HTTP 契约。调用签名、query/body 必填性和 TypeScript 返回类型以 `packages/sdk/src/generated/resources/*.ts` 与构建生成的 `.d.ts` 为准；不要手工编辑本文件替代契约生成。

## 使用说明

- SDK 的 `apiUrl` 设为 `/api`；下表路径不含该前缀。
- `public` 无需 Token；`token` 需要有效身份；`capability` 表示路由还经过 PermissionCheck，具体 capability/scope 以 Route access / `generatedApiContract` 为准。
- GET/DELETE 非路径字段作为 query；POST/PUT/PATCH 字段作为 JSON body，上传接口使用 `FormData`。
- 嵌套 Operation ID 可能在生成类中规范化，例如 `library.history.clear` 对应 `client.library.history_clear()`，`users.me.get` 对应 `client.users.me()`。以生成源码为最终调用名。
- 完整 request/response schema 位于 `contracts/openapi.json`；运行时元数据可从 SDK 导出的 `generatedApiContract` 读取。

## 社区与升级常用签名

```typescript
client.library.remove({ resource_type, resource_id })
client.notifications.list({ cursor, limit, unread_only, category: 'engagement' })
client.notifications.unread({ category: 'engagement' })
client.notifications.readAll({ category: 'engagement' })
client.messages.list(conversationId, { cursor, limit })
client.messages.send({ recipient_id, body, client_message_id })
client.safety.report({ resource_type, resource_id, reason, details })
client.operations.createUpgrade(formData)
client.operations.advanceUpgrade(planId, { reason })
client.operations.approveUpgradeContract(planId, { reason })
client.operations.recoverUpgrade(planId, { reason })
```

## captcha (6)

| Operation ID | HTTP | Path | Access | Response |
| --- | --- | --- | --- | --- |
| `captcha.challenge` | `GET` | `/captcha/challenge` | public | `200 / item` |
| `captcha.config` | `GET` | `/captcha/config` | public | `200 / item` |
| `captcha.drivers` | `GET` | `/admin/captcha/drivers` | capability | `200 / collection` |
| `captcha.install` | `POST` | `/admin/captcha/install` | capability | `200 / collection` |
| `captcha.meta` | `GET` | `/admin/captcha/:slug/meta` | capability | `200 / item` |
| `captcha.types` | `GET` | `/admin/captcha/types` | capability | `200 / collection` |

## cards (16)

| Operation ID | HTTP | Path | Access | Response |
| --- | --- | --- | --- | --- |
| `cards.batchManaged` | `POST` | `/admin/cards/batch` | capability | `204 / void` |
| `cards.create` | `POST` | `/me/cards` | capability | `201 / item` |
| `cards.delete` | `DELETE` | `/me/cards/:id` | capability | `204 / void` |
| `cards.deleteManaged` | `DELETE` | `/admin/cards/:id` | capability | `204 / void` |
| `cards.get` | `GET` | `/cards/:id` | public | `200 / item` |
| `cards.getManaged` | `GET` | `/admin/cards/:id` | capability | `200 / item` |
| `cards.getOwn` | `GET` | `/me/cards/:id` | capability | `200 / item` |
| `cards.hot` | `GET` | `/cards/hot` | public | `200 / collection` |
| `cards.like` | `POST` | `/cards/:id/like` | capability | `200 / item` |
| `cards.list` | `GET` | `/cards` | public | `200 / paginated` |
| `cards.publicByUser` | `GET` | `/users/by-handle/:handle/cards` | public | `200 / paginated` |
| `cards.listManaged` | `GET` | `/admin/cards` | capability | `200 / paginated` |
| `cards.listOwn` | `GET` | `/me/cards` | capability | `200 / paginated` |
| `cards.search` | `GET` | `/cards/search` | public | `200 / paginated` |
| `cards.unlike` | `DELETE` | `/cards/:id/like` | capability | `204 / void` |
| `cards.update` | `PATCH` | `/me/cards/:id` | capability | `204 / void` |
| `cards.updateManaged` | `PATCH` | `/admin/cards/:id` | capability | `204 / void` |

## comments (11)

| Operation ID | HTTP | Path | Access | Response |
| --- | --- | --- | --- | --- |
| `comments.batchManaged` | `POST` | `/admin/comments/batch` | capability | `204 / void` |
| `comments.cardList` | `GET` | `/cards/:id/comments` | public | `200 / paginated` |
| `comments.create` | `POST` | `/cards/:id/comments` | capability | `201 / item` |
| `comments.delete` | `DELETE` | `/me/comments/:id` | capability | `204 / void` |
| `comments.deleteManaged` | `DELETE` | `/admin/comments/:id` | capability | `204 / void` |
| `comments.get` | `GET` | `/comments/:id` | public | `200 / item` |
| `comments.getManaged` | `GET` | `/admin/comments/:id` | capability | `200 / item` |
| `comments.listManaged` | `GET` | `/admin/comments` | capability | `200 / paginated` |
| `comments.listOwn` | `GET` | `/me/comments` | capability | `200 / paginated` |
| `comments.moderateManaged` | `PATCH` | `/admin/comments/:id` | capability | `204 / void` |

## community (1)

| Operation ID | HTTP | Path | Access | Response |
| --- | --- | --- | --- | --- |
| `community.insights` | `GET` | `/admin/community/insights` | capability | `200 / item` |

## config (8)

| Operation ID | HTTP | Path | Access | Response |
| --- | --- | --- | --- | --- |
| `config.delete` | `DELETE` | `/admin/config` | capability | `204 / void` |
| `config.deleteKey` | `DELETE` | `/admin/config/key` | capability | `204 / void` |
| `config.groups` | `GET` | `/admin/config/groups` | capability | `200 / item` |
| `config.init` | `POST` | `/admin/config/init` | capability | `200 / item` |
| `config.list` | `GET` | `/admin/config` | capability | `200 / item` |
| `config.register` | `POST` | `/admin/config/register` | capability | `200 / item` |
| `config.reload` | `POST` | `/admin/config/reload` | capability | `204 / void` |
| `config.update` | `POST` | `/admin/config` | capability | `204 / void` |

## dashboard (1)

| Operation ID | HTTP | Path | Access | Response |
| --- | --- | --- | --- | --- |
| `dashboard.index` | `GET` | `/admin/dashboard` | capability | `200 / item` |

## drafts (5)

| Operation ID | HTTP | Path | Access | Response |
| --- | --- | --- | --- | --- |
| `drafts.create` | `POST` | `/me/drafts` | token | `200 / item` |
| `drafts.delete` | `DELETE` | `/me/drafts/:id` | token | `204 / void` |
| `drafts.get` | `GET` | `/me/drafts/:id` | token | `200 / item` |
| `drafts.list` | `GET` | `/me/drafts` | token | `200 / collection` |
| `drafts.update` | `PATCH` | `/me/drafts/:id` | token | `200 / item` |

## files (13)

| Operation ID | HTTP | Path | Access | Response |
| --- | --- | --- | --- | --- |
| `files.batchManaged` | `POST` | `/admin/files/batch` | capability | `204 / void` |
| `files.cleanup` | `DELETE` | `/admin/files/expired` | capability | `200 / item` |
| `files.confirm` | `PATCH` | `/me/files/:id/confirm` | capability | `204 / void` |
| `files.delete` | `DELETE` | `/me/files/:id` | capability | `204 / void` |
| `files.deleteManaged` | `DELETE` | `/admin/files/:id` | capability | `204 / void` |
| `files.direct` | `POST` | `/me/files/direct` | capability | `200 / item` |
| `files.get` | `GET` | `/me/files/:id` | capability | `200 / item` |
| `files.getManaged` | `GET` | `/admin/files/:id` | capability | `200 / item` |
| `files.listManaged` | `GET` | `/admin/files` | capability | `200 / paginated` |
| `files.listOwn` | `GET` | `/me/files` | capability | `200 / paginated` |
| `files.purge` | `DELETE` | `/admin/files/:id/purge` | capability | `204 / void` |
| `files.upload` | `POST` | `/me/files` | capability | `200 / item` |
| `files.usage` | `GET` | `/admin/files/:id/usage` | capability | `200 / item` |

## library (6)

| Operation ID | HTTP | Path | Access | Response |
| --- | --- | --- | --- | --- |
| `library.history` | `GET` | `/me/history` | token | `200 / item` |
| `library.history.clear` | `DELETE` | `/me/history` | token | `204 / void` |
| `library.history.record` | `POST` | `/me/history` | token | `204 / void` |
| `library.list` | `GET` | `/me/library` | token | `200 / item` |
| `library.remove` | `DELETE` | `/me/library` | token | `204 / void` |
| `library.save` | `POST` | `/me/library` | token | `204 / void` |

## likes (2)

| Operation ID | HTTP | Path | Access | Response |
| --- | --- | --- | --- | --- |
| `likes.list` | `GET` | `/me/likes` | capability | `200 / collection` |
| `likes.unlike` | `DELETE` | `/me/likes/:id` | capability | `204 / void` |

## messages (3)

| Operation ID | HTTP | Path | Access | Response |
| --- | --- | --- | --- | --- |
| `messages.conversations` | `GET` | `/me/conversations` | token | `200 / collection` |
| `messages.list` | `GET` | `/me/conversations/:id/messages` | token | `200 / item` |
| `messages.send` | `POST` | `/me/messages` | token | `200 / item` |

## notifications (5)

| Operation ID | HTTP | Path | Access | Response |
| --- | --- | --- | --- | --- |
| `notifications.list` | `GET` | `/me/notifications` | token | `200 / item` |
| `notifications.read` | `POST` | `/me/notifications/read` | token | `204 / void` |
| `notifications.readAll` | `POST` | `/me/notifications/read-all` | token | `204 / void` |
| `notifications.stream` | `GET` | `/me/notifications/stream` | token | `200 / raw` |
| `notifications.unread` | `GET` | `/me/notifications/unread` | token | `200 / item` |

## operations (14)

| Operation ID | HTTP | Path | Access | Response |
| --- | --- | --- | --- | --- |
| `operations.advanceUpgrade` | `POST` | `/admin/operations/upgrades/:planId/advance` | capability | `200 / item` |
| `operations.approveUpgradeContract` | `POST` | `/admin/operations/upgrades/:planId/approve-contract` | capability | `200 / item` |
| `operations.auditLogs` | `GET` | `/admin/audit-logs` | capability | `200 / paginated` |
| `operations.createUpgrade` | `POST` | `/admin/operations/upgrades` | capability | `201 / item` |
| `operations.deliveries` | `GET` | `/admin/operations/deliveries` | capability | `200 / paginated` |
| `operations.migrations` | `GET` | `/admin/operations/migrations` | capability | `200 / collection` |
| `operations.notificationStats` | `GET` | `/admin/operations/notifications` | capability | `200 / item` |
| `operations.overview` | `GET` | `/admin/operations/overview` | capability | `200 / item` |
| `operations.recoverUpgrade` | `POST` | `/admin/operations/upgrades/:planId/recover` | capability | `200 / item` |
| `operations.retryDelivery` | `POST` | `/admin/operations/deliveries/:id/retry` | capability | `204 / void` |
| `operations.runMigrations` | `POST` | `/admin/operations/migrations/run` | capability | `200 / collection` |
| `operations.runtime` | `GET` | `/admin/operations/runtime` | capability | `200 / item` |
| `operations.upgrade` | `GET` | `/admin/operations/upgrades/:planId` | capability | `200 / item` |
| `operations.upgrades` | `GET` | `/admin/operations/upgrades` | capability | `200 / collection` |

## permissions (2)

| Operation ID | HTTP | Path | Access | Response |
| --- | --- | --- | --- | --- |
| `permissions.all` | `GET` | `/admin/permissions/all` | capability | `200 / collection` |
| `permissions.list` | `GET` | `/admin/permissions` | capability | `200 / paginated` |

## reports (3)

| Operation ID | HTTP | Path | Access | Response |
| --- | --- | --- | --- | --- |
| `reports.getManaged` | `GET` | `/admin/reports/:id` | capability | `200 / item` |
| `reports.listManaged` | `GET` | `/admin/reports` | capability | `200 / paginated` |
| `reports.transition` | `POST` | `/admin/reports/:id/transition` | capability | `200 / item` |

## roles (9)

| Operation ID | HTTP | Path | Access | Response |
| --- | --- | --- | --- | --- |
| `roles.assignGrants` | `POST` | `/admin/roles/:id/grants` | capability | `204 / void` |
| `roles.batch` | `POST` | `/admin/roles/batch` | capability | `204 / void` |
| `roles.create` | `POST` | `/admin/roles` | capability | `200 / item` |
| `roles.delete` | `DELETE` | `/admin/roles/:id` | capability | `204 / void` |
| `roles.get` | `GET` | `/admin/roles/:id` | capability | `200 / item` |
| `roles.getGrants` | `GET` | `/admin/roles/:id/grants` | capability | `200 / collection` |
| `roles.list` | `GET` | `/admin/roles` | capability | `200 / paginated` |
| `roles.reseed` | `POST` | `/admin/roles/reseed` | capability | `200 / item` |
| `roles.update` | `PATCH` | `/admin/roles/:id` | capability | `204 / void` |

## safety (5)

| Operation ID | HTTP | Path | Access | Response |
| --- | --- | --- | --- | --- |
| `safety.block` | `POST` | `/users/:id/block` | token | `204 / void` |
| `safety.preferences` | `GET` | `/me/privacy` | token | `200 / item` |
| `safety.preferences.update` | `POST` | `/me/privacy` | token | `204 / void` |
| `safety.report` | `POST` | `/reports` | token | `200 / item` |
| `safety.unblock` | `DELETE` | `/users/:id/block` | token | `204 / void` |

## sender (6)

| Operation ID | HTTP | Path | Access | Response |
| --- | --- | --- | --- | --- |
| `sender.channels` | `GET` | `/admin/sender/channels` | capability | `200 / collection` |
| `sender.install` | `POST` | `/admin/sender/install` | capability | `200 / collection` |
| `sender.meta` | `GET` | `/admin/sender/:type/meta` | capability | `200 / item` |
| `sender.templates` | `GET` | `/admin/sender/templates` | capability | `200 / collection` |
| `sender.testChannel` | `POST` | `/admin/sender/test-channel` | capability | `200 / item` |
| `sender.types` | `GET` | `/admin/sender/types` | capability | `200 / collection` |

## session (9)

| Operation ID | HTTP | Path | Access | Response |
| --- | --- | --- | --- | --- |
| `session.captcha` | `POST` | `/session/captcha` | public | `204 / void` |
| `session.check` | `GET` | `/session/check` | token | `200 / item` |
| `session.guest` | `POST` | `/session/guest` | public | `200 / item` |
| `session.login` | `POST` | `/session/login` | public | `200 / item` |
| `session.logout` | `POST` | `/session/logout` | token | `204 / void` |
| `session.policy` | `GET` | `/session/policy` | public | `200 / item` |
| `session.recovery.request` | `POST` | `/session/recovery/request` | public | `200 / item` |
| `session.recovery.reset` | `POST` | `/session/recovery/reset` | public | `204 / void` |
| `session.register` | `POST` | `/session/register` | public | `200 / item` |

## social (6)

| Operation ID | HTTP | Path | Access | Response |
| --- | --- | --- | --- | --- |
| `social.follow` | `POST` | `/users/:id/follow` | token | `200 / item` |
| `social.followers` | `GET` | `/users/:id/followers` | token | `200 / item` |
| `social.following` | `GET` | `/users/:id/following` | token | `200 / item` |
| `social.profile` | `GET` | `/users/by-handle/:handle` | public | `200 / item` |
| `social.relation` | `GET` | `/users/:id/relation` | token | `200 / item` |
| `social.unfollow` | `DELETE` | `/users/:id/follow` | token | `204 / void` |

## storage (6)

| Operation ID | HTTP | Path | Access | Response |
| --- | --- | --- | --- | --- |
| `storage.channels` | `GET` | `/admin/storage/channels` | capability | `200 / collection` |
| `storage.channelStats` | `GET` | `/admin/storage/channel-stats` | capability | `200 / item` |
| `storage.install` | `POST` | `/admin/storage/install` | capability | `200 / collection` |
| `storage.meta` | `GET` | `/admin/storage/:type/meta` | capability | `200 / item` |
| `storage.testChannel` | `POST` | `/admin/storage/test-channel` | capability | `200 / item` |
| `storage.types` | `GET` | `/admin/storage/types` | capability | `200 / collection` |

## system (1)

| Operation ID | HTTP | Path | Access | Response |
| --- | --- | --- | --- | --- |
| `system.update` | `GET` | `/admin/system/update` | capability | `200 / item` |

## tags (11)

| Operation ID | HTTP | Path | Access | Response |
| --- | --- | --- | --- | --- |
| `tags.batchManaged` | `POST` | `/admin/tags/batch` | capability | `204 / void` |
| `tags.create` | `POST` | `/me/tags` | capability | `204 / void` |
| `tags.delete` | `DELETE` | `/me/tags/:id` | capability | `204 / void` |
| `tags.deleteManaged` | `DELETE` | `/admin/tags/:id` | capability | `204 / void` |
| `tags.get` | `GET` | `/tags/:id` | public | `200 / item` |
| `tags.getManaged` | `GET` | `/admin/tags/:id` | capability | `200 / item` |
| `tags.list` | `GET` | `/tags` | public | `200 / collection` |
| `tags.listManaged` | `GET` | `/admin/tags` | capability | `200 / paginated` |
| `tags.listOwn` | `GET` | `/me/tags` | capability | `200 / paginated` |
| `tags.update` | `PATCH` | `/me/tags/:id` | capability | `204 / void` |
| `tags.updateManaged` | `PATCH` | `/admin/tags/:id` | capability | `204 / void` |

## theme (6)

| Operation ID | HTTP | Path | Access | Response |
| --- | --- | --- | --- | --- |
| `theme.activate` | `POST` | `/admin/theme/activate` | capability | `204 / void` |
| `theme.delete` | `DELETE` | `/admin/theme/delete` | capability | `204 / void` |
| `theme.list` | `GET` | `/admin/theme/list` | capability | `200 / collection` |
| `theme.publicConfig` | `GET` | `/theme/config` | public | `200 / item` |
| `theme.updateConfig` | `PUT` | `/admin/theme/config` | capability | `204 / void` |
| `theme.upload` | `POST` | `/admin/theme/upload` | capability | `200 / item` |

## users (16)

| Operation ID | HTTP | Path | Access | Response |
| --- | --- | --- | --- | --- |
| `users.assignRoles` | `PATCH` | `/admin/users/:id/roles` | capability | `204 / void` |
| `users.batchManaged` | `POST` | `/admin/users/batch` | capability | `204 / void` |
| `users.deleteManaged` | `DELETE` | `/admin/users/:id` | capability | `204 / void` |
| `users.getManaged` | `GET` | `/admin/users/:id` | capability | `200 / item` |
| `users.listManaged` | `GET` | `/admin/users` | capability | `200 / paginated` |
| `users.me.email` | `POST` | `/me/email` | capability | `204 / void` |
| `users.me.emailCaptcha` | `POST` | `/me/email-captcha` | capability | `200 / item` |
| `users.me.get` | `GET` | `/me` | capability | `200 / item` |
| `users.me.password` | `POST` | `/me/password` | capability | `204 / void` |
| `users.me.update` | `PATCH` | `/me` | capability | `204 / void` |
| `users.recovery` | `GET` | `/admin/recovery` | capability | `200 / paginated` |
| `users.revokeSession` | `POST` | `/admin/users/:id/sessions/:sessionId/revoke` | capability | `204 / void` |
| `users.revokeSessions` | `POST` | `/admin/users/:id/sessions/revoke-all` | capability | `204 / void` |
| `users.security` | `GET` | `/admin/users/:id/security` | capability | `200 / item` |
| `users.sessions` | `GET` | `/admin/users/:id/sessions` | capability | `200 / collection` |
| `users.updateManaged` | `PATCH` | `/admin/users/:id` | capability | `204 / void` |

`client.users.me()` 的 `User.stats.received_likes` 由服务端按当前会话用户聚合，仅统计其可见、未删除卡片收到的有效点赞；不包含评论点赞、收藏或当前用户发出的点赞。
