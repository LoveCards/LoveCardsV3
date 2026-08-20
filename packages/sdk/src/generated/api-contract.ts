import type * as ApiTypes from '../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export interface GeneratedApiContract {
  "captcha.challenge": { method: "GET"; path: "/captcha/challenge"; request: void; response: ApiTypes.CaptchaChallenge; status: 200; shape: "item"; public: true; capabilities: readonly string[] }
  "captcha.config": { method: "GET"; path: "/captcha/config"; request: void; response: ApiTypes.CaptchaConfig; status: 200; shape: "item"; public: true; capabilities: readonly string[] }
  "captcha.drivers": { method: "GET"; path: "/admin/captcha/drivers"; request: void; response: ApiTypes.CaptchaDriver[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "captcha.install": { method: "POST"; path: "/admin/captcha/install"; request: void; response: ApiTypes.CaptchaMeta[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "captcha.meta": { method: "GET"; path: "/admin/captcha/:slug/meta"; request: void; response: ApiTypes.CaptchaDriver; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "captcha.types": { method: "GET"; path: "/admin/captcha/types"; request: void; response: ApiTypes.CaptchaDriver[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "cards.batchManaged": { method: "POST"; path: "/admin/cards/batch"; request: { ids: unknown[]; method: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "cards.create": { method: "POST"; path: "/me/cards"; request: { pictures?: unknown[]; data?: unknown[]; media_file_ids?: unknown[]; picture_file_ids?: unknown[]; cover_file_id?: number; tags?: unknown[]; content: string; cover?: string }; response: ApiTypes.CreateResult; status: 201; shape: "item"; public: false; capabilities: readonly string[] }
  "cards.delete": { method: "DELETE"; path: "/me/cards/:id"; request: void; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "cards.deleteManaged": { method: "DELETE"; path: "/admin/cards/:id"; request: void; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "cards.get": { method: "GET"; path: "/cards/:id"; request: void; response: ApiTypes.Card; status: 200; shape: "item"; public: true; capabilities: readonly string[] }
  "cards.getManaged": { method: "GET"; path: "/admin/cards/:id"; request: void; response: ApiTypes.Card; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "cards.getOwn": { method: "GET"; path: "/me/cards/:id"; request: void; response: ApiTypes.Card; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "cards.hot": { method: "GET"; path: "/cards/hot"; request: void; response: ApiTypes.Card[]; status: 200; shape: "collection"; public: true; capabilities: readonly string[] }
  "cards.like": { method: "POST"; path: "/cards/:id/like"; request: void; response: { likes: number }; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "cards.list": { method: "GET"; path: "/cards"; request: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }; response: ApiTypes.ListResult<ApiTypes.Card>; status: 200; shape: "paginated"; public: true; capabilities: readonly string[] }
  "cards.listManaged": { method: "GET"; path: "/admin/cards"; request: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }; response: ApiTypes.ListResult<ApiTypes.Card>; status: 200; shape: "paginated"; public: false; capabilities: readonly string[] }
  "cards.listOwn": { method: "GET"; path: "/me/cards"; request: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }; response: ApiTypes.ListResult<ApiTypes.Card>; status: 200; shape: "paginated"; public: false; capabilities: readonly string[] }
  "cards.search": { method: "GET"; path: "/cards/search"; request: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }; response: ApiTypes.ListResult<ApiTypes.Card>; status: 200; shape: "paginated"; public: true; capabilities: readonly string[] }
  "cards.unlike": { method: "DELETE"; path: "/cards/:id/like"; request: void; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "cards.update": { method: "PATCH"; path: "/me/cards/:id"; request: { pictures?: unknown[]; content?: string; data?: unknown[]; media_file_ids?: unknown[]; picture_file_ids?: unknown[]; cover_file_id?: number; tags?: unknown[]; cover?: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "cards.updateManaged": { method: "PATCH"; path: "/admin/cards/:id"; request: { pictures?: unknown[]; content?: string; data?: unknown[]; media_file_ids?: unknown[]; picture_file_ids?: unknown[]; cover_file_id?: number; tags?: unknown[]; is_top?: number; status?: number; user_id?: number; comments?: number; views?: number; good?: number; cover?: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "comments.batchManaged": { method: "POST"; path: "/admin/comments/batch"; request: { ids: unknown[]; method: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "comments.cardList": { method: "GET"; path: "/cards/:id/comments"; request: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }; response: ApiTypes.ListResult<ApiTypes.Comment>; status: 200; shape: "paginated"; public: true; capabilities: readonly string[] }
  "comments.create": { method: "POST"; path: "/cards/:id/comments"; request: { parent_id?: number; content: string; data?: unknown[] }; response: ApiTypes.CreateResult; status: 201; shape: "item"; public: false; capabilities: readonly string[] }
  "comments.delete": { method: "DELETE"; path: "/me/comments/:id"; request: void; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "comments.deleteManaged": { method: "DELETE"; path: "/admin/comments/:id"; request: void; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "comments.get": { method: "GET"; path: "/comments/:id"; request: void; response: ApiTypes.Comment; status: 200; shape: "item"; public: true; capabilities: readonly string[] }
  "comments.getManaged": { method: "GET"; path: "/admin/comments/:id"; request: void; response: ApiTypes.Comment; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "comments.listManaged": { method: "GET"; path: "/admin/comments"; request: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }; response: ApiTypes.ListResult<ApiTypes.Comment>; status: 200; shape: "paginated"; public: false; capabilities: readonly string[] }
  "comments.listOwn": { method: "GET"; path: "/me/comments"; request: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }; response: ApiTypes.ListResult<ApiTypes.Comment>; status: 200; shape: "paginated"; public: false; capabilities: readonly string[] }
  "comments.update": { method: "PATCH"; path: "/me/comments/:id"; request: { content?: string; data?: unknown[] }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "comments.updateManaged": { method: "PATCH"; path: "/admin/comments/:id"; request: { aid?: number; pid?: number; is_top?: number; status?: number; user_id?: number; good?: number; content?: string; data?: unknown[] }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "community.insights": { method: "GET"; path: "/admin/community/insights"; request: void; response: ApiTypes.OperationsCommunityInsights; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "config.delete": { method: "DELETE"; path: "/admin/config"; request: { group: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "config.deleteKey": { method: "DELETE"; path: "/admin/config/key"; request: { group: string; key: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "config.groups": { method: "GET"; path: "/admin/config/groups"; request: void; response: ApiTypes.ConfigData; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "config.init": { method: "POST"; path: "/admin/config/init"; request: void; response: ApiTypes.ConfigData; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "config.list": { method: "GET"; path: "/admin/config"; request: { group?: string }; response: ApiTypes.ConfigData; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "config.register": { method: "POST"; path: "/admin/config/register"; request: { group: string; schema: Record<string, unknown> }; response: ApiTypes.ConfigData; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "config.reload": { method: "POST"; path: "/admin/config/reload"; request: { group?: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "config.update": { method: "POST"; path: "/admin/config"; request: Record<string, unknown>; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "dashboard.index": { method: "GET"; path: "/admin/dashboard"; request: void; response: ApiTypes.DashboardData; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "drafts.create": { method: "POST"; path: "/me/drafts"; request: { title?: string; content?: string; payload?: Record<string, unknown> }; response: Record<string, unknown>; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "drafts.delete": { method: "DELETE"; path: "/me/drafts/:id"; request: void; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "drafts.get": { method: "GET"; path: "/me/drafts/:id"; request: void; response: Record<string, unknown>; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "drafts.list": { method: "GET"; path: "/me/drafts"; request: { cursor?: string; limit?: number }; response: Record<string, unknown>[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "drafts.update": { method: "PATCH"; path: "/me/drafts/:id"; request: { title?: string; content?: string; payload?: Record<string, unknown> }; response: Record<string, unknown>; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "files.batchManaged": { method: "POST"; path: "/admin/files/batch"; request: { ids: unknown[]; method: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "files.cleanup": { method: "DELETE"; path: "/admin/files/expired"; request: { limit?: number }; response: { cleaned: number }; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "files.confirm": { method: "PATCH"; path: "/me/files/:id/confirm"; request: void; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "files.delete": { method: "DELETE"; path: "/me/files/:id"; request: void; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "files.deleteManaged": { method: "DELETE"; path: "/admin/files/:id"; request: void; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "files.direct": { method: "POST"; path: "/me/files/direct"; request: { filename: string; size: number; mime: string }; response: ApiTypes.DirectUploadResult; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "files.get": { method: "GET"; path: "/me/files/:id"; request: void; response: ApiTypes.LCFile; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "files.getManaged": { method: "GET"; path: "/admin/files/:id"; request: void; response: ApiTypes.LCFile; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "files.listManaged": { method: "GET"; path: "/admin/files"; request: { page?: number; list_rows?: number; search_keys?: unknown[]; search_like?: number; search_value?: string; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; status?: number; upload_status?: number; scene?: string; ref_type?: string; ref_id?: number }; response: ApiTypes.ListResult<ApiTypes.LCFile>; status: 200; shape: "paginated"; public: false; capabilities: readonly string[] }
  "files.listOwn": { method: "GET"; path: "/me/files"; request: { page?: number; list_rows?: number; search_keys?: unknown[]; search_like?: number; search_value?: string; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; status?: number; upload_status?: number; scene?: string; ref_type?: string; ref_id?: number }; response: ApiTypes.ListResult<ApiTypes.LCFile>; status: 200; shape: "paginated"; public: false; capabilities: readonly string[] }
  "files.purge": { method: "DELETE"; path: "/admin/files/:id/purge"; request: void; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "files.upload": { method: "POST"; path: "/me/files"; request: FormData; response: ApiTypes.UploadResult; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "files.usage": { method: "GET"; path: "/admin/files/:id/usage"; request: void; response: ApiTypes.FileUsage; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "library.history": { method: "GET"; path: "/me/history"; request: { cursor?: string; limit?: number }; response: ApiTypes.CursorPage; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "library.history.clear": { method: "DELETE"; path: "/me/history"; request: void; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "library.history.record": { method: "POST"; path: "/me/history"; request: { resource_type: string; resource_id: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "library.list": { method: "GET"; path: "/me/library"; request: { cursor?: string; limit?: number }; response: ApiTypes.CursorPage; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "library.remove": { method: "DELETE"; path: "/me/library"; request: { resource_type: string; resource_id: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "library.save": { method: "POST"; path: "/me/library"; request: { resource_type: string; resource_id: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "likes.list": { method: "GET"; path: "/me/likes"; request: { type?: string }; response: ApiTypes.Like[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "likes.unlike": { method: "DELETE"; path: "/me/likes/:id"; request: { type?: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "messages.conversations": { method: "GET"; path: "/me/conversations"; request: void; response: Record<string, unknown>[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "messages.list": { method: "GET"; path: "/me/conversations/:id/messages"; request: { cursor?: string; limit?: number }; response: ApiTypes.CursorPage; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "messages.send": { method: "POST"; path: "/me/messages"; request: { recipient_id: number; body: string; client_message_id?: string }; response: Record<string, unknown>; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "notifications.list": { method: "GET"; path: "/me/notifications"; request: { cursor?: string; limit?: number; unread_only?: boolean }; response: ApiTypes.CursorPage; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "notifications.read": { method: "POST"; path: "/me/notifications/read"; request: { ids: number[] }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "notifications.readAll": { method: "POST"; path: "/me/notifications/read-all"; request: void; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "notifications.stream": { method: "GET"; path: "/me/notifications/stream"; request: { after?: number }; response: string; status: 200; shape: "raw"; public: false; capabilities: readonly string[] }
  "notifications.unread": { method: "GET"; path: "/me/notifications/unread"; request: void; response: Record<string, unknown>; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "operations.advanceUpgrade": { method: "POST"; path: "/admin/operations/upgrades/:planId/advance"; request: { reason: string }; response: ApiTypes.OperationsUpgradePlan; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "operations.approveUpgradeContract": { method: "POST"; path: "/admin/operations/upgrades/:planId/approve-contract"; request: { reason: string }; response: ApiTypes.OperationsUpgradePlan; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "operations.auditLogs": { method: "GET"; path: "/admin/audit-logs"; request: { page?: number; list_rows?: number; search_value?: string; search_keys?: string; status?: string; resource_type?: string; actor_id?: number; unread_only?: boolean; worker_type?: string }; response: ApiTypes.ListResult<ApiTypes.OperationsAuditLog>; status: 200; shape: "paginated"; public: false; capabilities: readonly string[] }
  "operations.createUpgrade": { method: "POST"; path: "/admin/operations/upgrades"; request: FormData; response: ApiTypes.OperationsUpgradePlan; status: 201; shape: "item"; public: false; capabilities: readonly string[] }
  "operations.deliveries": { method: "GET"; path: "/admin/operations/deliveries"; request: { page?: number; list_rows?: number; search_value?: string; search_keys?: string; status?: string; resource_type?: string; actor_id?: number; unread_only?: boolean; worker_type?: string }; response: ApiTypes.ListResult<ApiTypes.OperationsDelivery>; status: 200; shape: "paginated"; public: false; capabilities: readonly string[] }
  "operations.migrations": { method: "GET"; path: "/admin/operations/migrations"; request: void; response: ApiTypes.OperationsMigration[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "operations.notificationStats": { method: "GET"; path: "/admin/operations/notifications"; request: void; response: ApiTypes.OperationsNotificationStats; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "operations.overview": { method: "GET"; path: "/admin/operations/overview"; request: void; response: ApiTypes.OperationsOverview; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "operations.recoverUpgrade": { method: "POST"; path: "/admin/operations/upgrades/:planId/recover"; request: { reason: string }; response: ApiTypes.OperationsUpgradePlan; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "operations.retryDelivery": { method: "POST"; path: "/admin/operations/deliveries/:id/retry"; request: { reason: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "operations.runMigrations": { method: "POST"; path: "/admin/operations/migrations/run"; request: { reason?: string }; response: ApiTypes.OperationsMigration[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "operations.runtime": { method: "GET"; path: "/admin/operations/runtime"; request: void; response: ApiTypes.OperationsRuntimeStatus; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "operations.upgrade": { method: "GET"; path: "/admin/operations/upgrades/:planId"; request: void; response: ApiTypes.OperationsUpgradePlan; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "operations.upgrades": { method: "GET"; path: "/admin/operations/upgrades"; request: { limit?: number }; response: ApiTypes.OperationsUpgradePlan[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "permissions.all": { method: "GET"; path: "/admin/permissions/all"; request: void; response: ApiTypes.CapabilityItem[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "permissions.list": { method: "GET"; path: "/admin/permissions"; request: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }; response: ApiTypes.ListResult<ApiTypes.CapabilityItem>; status: 200; shape: "paginated"; public: false; capabilities: readonly string[] }
  "reports.getManaged": { method: "GET"; path: "/admin/reports/:id"; request: void; response: ApiTypes.AdminReport; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "reports.listManaged": { method: "GET"; path: "/admin/reports"; request: { page?: number; list_rows?: number; search_value?: string; search_keys?: string; status?: string; resource_type?: string; actor_id?: number; unread_only?: boolean; worker_type?: string }; response: ApiTypes.ListResult<ApiTypes.AdminReport>; status: 200; shape: "paginated"; public: false; capabilities: readonly string[] }
  "reports.transition": { method: "POST"; path: "/admin/reports/:id/transition"; request: { note?: string; resolution?: string; status: string }; response: ApiTypes.AdminReport; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "roles.assignGrants": { method: "POST"; path: "/admin/roles/:id/grants"; request: { grants: { capability: string; scope: string }[] }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "roles.batch": { method: "POST"; path: "/admin/roles/batch"; request: { ids: unknown[]; method: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "roles.create": { method: "POST"; path: "/admin/roles"; request: { description?: string; name: string; slug: string }; response: Record<string, unknown>; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "roles.delete": { method: "DELETE"; path: "/admin/roles/:id"; request: void; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "roles.get": { method: "GET"; path: "/admin/roles/:id"; request: void; response: ApiTypes.Role; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "roles.getGrants": { method: "GET"; path: "/admin/roles/:id/grants"; request: void; response: ApiTypes.Grant[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "roles.list": { method: "GET"; path: "/admin/roles"; request: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }; response: ApiTypes.ListResult<ApiTypes.Role>; status: 200; shape: "paginated"; public: false; capabilities: readonly string[] }
  "roles.reseed": { method: "POST"; path: "/admin/roles/reseed"; request: void; response: Record<string, unknown>; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "roles.update": { method: "PATCH"; path: "/admin/roles/:id"; request: { name?: string; slug?: string; description?: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "safety.block": { method: "POST"; path: "/users/:id/block"; request: void; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "safety.preferences": { method: "GET"; path: "/me/privacy"; request: void; response: Record<string, unknown>; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "safety.preferences.update": { method: "POST"; path: "/me/privacy"; request: { profile_visibility?: string; messages_from?: string; show_relations?: boolean }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "safety.report": { method: "POST"; path: "/reports"; request: { resource_type: string; resource_id: string; reason: string; details?: string }; response: Record<string, unknown>; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "safety.unblock": { method: "DELETE"; path: "/users/:id/block"; request: void; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "sender.channels": { method: "GET"; path: "/admin/sender/channels"; request: void; response: ApiTypes.SenderChannel[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "sender.install": { method: "POST"; path: "/admin/sender/install"; request: void; response: ApiTypes.SenderMeta[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "sender.meta": { method: "GET"; path: "/admin/sender/:type/meta"; request: void; response: ApiTypes.SenderMeta; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "sender.templates": { method: "GET"; path: "/admin/sender/templates"; request: void; response: ApiTypes.SenderTemplate[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "sender.testChannel": { method: "POST"; path: "/admin/sender/test-channel"; request: { channel: string; to?: string }; response: { success: boolean; message?: string }; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "sender.types": { method: "GET"; path: "/admin/sender/types"; request: void; response: ApiTypes.SenderType[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "session.captcha": { method: "POST"; path: "/session/captcha"; request: { account: string }; response: void; status: 204; shape: "void"; public: true; capabilities: readonly string[] }
  "session.check": { method: "GET"; path: "/session/check"; request: void; response: ApiTypes.SessionState; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "session.guest": { method: "POST"; path: "/session/guest"; request: void; response: ApiTypes.LoginResult; status: 200; shape: "item"; public: true; capabilities: readonly string[] }
  "session.login": { method: "POST"; path: "/session/login"; request: { account: string; password: string }; response: ApiTypes.LoginResult; status: 200; shape: "item"; public: true; capabilities: readonly string[] }
  "session.logout": { method: "POST"; path: "/session/logout"; request: void; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "session.policy": { method: "GET"; path: "/session/policy"; request: void; response: ApiTypes.AuthPolicy; status: 200; shape: "item"; public: true; capabilities: readonly string[] }
  "session.recovery.request": { method: "POST"; path: "/session/recovery/request"; request: { account: string }; response: Record<string, unknown>; status: 200; shape: "item"; public: true; capabilities: readonly string[] }
  "session.recovery.reset": { method: "POST"; path: "/session/recovery/reset"; request: { challenge_id: number; code: string; password: string }; response: void; status: 204; shape: "void"; public: true; capabilities: readonly string[] }
  "session.register": { method: "POST"; path: "/session/register"; request: { account: string; password: string; code?: string; captcha_id?: string; captcha_code?: string; lot_number?: string; captcha_output?: string; pass_token?: string; gen_time?: string }; response: ApiTypes.LoginResult; status: 200; shape: "item"; public: true; capabilities: readonly string[] }
  "social.follow": { method: "POST"; path: "/users/:id/follow"; request: void; response: Record<string, unknown>; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "social.followers": { method: "GET"; path: "/users/:id/followers"; request: { cursor?: string; limit?: number }; response: ApiTypes.CursorPage; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "social.following": { method: "GET"; path: "/users/:id/following"; request: { cursor?: string; limit?: number }; response: ApiTypes.CursorPage; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "social.profile": { method: "GET"; path: "/users/by-handle/:handle"; request: void; response: Record<string, unknown>; status: 200; shape: "item"; public: true; capabilities: readonly string[] }
  "social.relation": { method: "GET"; path: "/users/:id/relation"; request: void; response: Record<string, unknown>; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "social.unfollow": { method: "DELETE"; path: "/users/:id/follow"; request: void; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "storage.channels": { method: "GET"; path: "/admin/storage/channels"; request: void; response: ApiTypes.StorageChannel[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "storage.channelStats": { method: "GET"; path: "/admin/storage/channel-stats"; request: void; response: ApiTypes.ChannelStats; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "storage.install": { method: "POST"; path: "/admin/storage/install"; request: void; response: ApiTypes.StorageMeta[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "storage.meta": { method: "GET"; path: "/admin/storage/:type/meta"; request: void; response: ApiTypes.StorageMeta; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "storage.testChannel": { method: "POST"; path: "/admin/storage/test-channel"; request: { channel: string; to?: string }; response: { success: boolean; message?: string }; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "storage.types": { method: "GET"; path: "/admin/storage/types"; request: void; response: ApiTypes.StorageDriver[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "system.update": { method: "GET"; path: "/admin/system/update"; request: void; response: ApiTypes.SystemUpdateInfo; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "tags.batchManaged": { method: "POST"; path: "/admin/tags/batch"; request: { ids: unknown[]; method: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "tags.create": { method: "POST"; path: "/me/tags"; request: { name: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "tags.delete": { method: "DELETE"; path: "/me/tags/:id"; request: void; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "tags.deleteManaged": { method: "DELETE"; path: "/admin/tags/:id"; request: void; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "tags.get": { method: "GET"; path: "/tags/:id"; request: void; response: ApiTypes.Tag; status: 200; shape: "item"; public: true; capabilities: readonly string[] }
  "tags.getManaged": { method: "GET"; path: "/admin/tags/:id"; request: void; response: ApiTypes.Tag; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "tags.list": { method: "GET"; path: "/tags"; request: void; response: ApiTypes.Tag[]; status: 200; shape: "collection"; public: true; capabilities: readonly string[] }
  "tags.listManaged": { method: "GET"; path: "/admin/tags"; request: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }; response: ApiTypes.ListResult<ApiTypes.Tag>; status: 200; shape: "paginated"; public: false; capabilities: readonly string[] }
  "tags.listOwn": { method: "GET"; path: "/me/tags"; request: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }; response: ApiTypes.ListResult<ApiTypes.Tag>; status: 200; shape: "paginated"; public: false; capabilities: readonly string[] }
  "tags.update": { method: "PATCH"; path: "/me/tags/:id"; request: { name?: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "tags.updateManaged": { method: "PATCH"; path: "/admin/tags/:id"; request: { aid?: number; user_id?: number; name?: string; status?: number }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "theme.activate": { method: "POST"; path: "/admin/theme/activate"; request: { id: string; release: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "theme.delete": { method: "DELETE"; path: "/admin/theme/delete"; request: { id: string; release: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "theme.list": { method: "GET"; path: "/admin/theme/list"; request: void; response: ApiTypes.ThemeItem[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "theme.publicConfig": { method: "GET"; path: "/theme/config"; request: void; response: ApiTypes.ThemeConfigData; status: 200; shape: "item"; public: true; capabilities: readonly string[] }
  "theme.updateConfig": { method: "PUT"; path: "/admin/theme/config"; request: { id: string; release: string; values: Record<string, unknown> }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "theme.upload": { method: "POST"; path: "/admin/theme/upload"; request: FormData; response: ApiTypes.ThemeItem; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "users.assignRoles": { method: "PATCH"; path: "/admin/users/:id/roles"; request: { roles_id: number[] }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "users.batchManaged": { method: "POST"; path: "/admin/users/batch"; request: { ids: unknown[]; method: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "users.deleteManaged": { method: "DELETE"; path: "/admin/users/:id"; request: void; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "users.getManaged": { method: "GET"; path: "/admin/users/:id"; request: void; response: ApiTypes.User; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "users.listManaged": { method: "GET"; path: "/admin/users"; request: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; show_deleted?: number; tag_id?: number; search_value?: string }; response: ApiTypes.ListResult<ApiTypes.User>; status: 200; shape: "paginated"; public: false; capabilities: readonly string[] }
  "users.me.email": { method: "POST"; path: "/me/email"; request: { email: string; captcha?: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "users.me.emailCaptcha": { method: "POST"; path: "/me/email-captcha"; request: { email: string }; response: Record<string, unknown>; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "users.me.get": { method: "GET"; path: "/me"; request: void; response: ApiTypes.User; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "users.me.password": { method: "POST"; path: "/me/password"; request: { password: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "users.me.update": { method: "PATCH"; path: "/me"; request: { avatar?: string; username?: string; password?: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "users.recovery": { method: "GET"; path: "/admin/recovery"; request: { page?: number; list_rows?: number; search_value?: string; search_keys?: string; status?: string; resource_type?: string; actor_id?: number; unread_only?: boolean; worker_type?: string }; response: ApiTypes.ListResult<ApiTypes.RecoveryChallenge>; status: 200; shape: "paginated"; public: false; capabilities: readonly string[] }
  "users.revokeSession": { method: "POST"; path: "/admin/users/:id/sessions/:sessionId/revoke"; request: { reason: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "users.revokeSessions": { method: "POST"; path: "/admin/users/:id/sessions/revoke-all"; request: { reason: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "users.security": { method: "GET"; path: "/admin/users/:id/security"; request: void; response: ApiTypes.UserSecurityOverview; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "users.sessions": { method: "GET"; path: "/admin/users/:id/sessions"; request: void; response: ApiTypes.UserSession[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "users.updateManaged": { method: "PATCH"; path: "/admin/users/:id"; request: { avatar?: string; email?: string; phone?: string; username?: string; status?: number }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
}

export type GeneratedOperationId = keyof GeneratedApiContract
export type GeneratedRequest<K extends GeneratedOperationId> = GeneratedApiContract[K]['request']
export type GeneratedResponse<K extends GeneratedOperationId> = GeneratedApiContract[K]['response']

export const generatedApiContract = {
  "captcha.challenge": { method: "GET", path: "/captcha/challenge", status: 200, shape: "item", public: true, capabilities: [] },
  "captcha.config": { method: "GET", path: "/captcha/config", status: 200, shape: "item", public: true, capabilities: [] },
  "captcha.drivers": { method: "GET", path: "/admin/captcha/drivers", status: 200, shape: "collection", public: false, capabilities: ["admin.access","captcha.read"] },
  "captcha.install": { method: "POST", path: "/admin/captcha/install", status: 200, shape: "collection", public: false, capabilities: ["admin.access","captcha.install"] },
  "captcha.meta": { method: "GET", path: "/admin/captcha/:slug/meta", status: 200, shape: "item", public: false, capabilities: ["admin.access","captcha.read"] },
  "captcha.types": { method: "GET", path: "/admin/captcha/types", status: 200, shape: "collection", public: false, capabilities: ["admin.access","captcha.read"] },
  "cards.batchManaged": { method: "POST", path: "/admin/cards/batch", status: 204, shape: "void", public: false, capabilities: ["admin.access"] },
  "cards.create": { method: "POST", path: "/me/cards", status: 201, shape: "item", public: false, capabilities: ["cards.create"] },
  "cards.delete": { method: "DELETE", path: "/me/cards/:id", status: 204, shape: "void", public: false, capabilities: ["cards.delete","cards.delete"] },
  "cards.deleteManaged": { method: "DELETE", path: "/admin/cards/:id", status: 204, shape: "void", public: false, capabilities: ["admin.access","cards.delete"] },
  "cards.get": { method: "GET", path: "/cards/:id", status: 200, shape: "item", public: true, capabilities: [] },
  "cards.getManaged": { method: "GET", path: "/admin/cards/:id", status: 200, shape: "item", public: false, capabilities: ["admin.access","cards.read"] },
  "cards.getOwn": { method: "GET", path: "/me/cards/:id", status: 200, shape: "item", public: false, capabilities: ["cards.read"] },
  "cards.hot": { method: "GET", path: "/cards/hot", status: 200, shape: "collection", public: true, capabilities: [] },
  "cards.like": { method: "POST", path: "/cards/:id/like", status: 200, shape: "item", public: false, capabilities: ["likes.create"] },
  "cards.list": { method: "GET", path: "/cards", status: 200, shape: "paginated", public: true, capabilities: [] },
  "cards.listManaged": { method: "GET", path: "/admin/cards", status: 200, shape: "paginated", public: false, capabilities: ["admin.access","cards.read"] },
  "cards.listOwn": { method: "GET", path: "/me/cards", status: 200, shape: "paginated", public: false, capabilities: ["cards.read"] },
  "cards.search": { method: "GET", path: "/cards/search", status: 200, shape: "paginated", public: true, capabilities: [] },
  "cards.unlike": { method: "DELETE", path: "/cards/:id/like", status: 204, shape: "void", public: false, capabilities: ["likes.delete"] },
  "cards.update": { method: "PATCH", path: "/me/cards/:id", status: 204, shape: "void", public: false, capabilities: ["cards.update","cards.update"] },
  "cards.updateManaged": { method: "PATCH", path: "/admin/cards/:id", status: 204, shape: "void", public: false, capabilities: ["admin.access","cards.update"] },
  "comments.batchManaged": { method: "POST", path: "/admin/comments/batch", status: 204, shape: "void", public: false, capabilities: ["admin.access"] },
  "comments.cardList": { method: "GET", path: "/cards/:id/comments", status: 200, shape: "paginated", public: true, capabilities: [] },
  "comments.create": { method: "POST", path: "/cards/:id/comments", status: 201, shape: "item", public: false, capabilities: ["comments.create"] },
  "comments.delete": { method: "DELETE", path: "/me/comments/:id", status: 204, shape: "void", public: false, capabilities: ["comments.delete","comments.delete"] },
  "comments.deleteManaged": { method: "DELETE", path: "/admin/comments/:id", status: 204, shape: "void", public: false, capabilities: ["admin.access","comments.delete"] },
  "comments.get": { method: "GET", path: "/comments/:id", status: 200, shape: "item", public: true, capabilities: [] },
  "comments.getManaged": { method: "GET", path: "/admin/comments/:id", status: 200, shape: "item", public: false, capabilities: ["admin.access","comments.read"] },
  "comments.listManaged": { method: "GET", path: "/admin/comments", status: 200, shape: "paginated", public: false, capabilities: ["admin.access","comments.read"] },
  "comments.listOwn": { method: "GET", path: "/me/comments", status: 200, shape: "paginated", public: false, capabilities: ["comments.read"] },
  "comments.update": { method: "PATCH", path: "/me/comments/:id", status: 204, shape: "void", public: false, capabilities: ["comments.update","comments.update"] },
  "comments.updateManaged": { method: "PATCH", path: "/admin/comments/:id", status: 204, shape: "void", public: false, capabilities: ["admin.access","comments.update"] },
  "community.insights": { method: "GET", path: "/admin/community/insights", status: 200, shape: "item", public: false, capabilities: ["admin.access","community.insights.read"] },
  "config.delete": { method: "DELETE", path: "/admin/config", status: 204, shape: "void", public: false, capabilities: ["admin.access","config.deleteKey"] },
  "config.deleteKey": { method: "DELETE", path: "/admin/config/key", status: 204, shape: "void", public: false, capabilities: ["admin.access","config.deleteKey"] },
  "config.groups": { method: "GET", path: "/admin/config/groups", status: 200, shape: "item", public: false, capabilities: ["admin.access","config.read"] },
  "config.init": { method: "POST", path: "/admin/config/init", status: 200, shape: "item", public: false, capabilities: ["admin.access","config.init"] },
  "config.list": { method: "GET", path: "/admin/config", status: 200, shape: "item", public: false, capabilities: ["admin.access","config.read"] },
  "config.register": { method: "POST", path: "/admin/config/register", status: 200, shape: "item", public: false, capabilities: ["admin.access","config.register"] },
  "config.reload": { method: "POST", path: "/admin/config/reload", status: 204, shape: "void", public: false, capabilities: ["admin.access","config.reload"] },
  "config.update": { method: "POST", path: "/admin/config", status: 204, shape: "void", public: false, capabilities: ["admin.access","config.update"] },
  "dashboard.index": { method: "GET", path: "/admin/dashboard", status: 200, shape: "item", public: false, capabilities: ["admin.access","dashboard.read"] },
  "drafts.create": { method: "POST", path: "/me/drafts", status: 200, shape: "item", public: false, capabilities: [] },
  "drafts.delete": { method: "DELETE", path: "/me/drafts/:id", status: 204, shape: "void", public: false, capabilities: [] },
  "drafts.get": { method: "GET", path: "/me/drafts/:id", status: 200, shape: "item", public: false, capabilities: [] },
  "drafts.list": { method: "GET", path: "/me/drafts", status: 200, shape: "collection", public: false, capabilities: [] },
  "drafts.update": { method: "PATCH", path: "/me/drafts/:id", status: 200, shape: "item", public: false, capabilities: [] },
  "files.batchManaged": { method: "POST", path: "/admin/files/batch", status: 204, shape: "void", public: false, capabilities: ["admin.access"] },
  "files.cleanup": { method: "DELETE", path: "/admin/files/expired", status: 200, shape: "item", public: false, capabilities: ["admin.access","files.cleanup"] },
  "files.confirm": { method: "PATCH", path: "/me/files/:id/confirm", status: 204, shape: "void", public: false, capabilities: ["files.upload"] },
  "files.delete": { method: "DELETE", path: "/me/files/:id", status: 204, shape: "void", public: false, capabilities: ["files.delete","files.delete"] },
  "files.deleteManaged": { method: "DELETE", path: "/admin/files/:id", status: 204, shape: "void", public: false, capabilities: ["admin.access","files.delete"] },
  "files.direct": { method: "POST", path: "/me/files/direct", status: 200, shape: "item", public: false, capabilities: ["files.upload"] },
  "files.get": { method: "GET", path: "/me/files/:id", status: 200, shape: "item", public: false, capabilities: ["files.read","files.read"] },
  "files.getManaged": { method: "GET", path: "/admin/files/:id", status: 200, shape: "item", public: false, capabilities: ["admin.access","files.read"] },
  "files.listManaged": { method: "GET", path: "/admin/files", status: 200, shape: "paginated", public: false, capabilities: ["admin.access","files.read"] },
  "files.listOwn": { method: "GET", path: "/me/files", status: 200, shape: "paginated", public: false, capabilities: ["files.read"] },
  "files.purge": { method: "DELETE", path: "/admin/files/:id/purge", status: 204, shape: "void", public: false, capabilities: ["admin.access","files.purge"] },
  "files.upload": { method: "POST", path: "/me/files", status: 200, shape: "item", public: false, capabilities: ["files.upload"] },
  "files.usage": { method: "GET", path: "/admin/files/:id/usage", status: 200, shape: "item", public: false, capabilities: ["admin.access","files.usage.read"] },
  "library.history": { method: "GET", path: "/me/history", status: 200, shape: "item", public: false, capabilities: [] },
  "library.history.clear": { method: "DELETE", path: "/me/history", status: 204, shape: "void", public: false, capabilities: [] },
  "library.history.record": { method: "POST", path: "/me/history", status: 204, shape: "void", public: false, capabilities: [] },
  "library.list": { method: "GET", path: "/me/library", status: 200, shape: "item", public: false, capabilities: [] },
  "library.remove": { method: "DELETE", path: "/me/library", status: 204, shape: "void", public: false, capabilities: [] },
  "library.save": { method: "POST", path: "/me/library", status: 204, shape: "void", public: false, capabilities: [] },
  "likes.list": { method: "GET", path: "/me/likes", status: 200, shape: "collection", public: false, capabilities: ["likes.read"] },
  "likes.unlike": { method: "DELETE", path: "/me/likes/:id", status: 204, shape: "void", public: false, capabilities: ["likes.delete"] },
  "messages.conversations": { method: "GET", path: "/me/conversations", status: 200, shape: "collection", public: false, capabilities: [] },
  "messages.list": { method: "GET", path: "/me/conversations/:id/messages", status: 200, shape: "item", public: false, capabilities: [] },
  "messages.send": { method: "POST", path: "/me/messages", status: 200, shape: "item", public: false, capabilities: [] },
  "notifications.list": { method: "GET", path: "/me/notifications", status: 200, shape: "item", public: false, capabilities: [] },
  "notifications.read": { method: "POST", path: "/me/notifications/read", status: 204, shape: "void", public: false, capabilities: [] },
  "notifications.readAll": { method: "POST", path: "/me/notifications/read-all", status: 204, shape: "void", public: false, capabilities: [] },
  "notifications.stream": { method: "GET", path: "/me/notifications/stream", status: 200, shape: "raw", public: false, capabilities: [] },
  "notifications.unread": { method: "GET", path: "/me/notifications/unread", status: 200, shape: "item", public: false, capabilities: [] },
  "operations.advanceUpgrade": { method: "POST", path: "/admin/operations/upgrades/:planId/advance", status: 200, shape: "item", public: false, capabilities: ["admin.access","system.update"] },
  "operations.approveUpgradeContract": { method: "POST", path: "/admin/operations/upgrades/:planId/approve-contract", status: 200, shape: "item", public: false, capabilities: ["admin.access","system.update.contract"] },
  "operations.auditLogs": { method: "GET", path: "/admin/audit-logs", status: 200, shape: "paginated", public: false, capabilities: ["admin.access","audit.read"] },
  "operations.createUpgrade": { method: "POST", path: "/admin/operations/upgrades", status: 201, shape: "item", public: false, capabilities: ["admin.access","system.update"] },
  "operations.deliveries": { method: "GET", path: "/admin/operations/deliveries", status: 200, shape: "paginated", public: false, capabilities: ["admin.access","communications.read"] },
  "operations.migrations": { method: "GET", path: "/admin/operations/migrations", status: 200, shape: "collection", public: false, capabilities: ["admin.access","operations.read"] },
  "operations.notificationStats": { method: "GET", path: "/admin/operations/notifications", status: 200, shape: "item", public: false, capabilities: ["admin.access","communications.read"] },
  "operations.overview": { method: "GET", path: "/admin/operations/overview", status: 200, shape: "item", public: false, capabilities: ["admin.access","operations.read"] },
  "operations.recoverUpgrade": { method: "POST", path: "/admin/operations/upgrades/:planId/recover", status: 200, shape: "item", public: false, capabilities: ["admin.access","system.recover"] },
  "operations.retryDelivery": { method: "POST", path: "/admin/operations/deliveries/:id/retry", status: 204, shape: "void", public: false, capabilities: ["admin.access","operations.retry"] },
  "operations.runMigrations": { method: "POST", path: "/admin/operations/migrations/run", status: 200, shape: "collection", public: false, capabilities: ["admin.access","operations.migrate"] },
  "operations.runtime": { method: "GET", path: "/admin/operations/runtime", status: 200, shape: "item", public: false, capabilities: ["admin.access","operations.read"] },
  "operations.upgrade": { method: "GET", path: "/admin/operations/upgrades/:planId", status: 200, shape: "item", public: false, capabilities: ["admin.access","operations.read"] },
  "operations.upgrades": { method: "GET", path: "/admin/operations/upgrades", status: 200, shape: "collection", public: false, capabilities: ["admin.access","operations.read"] },
  "permissions.all": { method: "GET", path: "/admin/permissions/all", status: 200, shape: "collection", public: false, capabilities: ["admin.access","permissions.read"] },
  "permissions.list": { method: "GET", path: "/admin/permissions", status: 200, shape: "paginated", public: false, capabilities: ["admin.access","permissions.read"] },
  "reports.getManaged": { method: "GET", path: "/admin/reports/:id", status: 200, shape: "item", public: false, capabilities: ["admin.access","reports.read"] },
  "reports.listManaged": { method: "GET", path: "/admin/reports", status: 200, shape: "paginated", public: false, capabilities: ["admin.access","reports.read"] },
  "reports.transition": { method: "POST", path: "/admin/reports/:id/transition", status: 200, shape: "item", public: false, capabilities: ["admin.access","reports.manage"] },
  "roles.assignGrants": { method: "POST", path: "/admin/roles/:id/grants", status: 204, shape: "void", public: false, capabilities: ["admin.access","roles.assign"] },
  "roles.batch": { method: "POST", path: "/admin/roles/batch", status: 204, shape: "void", public: false, capabilities: ["admin.access","roles.delete"] },
  "roles.create": { method: "POST", path: "/admin/roles", status: 200, shape: "item", public: false, capabilities: ["admin.access","roles.create"] },
  "roles.delete": { method: "DELETE", path: "/admin/roles/:id", status: 204, shape: "void", public: false, capabilities: ["admin.access","roles.delete"] },
  "roles.get": { method: "GET", path: "/admin/roles/:id", status: 200, shape: "item", public: false, capabilities: ["admin.access","roles.read"] },
  "roles.getGrants": { method: "GET", path: "/admin/roles/:id/grants", status: 200, shape: "collection", public: false, capabilities: ["admin.access","roles.read"] },
  "roles.list": { method: "GET", path: "/admin/roles", status: 200, shape: "paginated", public: false, capabilities: ["admin.access","roles.read"] },
  "roles.reseed": { method: "POST", path: "/admin/roles/reseed", status: 200, shape: "item", public: false, capabilities: ["admin.access","roles.assign"] },
  "roles.update": { method: "PATCH", path: "/admin/roles/:id", status: 204, shape: "void", public: false, capabilities: ["admin.access","roles.update"] },
  "safety.block": { method: "POST", path: "/users/:id/block", status: 204, shape: "void", public: false, capabilities: [] },
  "safety.preferences": { method: "GET", path: "/me/privacy", status: 200, shape: "item", public: false, capabilities: [] },
  "safety.preferences.update": { method: "POST", path: "/me/privacy", status: 204, shape: "void", public: false, capabilities: [] },
  "safety.report": { method: "POST", path: "/reports", status: 200, shape: "item", public: false, capabilities: [] },
  "safety.unblock": { method: "DELETE", path: "/users/:id/block", status: 204, shape: "void", public: false, capabilities: [] },
  "sender.channels": { method: "GET", path: "/admin/sender/channels", status: 200, shape: "collection", public: false, capabilities: ["admin.access","sender.read"] },
  "sender.install": { method: "POST", path: "/admin/sender/install", status: 200, shape: "collection", public: false, capabilities: ["admin.access","sender.install"] },
  "sender.meta": { method: "GET", path: "/admin/sender/:type/meta", status: 200, shape: "item", public: false, capabilities: ["admin.access","sender.read"] },
  "sender.templates": { method: "GET", path: "/admin/sender/templates", status: 200, shape: "collection", public: false, capabilities: ["admin.access","sender.read"] },
  "sender.testChannel": { method: "POST", path: "/admin/sender/test-channel", status: 200, shape: "item", public: false, capabilities: ["admin.access","sender.test"] },
  "sender.types": { method: "GET", path: "/admin/sender/types", status: 200, shape: "collection", public: false, capabilities: ["admin.access","sender.read"] },
  "session.captcha": { method: "POST", path: "/session/captcha", status: 204, shape: "void", public: true, capabilities: [] },
  "session.check": { method: "GET", path: "/session/check", status: 200, shape: "item", public: false, capabilities: [] },
  "session.guest": { method: "POST", path: "/session/guest", status: 200, shape: "item", public: true, capabilities: [] },
  "session.login": { method: "POST", path: "/session/login", status: 200, shape: "item", public: true, capabilities: [] },
  "session.logout": { method: "POST", path: "/session/logout", status: 204, shape: "void", public: false, capabilities: [] },
  "session.policy": { method: "GET", path: "/session/policy", status: 200, shape: "item", public: true, capabilities: [] },
  "session.recovery.request": { method: "POST", path: "/session/recovery/request", status: 200, shape: "item", public: true, capabilities: [] },
  "session.recovery.reset": { method: "POST", path: "/session/recovery/reset", status: 204, shape: "void", public: true, capabilities: [] },
  "session.register": { method: "POST", path: "/session/register", status: 200, shape: "item", public: true, capabilities: [] },
  "social.follow": { method: "POST", path: "/users/:id/follow", status: 200, shape: "item", public: false, capabilities: [] },
  "social.followers": { method: "GET", path: "/users/:id/followers", status: 200, shape: "item", public: false, capabilities: [] },
  "social.following": { method: "GET", path: "/users/:id/following", status: 200, shape: "item", public: false, capabilities: [] },
  "social.profile": { method: "GET", path: "/users/by-handle/:handle", status: 200, shape: "item", public: true, capabilities: [] },
  "social.relation": { method: "GET", path: "/users/:id/relation", status: 200, shape: "item", public: false, capabilities: [] },
  "social.unfollow": { method: "DELETE", path: "/users/:id/follow", status: 204, shape: "void", public: false, capabilities: [] },
  "storage.channels": { method: "GET", path: "/admin/storage/channels", status: 200, shape: "collection", public: false, capabilities: ["admin.access","storage.read"] },
  "storage.channelStats": { method: "GET", path: "/admin/storage/channel-stats", status: 200, shape: "item", public: false, capabilities: ["admin.access","storage.read"] },
  "storage.install": { method: "POST", path: "/admin/storage/install", status: 200, shape: "collection", public: false, capabilities: ["admin.access","storage.install"] },
  "storage.meta": { method: "GET", path: "/admin/storage/:type/meta", status: 200, shape: "item", public: false, capabilities: ["admin.access","storage.read"] },
  "storage.testChannel": { method: "POST", path: "/admin/storage/test-channel", status: 200, shape: "item", public: false, capabilities: ["admin.access","storage.test"] },
  "storage.types": { method: "GET", path: "/admin/storage/types", status: 200, shape: "collection", public: false, capabilities: ["admin.access","storage.read"] },
  "system.update": { method: "GET", path: "/admin/system/update", status: 200, shape: "item", public: false, capabilities: ["admin.access","system.update"] },
  "tags.batchManaged": { method: "POST", path: "/admin/tags/batch", status: 204, shape: "void", public: false, capabilities: ["admin.access"] },
  "tags.create": { method: "POST", path: "/me/tags", status: 204, shape: "void", public: false, capabilities: ["tags.create"] },
  "tags.delete": { method: "DELETE", path: "/me/tags/:id", status: 204, shape: "void", public: false, capabilities: ["tags.delete","tags.delete"] },
  "tags.deleteManaged": { method: "DELETE", path: "/admin/tags/:id", status: 204, shape: "void", public: false, capabilities: ["admin.access","tags.delete"] },
  "tags.get": { method: "GET", path: "/tags/:id", status: 200, shape: "item", public: true, capabilities: [] },
  "tags.getManaged": { method: "GET", path: "/admin/tags/:id", status: 200, shape: "item", public: false, capabilities: ["admin.access","tags.read"] },
  "tags.list": { method: "GET", path: "/tags", status: 200, shape: "collection", public: true, capabilities: [] },
  "tags.listManaged": { method: "GET", path: "/admin/tags", status: 200, shape: "paginated", public: false, capabilities: ["admin.access","tags.read"] },
  "tags.listOwn": { method: "GET", path: "/me/tags", status: 200, shape: "paginated", public: false, capabilities: ["tags.read"] },
  "tags.update": { method: "PATCH", path: "/me/tags/:id", status: 204, shape: "void", public: false, capabilities: ["tags.update","tags.update"] },
  "tags.updateManaged": { method: "PATCH", path: "/admin/tags/:id", status: 204, shape: "void", public: false, capabilities: ["admin.access","tags.update"] },
  "theme.activate": { method: "POST", path: "/admin/theme/activate", status: 204, shape: "void", public: false, capabilities: ["admin.access","theme.activate"] },
  "theme.delete": { method: "DELETE", path: "/admin/theme/delete", status: 204, shape: "void", public: false, capabilities: ["admin.access","theme.delete"] },
  "theme.list": { method: "GET", path: "/admin/theme/list", status: 200, shape: "collection", public: false, capabilities: ["admin.access","theme.read"] },
  "theme.publicConfig": { method: "GET", path: "/theme/config", status: 200, shape: "item", public: true, capabilities: [] },
  "theme.updateConfig": { method: "PUT", path: "/admin/theme/config", status: 204, shape: "void", public: false, capabilities: ["admin.access","theme.update"] },
  "theme.upload": { method: "POST", path: "/admin/theme/upload", status: 200, shape: "item", public: false, capabilities: ["admin.access","theme.upload"] },
  "users.assignRoles": { method: "PATCH", path: "/admin/users/:id/roles", status: 204, shape: "void", public: false, capabilities: ["admin.access","users.roles.assign"] },
  "users.batchManaged": { method: "POST", path: "/admin/users/batch", status: 204, shape: "void", public: false, capabilities: ["admin.access"] },
  "users.deleteManaged": { method: "DELETE", path: "/admin/users/:id", status: 204, shape: "void", public: false, capabilities: ["admin.access","users.delete"] },
  "users.getManaged": { method: "GET", path: "/admin/users/:id", status: 200, shape: "item", public: false, capabilities: ["admin.access","users.read"] },
  "users.listManaged": { method: "GET", path: "/admin/users", status: 200, shape: "paginated", public: false, capabilities: ["admin.access","users.read"] },
  "users.me.email": { method: "POST", path: "/me/email", status: 204, shape: "void", public: false, capabilities: ["users.update"] },
  "users.me.emailCaptcha": { method: "POST", path: "/me/email-captcha", status: 200, shape: "item", public: false, capabilities: ["users.update"] },
  "users.me.get": { method: "GET", path: "/me", status: 200, shape: "item", public: false, capabilities: ["users.read"] },
  "users.me.password": { method: "POST", path: "/me/password", status: 204, shape: "void", public: false, capabilities: ["users.update"] },
  "users.me.update": { method: "PATCH", path: "/me", status: 204, shape: "void", public: false, capabilities: ["users.update"] },
  "users.recovery": { method: "GET", path: "/admin/recovery", status: 200, shape: "paginated", public: false, capabilities: ["admin.access","users.recovery.read"] },
  "users.revokeSession": { method: "POST", path: "/admin/users/:id/sessions/:sessionId/revoke", status: 204, shape: "void", public: false, capabilities: ["admin.access","users.sessions.revoke"] },
  "users.revokeSessions": { method: "POST", path: "/admin/users/:id/sessions/revoke-all", status: 204, shape: "void", public: false, capabilities: ["admin.access","users.sessions.revoke"] },
  "users.security": { method: "GET", path: "/admin/users/:id/security", status: 200, shape: "item", public: false, capabilities: ["admin.access","users.security.read"] },
  "users.sessions": { method: "GET", path: "/admin/users/:id/sessions", status: 200, shape: "collection", public: false, capabilities: ["admin.access","users.security.read"] },
  "users.updateManaged": { method: "PATCH", path: "/admin/users/:id", status: 204, shape: "void", public: false, capabilities: ["admin.access","users.update"] },
} as const satisfies Record<GeneratedOperationId, { method: string; path: string; status: number; shape: string; public: boolean; capabilities: readonly string[] }>

