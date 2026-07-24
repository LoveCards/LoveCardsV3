(function(global, factory) {
  typeof exports === "object" && typeof module !== "undefined" ? factory(exports, require("axios")) : typeof define === "function" && define.amd ? define(["exports", "axios"], factory) : (global = typeof globalThis !== "undefined" ? globalThis : global || self, factory(global.LC = {}, global.axios));
})(this, (function(exports2, axios) {
  "use strict";
  class ApiError extends Error {
    constructor(code, message, status, details) {
      super(message);
      this.code = code;
      this.status = status;
      this.details = details;
      this.name = "ApiError";
    }
    static from(error) {
      var _a, _b;
      if (error instanceof ApiError) return error;
      if (error instanceof axios.AxiosError) {
        const { response } = error;
        if (!response) {
          if (error.code === "ECONNABORTED") return new ApiError(-2, "请求超时，请稍后重试", 0);
          if (error.code === "ERR_NETWORK") return new ApiError(-3, "网络连接失败，请检查网络设置", 0);
          if (error.code === "ERR_CANCELED") return new ApiError(-4, "请求已取消", 0);
          return new ApiError(-1, error.message || "网络错误", 0);
        }
        const { status, data } = response;
        if (((_a = data == null ? void 0 : data.error) == null ? void 0 : _a.code) && ((_b = data == null ? void 0 : data.error) == null ? void 0 : _b.message)) {
          return new ApiError(data.error.code, data.error.message, status, data.error.details);
        }
        if ((data == null ? void 0 : data.code) && (data == null ? void 0 : data.message)) return new ApiError(data.code, data.message, status);
        if (data == null ? void 0 : data.error) return new ApiError(1, data.error, status);
        const msgs = {
          400: "请求参数错误",
          401: "未授权，请重新登录",
          403: "权限不足",
          404: "资源不存在",
          405: "请求方法不允许",
          408: "请求超时",
          409: "资源冲突",
          422: "参数验证失败",
          429: "请求过于频繁",
          500: "服务器内部错误",
          502: "网关错误",
          503: "服务不可用",
          504: "网关超时"
        };
        return new ApiError(status, msgs[status] || `请求失败 (${status})`, status);
      }
      if (error instanceof Error) return new ApiError(-1, error.message, 0);
      return new ApiError(-1, "未知错误", 0);
    }
  }
  function isApiError(error) {
    return error instanceof ApiError;
  }
  class Deduplicator {
    constructor() {
      this._pending = /* @__PURE__ */ new Map();
    }
    execute(key, factory) {
      if (this._pending.has(key)) return this._pending.get(key);
      const promise = factory().finally(() => this._pending.delete(key));
      this._pending.set(key, promise);
      return promise;
    }
  }
  const defaultTokenStore = {
    get: () => {
      if (typeof localStorage !== "undefined") return localStorage.getItem("token");
      return null;
    },
    set: (token) => {
      if (typeof localStorage !== "undefined") localStorage.setItem("token", token);
    },
    clear: () => {
      if (typeof localStorage !== "undefined") localStorage.removeItem("token");
    }
  };
  const defaultConfig = {
    timeout: 1e4
  };
  function methodKey(method, url, params) {
    return `${method}:${url}:${JSON.stringify(params ?? {})}`;
  }
  class BaseResource {
    constructor(instance, opts) {
      this._instance = instance;
      this._opts = opts;
    }
    // ─── 请求方法 ───
    /**
     * 序列化 GET/DELETE 参数中的数组为 JSON 字符串
     * 后端 ValidateExtend::paramsJsonToArray() 用 json_decode 接收，期望 JSON 字符串
     */
    _serializeParams(params) {
      if (!params || typeof params !== "object" || Array.isArray(params)) return params;
      const out = {};
      for (const [k, v] of Object.entries(params)) {
        if (Array.isArray(v)) {
          out[k] = JSON.stringify(v);
        } else if (k === "order_desc") {
          out[k] = v === true || v === "true" ? "true" : "false";
        } else {
          out[k] = v;
        }
      }
      return out;
    }
    async _get(url, params, signal) {
      const serialized = this._serializeParams(params);
      const key = methodKey("GET", url, serialized);
      return this._opts.dedupe.execute(
        key,
        () => this._request("GET", url, { params: serialized, signal })
      );
    }
    async _post(url, data, config) {
      return this._request("POST", url, { ...config, data });
    }
    async _patch(url, data) {
      return this._request("PATCH", url, { data });
    }
    async _put(url, data) {
      return this._request("PUT", url, { data });
    }
    async _delete(url, params) {
      return this._request("DELETE", url, { params: this._serializeParams(params) });
    }
    // ─── 内部请求逻辑 ───
    async _request(method, url, config) {
      const maxRetries = this._opts.retry.maxRetries ?? 0;
      const retryOn = this._opts.retry.retryOn ?? [];
      const retryDelay = this._opts.retry.retryDelay ?? 1e3;
      const requestId = Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
      const startTime = Date.now();
      let lastError;
      for (let attempt = 0; attempt <= maxRetries; attempt++) {
        const ctx = {
          requestId,
          method,
          url,
          startTime,
          retryCount: attempt,
          config: { headers: config.headers ?? {} }
        };
        try {
          for (const fn of [...this._opts.hooks.beforeRequest]) {
            await fn(ctx);
          }
        } catch (hookErr) {
          throw ApiError.from(hookErr);
        }
        try {
          if (this._opts.debug) {
            console.log(`[LC] ${method} ${url}`, config.data ?? config.params ?? "");
          }
          const response = await this._instance.request({
            method,
            url,
            ...config,
            headers: { ...config.headers, ...ctx.config.headers }
          });
          if (this._opts.debug) {
            console.log(`[LC] ${response.status}`, response.data);
          }
          const result = this._unwrap(response);
          const elapsedMs = Date.now() - startTime;
          for (const fn of [...this._opts.hooks.afterResponse]) {
            try {
              await fn({ ...ctx, status: response.status, data: result, elapsedMs });
            } catch {
            }
          }
          return result;
        } catch (err) {
          lastError = err;
          const apiErr = ApiError.from(err);
          const elapsedMs = Date.now() - startTime;
          if (this._opts.debug) {
            console.log(`[LC] ERROR ${apiErr.status}`, apiErr.message);
          }
          let reason = "http";
          if (apiErr.status === 0) {
            if (apiErr.code === -2) reason = "timeout";
            else if (apiErr.code === -3) reason = "network";
            else if (apiErr.code === -4) reason = "cancelled";
          }
          const isRetryable = retryOn.includes(apiErr.status);
          const willRetry = attempt < maxRetries && isRetryable;
          for (const fn of [...this._opts.hooks.onError]) {
            try {
              await fn({
                ...ctx,
                status: apiErr.status,
                message: apiErr.message,
                code: apiErr.code,
                elapsedMs,
                isRetryable,
                willRetry,
                reason
              });
            } catch {
            }
          }
          if (willRetry) {
            await new Promise((r) => setTimeout(r, retryDelay * (attempt + 1)));
            continue;
          }
          throw apiErr;
        }
      }
      throw ApiError.from(lastError);
    }
    // ─── 响应解包 ───
    _unwrap(response) {
      const body = response.data;
      const status = response.status;
      if (status === 204) return void 0;
      if (typeof body !== "object" || body === null) return body;
      if ("data" in body) {
        if (body.data === null && status === 201) {
          return { id: null };
        }
        if ("pagination" in body && body.pagination) {
          return {
            data: body.data,
            pagination: body.pagination
          };
        }
        return body.data;
      }
      return body;
    }
  }
  class Session extends BaseResource {
    login(data) {
      return this._post("/session/login", data);
    }
    register(data) {
      return this._post("/session/register", data);
    }
    guest() {
      return this._post("/session/guest");
    }
    logout() {
      return this._post("/session/logout");
    }
    captcha(params) {
      return this._post("/session/captcha", params);
    }
    check() {
      return this._get("/session/check");
    }
  }
  class Cards extends BaseResource {
    list(params) {
      return this._get("/cards", params);
    }
    get(id) {
      return this._get(`/cards/${id}`);
    }
    hot() {
      return this._get("/cards/hot");
    }
    search(params) {
      return this._get("/cards/search", params);
    }
    create(data) {
      return this._post("/cards", data);
    }
    update(id, data) {
      return this._patch(`/cards/${id}`, data);
    }
    delete(id) {
      return this._delete(`/cards/${id}`);
    }
    like(id) {
      return this._post(`/cards/${id}/like`);
    }
    listOwn(params) {
      return this._get("/users/me/cards", params);
    }
    listMe(params) {
      return this.listOwn(params);
    }
    batch(data) {
      return this._post("/cards/batch", data);
    }
  }
  class Users extends BaseResource {
    me() {
      return this._get("/users/me");
    }
    updateMe(data) {
      return this._patch("/users/me", data);
    }
    updatePassword(data) {
      return this._post("/users/me/password", data);
    }
    updateEmail(data) {
      return this._post("/users/me/email", data);
    }
    emailCaptcha(data) {
      return this._post("/users/me/email-captcha", data);
    }
    list(params) {
      return this._get("/users", params);
    }
    get(id) {
      return this._get(`/users/${id}`);
    }
    update(id, data) {
      return this._patch(`/users/${id}`, data);
    }
    delete(id) {
      return this._delete(`/users/${id}`);
    }
    batch(data) {
      return this._post("/users/batch", data);
    }
  }
  class Comments extends BaseResource {
    list(params) {
      return this._get("/comments", params);
    }
    cardList(cardId, params) {
      return this._get(`/cards/${cardId}/comments`, params);
    }
    create(cardId, data) {
      return this._post(`/cards/${cardId}/comments`, data);
    }
    get(id) {
      return this._get(`/comments/${id}`);
    }
    update(id, data) {
      return this._patch(`/comments/${id}`, data);
    }
    delete(id) {
      return this._delete(`/comments/${id}`);
    }
    listOwn(params) {
      return this._get("/users/me/comments", params);
    }
    listMe(params) {
      return this.listOwn(params);
    }
    batch(data) {
      return this._post("/comments/batch", data);
    }
  }
  class Tags extends BaseResource {
    list(params) {
      return this._get("/tags", params);
    }
    listAll(params) {
      return this._get("/tags/all", params);
    }
    get(id) {
      return this._get(`/tags/${id}`);
    }
    create(data) {
      return this._post("/tags", data);
    }
    update(id, data) {
      return this._patch(`/tags/${id}`, data);
    }
    delete(id) {
      return this._delete(`/tags/${id}`);
    }
    batch(data) {
      return this._post("/tags/batch", data);
    }
  }
  class Likes extends BaseResource {
    list(params) {
      return this._get("/likes", params);
    }
    unlike(id) {
      return this._delete(`/likes/${id}`);
    }
  }
  class Files extends BaseResource {
    upload(formData) {
      return this._post("/files", formData, {
        headers: { "Content-Type": "multipart/form-data" }
      });
    }
    list(params) {
      return this._get("/files", params);
    }
    get(id) {
      return this._get(`/files/${id}`);
    }
    direct(data) {
      return this._post("/files/direct", data);
    }
    confirm(id) {
      return this._patch(`/files/${id}/confirm`);
    }
    /**
     * 获取当前用户的文件列表（严格本人）
     * @param params 分页参数
     */
    listOwn(params) {
      return this._get("/users/me/files", params);
    }
    /**
     * @deprecated 请使用 listOwn() 替代
     */
    listMe(params) {
      return this.listOwn(params);
    }
    batch(data) {
      return this._post("/files/batch", data);
    }
    cleanup() {
      return this._delete("/files/expired");
    }
    delete(id) {
      return this._delete(`/files/${id}`);
    }
  }
  class Theme extends BaseResource {
    tags() {
      return this._get("/theme/tags");
    }
    list() {
      return this._get("/theme/list");
    }
    upload(formData) {
      return this._post("/theme/upload", formData, {
        headers: { "Content-Type": "multipart/form-data" }
      });
    }
    activate(data) {
      return this._post("/theme/activate", data);
    }
    config() {
      return this._get("/theme/config");
    }
    updateConfig(data) {
      return this._patch("/theme/config", data);
    }
    freeze() {
      return this._post("/theme/freeze");
    }
    delete(data) {
      return this._delete("/theme/delete", data);
    }
    publicConfig() {
      return this._get("/theme/config");
    }
  }
  class Roles extends BaseResource {
    list(params) {
      return this._get("/roles", params);
    }
    get(id) {
      return this._get(`/roles/${id}`);
    }
    create(data) {
      return this._post("/roles", data);
    }
    update(id, data) {
      return this._patch(`/roles/${id}`, data);
    }
    delete(id) {
      return this._delete(`/roles/${id}`);
    }
    getCapabilities(id) {
      return this._get(`/roles/${id}/capabilities`);
    }
    assignCapabilities(id, data) {
      return this._post(`/roles/${id}/capabilities`, data);
    }
    reseed() {
      return this._post("/roles/reseed");
    }
  }
  class Permissions extends BaseResource {
    list(params) {
      return this._get("/permissions", params);
    }
    all() {
      return this._get("/permissions/all");
    }
  }
  class Config extends BaseResource {
    list() {
      return this._get("/config");
    }
    update(data) {
      return this._post("/config", data);
    }
    groups() {
      return this._get("/config/groups");
    }
    init() {
      return this._post("/config/init");
    }
    register(data) {
      return this._post("/config/register", data);
    }
    reload() {
      return this._post("/config/reload");
    }
    deleteGroup(group) {
      return this._delete("/config", { group });
    }
    deleteKey(group, key) {
      return this._delete("/config/key", { group, key });
    }
  }
  class Dashboard extends BaseResource {
    index() {
      return this._get("/dashboard");
    }
  }
  class Storage extends BaseResource {
    types() {
      return this._get("/storage/types");
    }
    meta(type) {
      return this._get(`/storage/${type}/meta`);
    }
    install() {
      return this._post("/storage/install");
    }
    channels() {
      return this._get("/storage/channels");
    }
    testChannel(data) {
      return this._post("/storage/test-channel", data);
    }
    channelStats() {
      return this._get("/storage/channel-stats");
    }
  }
  class Sender extends BaseResource {
    types() {
      return this._get("/sender/types");
    }
    meta(type) {
      return this._get(`/sender/${type}/meta`);
    }
    install() {
      return this._post("/sender/install");
    }
    channels() {
      return this._get("/sender/channels");
    }
    templates() {
      return this._get("/sender/templates");
    }
    testChannel(data) {
      return this._post("/sender/test-channel", data);
    }
  }
  class Captcha extends BaseResource {
    types() {
      return this._get("/captcha/types");
    }
    drivers() {
      return this._get("/captcha/drivers");
    }
    meta(slug) {
      return this._get(`/captcha/${slug}/meta`);
    }
    install() {
      return this._post("/captcha/install");
    }
    config() {
      return this._get("/captcha/config");
    }
  }
  class System extends BaseResource {
    update() {
      return this._get("/system/update");
    }
  }
  function createClient(config) {
    var _a, _b, _c;
    const tokenStore = config.tokenStore ?? defaultTokenStore;
    const timeout = config.timeout ?? defaultConfig.timeout;
    const debug = config.debug ?? false;
    const retry = config.retry ?? {};
    const instance = axios.create({
      baseURL: config.apiUrl,
      timeout,
      headers: { "Content-Type": "application/json" }
    });
    let currentRole = config.defaultRole ?? null;
    instance.interceptors.request.use((cfg) => {
      const token = tokenStore.get();
      if (token) {
        cfg.headers.Authorization = `Bearer ${token}`;
        cfg.headers["X-Token"] = token;
      }
      if (currentRole) {
        cfg.headers["X-Role"] = currentRole;
      }
      return cfg;
    });
    instance.interceptors.response.use(
      (response) => response,
      (error) => {
        const apiErr = ApiError.from(error);
        if (apiErr.status === 401 && config.onAuthError) config.onAuthError();
        return Promise.reject(apiErr);
      }
    );
    const opts = {
      tokenStore,
      debug,
      retry,
      dedupe: new Deduplicator(),
      hooks: {
        beforeRequest: ((_a = config.hooks) == null ? void 0 : _a.beforeRequest) ? [config.hooks.beforeRequest] : [],
        afterResponse: ((_b = config.hooks) == null ? void 0 : _b.afterResponse) ? [config.hooks.afterResponse] : [],
        onError: ((_c = config.hooks) == null ? void 0 : _c.onError) ? [config.hooks.onError] : []
      }
    };
    return new LCClientImpl(instance, opts, tokenStore, () => currentRole, (r) => {
      currentRole = r;
    });
  }
  class LCClientImpl {
    constructor(instance, opts, tokenStore, getRole, setRole) {
      this._tokenStore = tokenStore;
      this._getRole = getRole;
      this._setRole = setRole;
      this.session = new Session(instance, opts);
      this.cards = new Cards(instance, opts);
      this.users = new Users(instance, opts);
      this.comments = new Comments(instance, opts);
      this.tags = new Tags(instance, opts);
      this.likes = new Likes(instance, opts);
      this.files = new Files(instance, opts);
      this.theme = new Theme(instance, opts);
      this.roles = new Roles(instance, opts);
      this.permissions = new Permissions(instance, opts);
      this.config = new Config(instance, opts);
      this.dashboard = new Dashboard(instance, opts);
      this.storage = new Storage(instance, opts);
      this.sender = new Sender(instance, opts);
      this.captcha = new Captcha(instance, opts);
      this.system = new System(instance, opts);
      const hookStore = opts.hooks;
      this.hooks = {
        beforeRequest(fn) {
          hookStore.beforeRequest.push(fn);
          return () => {
            const idx = hookStore.beforeRequest.indexOf(fn);
            if (idx >= 0) hookStore.beforeRequest.splice(idx, 1);
          };
        },
        afterResponse(fn) {
          hookStore.afterResponse.push(fn);
          return () => {
            const idx = hookStore.afterResponse.indexOf(fn);
            if (idx >= 0) hookStore.afterResponse.splice(idx, 1);
          };
        },
        onError(fn) {
          hookStore.onError.push(fn);
          return () => {
            const idx = hookStore.onError.indexOf(fn);
            if (idx >= 0) hookStore.onError.splice(idx, 1);
          };
        }
      };
    }
    setToken(token) {
      this._tokenStore.set(token);
    }
    clearToken() {
      this._tokenStore.clear();
    }
    getToken() {
      return this._tokenStore.get();
    }
    setRole(slug) {
      this._setRole(slug);
    }
    getRole() {
      return this._getRole();
    }
  }
  const PUBLIC_API = {
    "cards.hot": { method: "GET", path: "/api/cards/hot" },
    "cards.list": { method: "GET", path: "/api/cards" },
    "cards.get": { method: "GET", path: "/api/cards/:id" },
    "cards.search": { method: "GET", path: "/api/cards/search" },
    "tags.list": { method: "GET", path: "/api/tags" },
    "tags.get": { method: "GET", path: "/api/tags/:id" },
    "comments.list": { method: "GET", path: "/api/cards/:id/comments" },
    "users.me": { method: "GET", path: "/api/users/me" },
    "system.theme": { method: "GET", path: "/api/theme/config" },
    "captcha.config": { method: "GET", path: "/api/captcha/config" }
  };
  exports2.ApiError = ApiError;
  exports2.PUBLIC_API = PUBLIC_API;
  exports2.createClient = createClient;
  exports2.isApiError = isApiError;
  Object.defineProperty(exports2, Symbol.toStringTag, { value: "Module" });
}));
