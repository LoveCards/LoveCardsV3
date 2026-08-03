import type * as ApiTypes from '../types'

/** Generated from contracts/openapi.json. Do not edit manually. */
export interface GeneratedApiContract {
  "captcha.config": { method: "GET"; path: "/captcha/config"; request: {  }; response: ApiTypes.CaptchaConfig; status: 200; shape: "item"; public: true; capabilities: readonly string[] }
  "captcha.drivers": { method: "GET"; path: "/captcha/drivers"; request: {  }; response: ApiTypes.CaptchaDriver[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "captcha.install": { method: "POST"; path: "/captcha/install"; request: Record<string, unknown>; response: ApiTypes.CaptchaMeta[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "captcha.meta": { method: "GET"; path: "/captcha/:slug/meta"; request: {  }; response: ApiTypes.CaptchaDriver; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "captcha.types": { method: "GET"; path: "/captcha/types"; request: {  }; response: ApiTypes.CaptchaDriver[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "cards.batch": { method: "POST"; path: "/cards/batch"; request: { ids: unknown[]; method: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "cards.create": { method: "POST"; path: "/cards"; request: { pictures?: unknown[]; content: string; cover?: string; tags?: unknown[] }; response: ApiTypes.CreateResult; status: 201; shape: "item"; public: false; capabilities: readonly string[] }
  "cards.delete": { method: "DELETE"; path: "/cards/:id"; request: {  }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "cards.get": { method: "GET"; path: "/cards/:id"; request: {  }; response: ApiTypes.Card; status: 200; shape: "item"; public: true; capabilities: readonly string[] }
  "cards.hot": { method: "GET"; path: "/cards/hot"; request: {  }; response: ApiTypes.Card[]; status: 200; shape: "collection"; public: true; capabilities: readonly string[] }
  "cards.like": { method: "POST"; path: "/cards/:id/like"; request: Record<string, unknown>; response: { likes: number }; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "cards.list": { method: "GET"; path: "/cards"; request: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }; response: ApiTypes.ListResult<ApiTypes.Card>; status: 200; shape: "paginated"; public: true; capabilities: readonly string[] }
  "cards.listOwn": { method: "GET"; path: "/users/me/cards"; request: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }; response: ApiTypes.ListResult<ApiTypes.Card>; status: 200; shape: "paginated"; public: false; capabilities: readonly string[] }
  "cards.search": { method: "GET"; path: "/cards/search"; request: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }; response: ApiTypes.ListResult<ApiTypes.Card>; status: 200; shape: "paginated"; public: true; capabilities: readonly string[] }
  "cards.update": { method: "PATCH"; path: "/cards/:id"; request: { pictures?: unknown[]; content?: string; is_top?: number; status?: number; user_id?: number; comments?: number; views?: number; good?: number; cover?: string; tags?: unknown[] }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "comments.batch": { method: "POST"; path: "/comments/batch"; request: { ids: unknown[]; method: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "comments.cardList": { method: "GET"; path: "/cards/:id/comments"; request: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }; response: ApiTypes.ListResult<ApiTypes.Comment>; status: 200; shape: "paginated"; public: true; capabilities: readonly string[] }
  "comments.create": { method: "POST"; path: "/cards/:id/comments"; request: { parent_id?: number; content: string; data?: unknown[] }; response: ApiTypes.CreateResult; status: 201; shape: "item"; public: false; capabilities: readonly string[] }
  "comments.delete": { method: "DELETE"; path: "/comments/:id"; request: {  }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "comments.get": { method: "GET"; path: "/comments/:id"; request: {  }; response: ApiTypes.Comment; status: 200; shape: "item"; public: true; capabilities: readonly string[] }
  "comments.list": { method: "GET"; path: "/comments"; request: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }; response: ApiTypes.ListResult<ApiTypes.Comment>; status: 200; shape: "paginated"; public: false; capabilities: readonly string[] }
  "comments.listOwn": { method: "GET"; path: "/users/me/comments"; request: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }; response: ApiTypes.ListResult<ApiTypes.Comment>; status: 200; shape: "paginated"; public: false; capabilities: readonly string[] }
  "comments.update": { method: "PATCH"; path: "/comments/:id"; request: { aid?: number; pid?: number; is_top?: number; status?: number; user_id?: number; good?: number; content?: string; data?: unknown[] }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "config.delete": { method: "DELETE"; path: "/config"; request: {  }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "config.deleteKey": { method: "DELETE"; path: "/config/key"; request: {  }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "config.groups": { method: "GET"; path: "/config/groups"; request: {  }; response: ApiTypes.ConfigData; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "config.init": { method: "POST"; path: "/config/init"; request: Record<string, unknown>; response: ApiTypes.ConfigData; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "config.list": { method: "GET"; path: "/config"; request: {  }; response: ApiTypes.ConfigData; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "config.register": { method: "POST"; path: "/config/register"; request: Record<string, unknown>; response: ApiTypes.ConfigData; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "config.reload": { method: "POST"; path: "/config/reload"; request: Record<string, unknown>; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "config.update": { method: "POST"; path: "/config"; request: Record<string, unknown>; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "dashboard.index": { method: "GET"; path: "/dashboard"; request: {  }; response: ApiTypes.DashboardData; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "files.allDelete": { method: "DELETE"; path: "/files/:id"; request: {  }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "files.batch": { method: "POST"; path: "/files/batch"; request: { ids: unknown[]; method: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "files.cleanup": { method: "DELETE"; path: "/files/expired"; request: {  }; response: { cleaned: number }; status: 200; shape: "dynamic"; public: false; capabilities: readonly string[] }
  "files.confirm": { method: "PATCH"; path: "/files/:id/confirm"; request: Record<string, unknown>; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "files.direct": { method: "POST"; path: "/files/direct"; request: Record<string, unknown>; response: ApiTypes.DirectUploadResult; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "files.get": { method: "GET"; path: "/files/:id"; request: {  }; response: ApiTypes.LCFile; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "files.list": { method: "GET"; path: "/files"; request: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }; response: ApiTypes.ListResult<ApiTypes.LCFile>; status: 200; shape: "paginated"; public: false; capabilities: readonly string[] }
  "files.listOwn": { method: "GET"; path: "/users/me/files"; request: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }; response: ApiTypes.ListResult<ApiTypes.LCFile>; status: 200; shape: "paginated"; public: false; capabilities: readonly string[] }
  "files.upload": { method: "POST"; path: "/files"; request: Record<string, unknown>; response: ApiTypes.UploadResult; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "likes.list": { method: "GET"; path: "/likes"; request: {  }; response: ApiTypes.Like[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "likes.unlike": { method: "DELETE"; path: "/likes/:id"; request: {  }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "permissions.all": { method: "GET"; path: "/permissions/all"; request: {  }; response: ApiTypes.CapabilityItem[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "permissions.list": { method: "GET"; path: "/permissions"; request: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }; response: ApiTypes.ListResult<ApiTypes.CapabilityItem>; status: 200; shape: "paginated"; public: false; capabilities: readonly string[] }
  "roles.assignCapabilities": { method: "POST"; path: "/roles/:id/capabilities"; request: { capabilities: unknown[] }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "roles.create": { method: "POST"; path: "/roles"; request: { description?: string; name: string; slug: string }; response: Record<string, unknown>; status: 200; shape: "dynamic"; public: false; capabilities: readonly string[] }
  "roles.delete": { method: "DELETE"; path: "/roles/:id"; request: {  }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "roles.get": { method: "GET"; path: "/roles/:id"; request: {  }; response: ApiTypes.Role; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "roles.getRoleCapabilities": { method: "GET"; path: "/roles/:id/capabilities"; request: {  }; response: string[]; status: 200; shape: "dynamic"; public: false; capabilities: readonly string[] }
  "roles.list": { method: "GET"; path: "/roles"; request: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }; response: ApiTypes.ListResult<ApiTypes.Role>; status: 200; shape: "paginated"; public: false; capabilities: readonly string[] }
  "roles.reseed": { method: "POST"; path: "/roles/reseed"; request: Record<string, unknown>; response: Record<string, unknown>; status: 200; shape: "dynamic"; public: false; capabilities: readonly string[] }
  "roles.update": { method: "PATCH"; path: "/roles/:id"; request: { name?: string; slug?: string; description?: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "sender.channels": { method: "GET"; path: "/sender/channels"; request: {  }; response: ApiTypes.SenderChannel[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "sender.install": { method: "POST"; path: "/sender/install"; request: Record<string, unknown>; response: ApiTypes.SenderMeta[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "sender.meta": { method: "GET"; path: "/sender/:type/meta"; request: {  }; response: ApiTypes.SenderMeta; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "sender.templates": { method: "GET"; path: "/sender/templates"; request: {  }; response: ApiTypes.SenderTemplate[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "sender.testChannel": { method: "POST"; path: "/sender/test-channel"; request: Record<string, unknown>; response: { success: boolean; message?: string }; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "sender.types": { method: "GET"; path: "/sender/types"; request: {  }; response: ApiTypes.SenderType[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "session.captcha": { method: "POST"; path: "/session/captcha"; request: { account: string }; response: void; status: 204; shape: "void"; public: true; capabilities: readonly string[] }
  "session.check": { method: "GET"; path: "/session/check"; request: {  }; response: Record<string, unknown>; status: 200; shape: "dynamic"; public: false; capabilities: readonly string[] }
  "session.guest": { method: "POST"; path: "/session/guest"; request: Record<string, unknown>; response: ApiTypes.LoginResult; status: 200; shape: "item"; public: true; capabilities: readonly string[] }
  "session.login": { method: "POST"; path: "/session/login"; request: { account: string; password: string }; response: ApiTypes.LoginResult; status: 200; shape: "item"; public: true; capabilities: readonly string[] }
  "session.logout": { method: "POST"; path: "/session/logout"; request: Record<string, unknown>; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "session.register": { method: "POST"; path: "/session/register"; request: { account: string; password: string; code?: string }; response: ApiTypes.LoginResult; status: 200; shape: "item"; public: true; capabilities: readonly string[] }
  "storage.channels": { method: "GET"; path: "/storage/channels"; request: {  }; response: ApiTypes.StorageChannel[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "storage.channelStats": { method: "GET"; path: "/storage/channel-stats"; request: {  }; response: ApiTypes.ChannelStats; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "storage.install": { method: "POST"; path: "/storage/install"; request: Record<string, unknown>; response: ApiTypes.StorageMeta[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "storage.meta": { method: "GET"; path: "/storage/:type/meta"; request: {  }; response: ApiTypes.StorageMeta; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "storage.testChannel": { method: "POST"; path: "/storage/test-channel"; request: Record<string, unknown>; response: { success: boolean; message?: string }; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "storage.types": { method: "GET"; path: "/storage/types"; request: {  }; response: ApiTypes.StorageDriver[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "system.update": { method: "GET"; path: "/system/update"; request: {  }; response: ApiTypes.SystemUpdateInfo; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "tags.batch": { method: "POST"; path: "/tags/batch"; request: { ids: unknown[]; method: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "tags.create": { method: "POST"; path: "/tags"; request: { user_id?: string; name: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "tags.delete": { method: "DELETE"; path: "/tags/:id"; request: {  }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "tags.get": { method: "GET"; path: "/tags/:id"; request: {  }; response: ApiTypes.Tag; status: 200; shape: "item"; public: true; capabilities: readonly string[] }
  "tags.list": { method: "GET"; path: "/tags"; request: {  }; response: ApiTypes.Tag[]; status: 200; shape: "collection"; public: true; capabilities: readonly string[] }
  "tags.listAll": { method: "GET"; path: "/tags/all"; request: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }; response: ApiTypes.ListResult<ApiTypes.Tag>; status: 200; shape: "paginated"; public: false; capabilities: readonly string[] }
  "tags.update": { method: "PATCH"; path: "/tags/:id"; request: { aid?: number; user_id?: string; name?: string; status?: number }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "theme.activate": { method: "POST"; path: "/theme/activate"; request: Record<string, unknown>; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "theme.delete": { method: "DELETE"; path: "/theme/delete"; request: {  }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "theme.freeze": { method: "POST"; path: "/theme/freeze"; request: Record<string, unknown>; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "theme.list": { method: "GET"; path: "/theme/list"; request: {  }; response: ApiTypes.ThemeItem[]; status: 200; shape: "collection"; public: false; capabilities: readonly string[] }
  "theme.publicConfig": { method: "GET"; path: "/theme/config"; request: {  }; response: ApiTypes.ThemeConfigData; status: 200; shape: "item"; public: true; capabilities: readonly string[] }
  "theme.updateConfig": { method: "PUT"; path: "/theme/config"; request: Record<string, unknown>; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "theme.upload": { method: "POST"; path: "/theme/upload"; request: Record<string, unknown>; response: ApiTypes.ThemeItem; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "users.batch": { method: "POST"; path: "/users/batch"; request: { ids: unknown[]; method: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "users.delete": { method: "DELETE"; path: "/users/:id"; request: {  }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "users.get": { method: "GET"; path: "/users/:id"; request: {  }; response: ApiTypes.User; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "users.list": { method: "GET"; path: "/users"; request: { page?: number; list_rows?: number; search_keys?: string; search_like?: number; order_desc?: boolean; order_key?: string; search_value?: string }; response: ApiTypes.ListResult<ApiTypes.User>; status: 200; shape: "paginated"; public: false; capabilities: readonly string[] }
  "users.me.email": { method: "POST"; path: "/users/me/email"; request: { email: string; captcha?: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "users.me.emailCaptcha": { method: "POST"; path: "/users/me/email-captcha"; request: { email: string }; response: Record<string, unknown>; status: 200; shape: "dynamic"; public: false; capabilities: readonly string[] }
  "users.me.get": { method: "GET"; path: "/users/me"; request: {  }; response: ApiTypes.User; status: 200; shape: "item"; public: false; capabilities: readonly string[] }
  "users.me.password": { method: "POST"; path: "/users/me/password"; request: { password: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "users.me.update": { method: "PATCH"; path: "/users/me"; request: { avatar?: string; username?: string; password?: string }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
  "users.update": { method: "PATCH"; path: "/users/:id"; request: { avatar?: string; roles_id?: unknown[]; email?: string; phone?: string; username?: string; status?: number }; response: void; status: 204; shape: "void"; public: false; capabilities: readonly string[] }
}

export type GeneratedOperationId = keyof GeneratedApiContract
export type GeneratedRequest<K extends GeneratedOperationId> = GeneratedApiContract[K]['request']
export type GeneratedResponse<K extends GeneratedOperationId> = GeneratedApiContract[K]['response']

export const generatedApiContract = {
  "captcha.config": { method: "GET", path: "/captcha/config", status: 200, shape: "item", public: true, capabilities: [] },
  "captcha.drivers": { method: "GET", path: "/captcha/drivers", status: 200, shape: "collection", public: false, capabilities: ["captcha.read"] },
  "captcha.install": { method: "POST", path: "/captcha/install", status: 200, shape: "collection", public: false, capabilities: ["captcha.install"] },
  "captcha.meta": { method: "GET", path: "/captcha/:slug/meta", status: 200, shape: "item", public: false, capabilities: ["captcha.read"] },
  "captcha.types": { method: "GET", path: "/captcha/types", status: 200, shape: "collection", public: false, capabilities: ["captcha.read"] },
  "cards.batch": { method: "POST", path: "/cards/batch", status: 204, shape: "void", public: false, capabilities: [] },
  "cards.create": { method: "POST", path: "/cards", status: 201, shape: "item", public: false, capabilities: ["cards.create"] },
  "cards.delete": { method: "DELETE", path: "/cards/:id", status: 204, shape: "void", public: false, capabilities: ["cards.delete","cards.delete.all"] },
  "cards.get": { method: "GET", path: "/cards/:id", status: 200, shape: "item", public: true, capabilities: [] },
  "cards.hot": { method: "GET", path: "/cards/hot", status: 200, shape: "collection", public: true, capabilities: [] },
  "cards.like": { method: "POST", path: "/cards/:id/like", status: 200, shape: "item", public: false, capabilities: ["likes.create"] },
  "cards.list": { method: "GET", path: "/cards", status: 200, shape: "paginated", public: true, capabilities: [] },
  "cards.listOwn": { method: "GET", path: "/users/me/cards", status: 200, shape: "paginated", public: false, capabilities: [] },
  "cards.search": { method: "GET", path: "/cards/search", status: 200, shape: "paginated", public: true, capabilities: [] },
  "cards.update": { method: "PATCH", path: "/cards/:id", status: 204, shape: "void", public: false, capabilities: ["cards.update","cards.update.all"] },
  "comments.batch": { method: "POST", path: "/comments/batch", status: 204, shape: "void", public: false, capabilities: [] },
  "comments.cardList": { method: "GET", path: "/cards/:id/comments", status: 200, shape: "paginated", public: true, capabilities: [] },
  "comments.create": { method: "POST", path: "/cards/:id/comments", status: 201, shape: "item", public: false, capabilities: ["comments.create"] },
  "comments.delete": { method: "DELETE", path: "/comments/:id", status: 204, shape: "void", public: false, capabilities: ["comments.delete","comments.delete.all"] },
  "comments.get": { method: "GET", path: "/comments/:id", status: 200, shape: "item", public: true, capabilities: [] },
  "comments.list": { method: "GET", path: "/comments", status: 200, shape: "paginated", public: false, capabilities: ["comments.read","comments.read.all"] },
  "comments.listOwn": { method: "GET", path: "/users/me/comments", status: 200, shape: "paginated", public: false, capabilities: [] },
  "comments.update": { method: "PATCH", path: "/comments/:id", status: 204, shape: "void", public: false, capabilities: ["comments.update","comments.update.all"] },
  "config.delete": { method: "DELETE", path: "/config", status: 204, shape: "void", public: false, capabilities: ["config.deleteKey"] },
  "config.deleteKey": { method: "DELETE", path: "/config/key", status: 204, shape: "void", public: false, capabilities: ["config.deleteKey"] },
  "config.groups": { method: "GET", path: "/config/groups", status: 200, shape: "item", public: false, capabilities: ["config.read"] },
  "config.init": { method: "POST", path: "/config/init", status: 200, shape: "item", public: false, capabilities: ["config.init"] },
  "config.list": { method: "GET", path: "/config", status: 200, shape: "item", public: false, capabilities: ["config.read"] },
  "config.register": { method: "POST", path: "/config/register", status: 200, shape: "item", public: false, capabilities: ["config.register"] },
  "config.reload": { method: "POST", path: "/config/reload", status: 204, shape: "void", public: false, capabilities: ["config.reload"] },
  "config.update": { method: "POST", path: "/config", status: 204, shape: "void", public: false, capabilities: ["config.update"] },
  "dashboard.index": { method: "GET", path: "/dashboard", status: 200, shape: "item", public: false, capabilities: ["dashboard.read"] },
  "files.allDelete": { method: "DELETE", path: "/files/:id", status: 204, shape: "void", public: false, capabilities: ["files.delete","files.delete.all"] },
  "files.batch": { method: "POST", path: "/files/batch", status: 204, shape: "void", public: false, capabilities: ["files.update","files.update.all","files.delete","files.delete.all"] },
  "files.cleanup": { method: "DELETE", path: "/files/expired", status: 200, shape: "dynamic", public: false, capabilities: ["files.delete.all"] },
  "files.confirm": { method: "PATCH", path: "/files/:id/confirm", status: 204, shape: "void", public: false, capabilities: ["files.upload"] },
  "files.direct": { method: "POST", path: "/files/direct", status: 200, shape: "item", public: false, capabilities: ["files.upload"] },
  "files.get": { method: "GET", path: "/files/:id", status: 200, shape: "item", public: false, capabilities: ["files.read","files.read.all"] },
  "files.list": { method: "GET", path: "/files", status: 200, shape: "paginated", public: false, capabilities: ["files.read","files.read.all"] },
  "files.listOwn": { method: "GET", path: "/users/me/files", status: 200, shape: "paginated", public: false, capabilities: [] },
  "files.upload": { method: "POST", path: "/files", status: 200, shape: "item", public: false, capabilities: ["files.upload"] },
  "likes.list": { method: "GET", path: "/likes", status: 200, shape: "collection", public: false, capabilities: ["likes.read"] },
  "likes.unlike": { method: "DELETE", path: "/likes/:id", status: 204, shape: "void", public: false, capabilities: ["likes.delete"] },
  "permissions.all": { method: "GET", path: "/permissions/all", status: 200, shape: "collection", public: false, capabilities: ["permissions.read"] },
  "permissions.list": { method: "GET", path: "/permissions", status: 200, shape: "paginated", public: false, capabilities: ["permissions.read"] },
  "roles.assignCapabilities": { method: "POST", path: "/roles/:id/capabilities", status: 204, shape: "void", public: false, capabilities: ["roles.assign"] },
  "roles.create": { method: "POST", path: "/roles", status: 200, shape: "dynamic", public: false, capabilities: ["roles.create"] },
  "roles.delete": { method: "DELETE", path: "/roles/:id", status: 204, shape: "void", public: false, capabilities: ["roles.delete"] },
  "roles.get": { method: "GET", path: "/roles/:id", status: 200, shape: "item", public: false, capabilities: ["roles.read"] },
  "roles.getRoleCapabilities": { method: "GET", path: "/roles/:id/capabilities", status: 200, shape: "dynamic", public: false, capabilities: ["roles.read"] },
  "roles.list": { method: "GET", path: "/roles", status: 200, shape: "paginated", public: false, capabilities: ["roles.read"] },
  "roles.reseed": { method: "POST", path: "/roles/reseed", status: 200, shape: "dynamic", public: false, capabilities: ["roles.assign"] },
  "roles.update": { method: "PATCH", path: "/roles/:id", status: 204, shape: "void", public: false, capabilities: ["roles.update"] },
  "sender.channels": { method: "GET", path: "/sender/channels", status: 200, shape: "collection", public: false, capabilities: ["sender.read"] },
  "sender.install": { method: "POST", path: "/sender/install", status: 200, shape: "collection", public: false, capabilities: ["sender.install"] },
  "sender.meta": { method: "GET", path: "/sender/:type/meta", status: 200, shape: "item", public: false, capabilities: ["sender.read"] },
  "sender.templates": { method: "GET", path: "/sender/templates", status: 200, shape: "collection", public: false, capabilities: ["sender.read"] },
  "sender.testChannel": { method: "POST", path: "/sender/test-channel", status: 200, shape: "item", public: false, capabilities: ["sender.test"] },
  "sender.types": { method: "GET", path: "/sender/types", status: 200, shape: "collection", public: false, capabilities: ["sender.read"] },
  "session.captcha": { method: "POST", path: "/session/captcha", status: 204, shape: "void", public: true, capabilities: [] },
  "session.check": { method: "GET", path: "/session/check", status: 200, shape: "dynamic", public: false, capabilities: [] },
  "session.guest": { method: "POST", path: "/session/guest", status: 200, shape: "item", public: true, capabilities: [] },
  "session.login": { method: "POST", path: "/session/login", status: 200, shape: "item", public: true, capabilities: [] },
  "session.logout": { method: "POST", path: "/session/logout", status: 204, shape: "void", public: false, capabilities: [] },
  "session.register": { method: "POST", path: "/session/register", status: 200, shape: "item", public: true, capabilities: [] },
  "storage.channels": { method: "GET", path: "/storage/channels", status: 200, shape: "collection", public: false, capabilities: ["storage.read"] },
  "storage.channelStats": { method: "GET", path: "/storage/channel-stats", status: 200, shape: "item", public: false, capabilities: ["storage.read"] },
  "storage.install": { method: "POST", path: "/storage/install", status: 200, shape: "collection", public: false, capabilities: ["storage.install"] },
  "storage.meta": { method: "GET", path: "/storage/:type/meta", status: 200, shape: "item", public: false, capabilities: ["storage.read"] },
  "storage.testChannel": { method: "POST", path: "/storage/test-channel", status: 200, shape: "item", public: false, capabilities: ["storage.test"] },
  "storage.types": { method: "GET", path: "/storage/types", status: 200, shape: "collection", public: false, capabilities: ["storage.read"] },
  "system.update": { method: "GET", path: "/system/update", status: 200, shape: "item", public: false, capabilities: ["system.update"] },
  "tags.batch": { method: "POST", path: "/tags/batch", status: 204, shape: "void", public: false, capabilities: [] },
  "tags.create": { method: "POST", path: "/tags", status: 204, shape: "void", public: false, capabilities: ["tags.create"] },
  "tags.delete": { method: "DELETE", path: "/tags/:id", status: 204, shape: "void", public: false, capabilities: ["tags.delete","tags.delete.all"] },
  "tags.get": { method: "GET", path: "/tags/:id", status: 200, shape: "item", public: true, capabilities: [] },
  "tags.list": { method: "GET", path: "/tags", status: 200, shape: "collection", public: true, capabilities: [] },
  "tags.listAll": { method: "GET", path: "/tags/all", status: 200, shape: "paginated", public: false, capabilities: ["tags.read","tags.read.all"] },
  "tags.update": { method: "PATCH", path: "/tags/:id", status: 204, shape: "void", public: false, capabilities: ["tags.update","tags.update.all"] },
  "theme.activate": { method: "POST", path: "/theme/activate", status: 204, shape: "void", public: false, capabilities: ["theme.activate"] },
  "theme.delete": { method: "DELETE", path: "/theme/delete", status: 204, shape: "void", public: false, capabilities: ["theme.delete"] },
  "theme.freeze": { method: "POST", path: "/theme/freeze", status: 204, shape: "void", public: false, capabilities: ["theme.freeze"] },
  "theme.list": { method: "GET", path: "/theme/list", status: 200, shape: "collection", public: false, capabilities: ["theme.read"] },
  "theme.publicConfig": { method: "GET", path: "/theme/config", status: 200, shape: "item", public: true, capabilities: [] },
  "theme.updateConfig": { method: "PUT", path: "/theme/config", status: 204, shape: "void", public: false, capabilities: ["theme.update"] },
  "theme.upload": { method: "POST", path: "/theme/upload", status: 200, shape: "item", public: false, capabilities: ["theme.upload"] },
  "users.batch": { method: "POST", path: "/users/batch", status: 204, shape: "void", public: false, capabilities: [] },
  "users.delete": { method: "DELETE", path: "/users/:id", status: 204, shape: "void", public: false, capabilities: ["users.delete","users.delete.all"] },
  "users.get": { method: "GET", path: "/users/:id", status: 200, shape: "item", public: false, capabilities: ["users.read","users.read.all"] },
  "users.list": { method: "GET", path: "/users", status: 200, shape: "paginated", public: false, capabilities: ["users.read","users.read.all"] },
  "users.me.email": { method: "POST", path: "/users/me/email", status: 204, shape: "void", public: false, capabilities: [] },
  "users.me.emailCaptcha": { method: "POST", path: "/users/me/email-captcha", status: 200, shape: "dynamic", public: false, capabilities: [] },
  "users.me.get": { method: "GET", path: "/users/me", status: 200, shape: "item", public: false, capabilities: [] },
  "users.me.password": { method: "POST", path: "/users/me/password", status: 204, shape: "void", public: false, capabilities: [] },
  "users.me.update": { method: "PATCH", path: "/users/me", status: 204, shape: "void", public: false, capabilities: [] },
  "users.update": { method: "PATCH", path: "/users/:id", status: 204, shape: "void", public: false, capabilities: ["users.update","users.update.all"] },
} as const satisfies Record<GeneratedOperationId, { method: string; path: string; status: number; shape: string; public: boolean; capabilities: readonly string[] }>

