# SSE 与滑块验证收敛方案（2026-11-08）

> 本文的 SSE 通信设计是历史方案；2026-11-10 起，通信模式、故障处理和信号投递以 [FRONTEND-SIGNALS.md](FRONTEND-SIGNALS.md) 为准。滑块验证部分不受此替代说明影响。

状态：滑块验证调用链已在 dev 分支实现，仍需 CI 与 dev 真实验收；未配置极验 ID/Key 时不得标记为在线可用。通信部分按上方新契约执行。

## 1. SSE：保留独立 Gateway，移除旧 PHP 伪端点

### 当前基线

- 合并前 `main` 的 `GET /me/notifications/stream` 是单次 PHP 响应，帧中的 `\n` 是字面量；它既不能形成有效的持续 SSE，也没有主题消费方。
- #52 已将 #48 + #50 的通信运行时链合入 `main`，删除旧路由及 SDK 方法，使用 `apps/realtime-gateway` 的 `GET /stream?ticket=`。Gateway 负责连接与 SSE 帧，业务后端负责权限、一次性 ticket、持久化和补读。主题 `communication-runtime.js` 统一调度通知、私信与未读状态。
- #49 是另一套 Gateway 协议和信号实现，未进入主线。

### 行为约束

1. 管理端只选择 `polling` 或 `realtime`。默认 `polling` 可在 PHP-FPM + MySQL 环境运行；选择 `realtime` 前，Resolver 必须确认 Event Worker、Gateway、内部 token 和公网连接 URL 可用。状态不满足时 `effective_mode=polling`，并展示原因。
2. 浏览器的业务组件只订阅通信主题并用 SDK 回源取数，不直接识别运行模式。`realtime` 用 EventSource 接收定向的 `signal`，信号不携带私信正文；每次实际数据读取仍由已有 API 鉴权。一次性 ticket 60 秒有效，Gateway 消费后按用户定向推送；重连须重新签发 ticket。
3. Gateway 输出符合 SSE 规范的真实换行帧、`text/event-stream`、心跳。游标只用于补读和去重提示，Redis Pub/Sub 只传唤醒信号，不作为消息事实源。
4. 断线时浏览器切到 polling；Gateway 丢失 Redis 时的回源和浏览器 `realtime` 模式下的低频校准属于故障补偿，不能成为第二套业务通信接口。Gateway 在 source-poll 下每连接 5 秒回源的成本随连接数线性增长。本轮增加连接上限（默认 100，可由 `LC_REALTIME_GATEWAY_MAX_CONNECTIONS` 设置）、后端回源 5 秒超时和单连接单次回源；容量满时 `/stream` 返回 503，浏览器错误回调退回 polling，连续重连失败后停止尝试。生产启用前仍须按部署资源压测。
5. 部署形态：Gateway、Event Worker 由进程管理器托管，反代提供同源 `/stream`，内网 Bearer 接口和 token 不暴露到浏览器。dev 默认保持 polling；生产切换需另做部署验收。

### 合并与验收

- #52 已合并完整通信链。后续改动在独立 PR 中复核，不重新引入旧 PHP SSE 端点。
- 自动检查：Gateway 帧字节、ticket 过期和重放、跨用户隔离、Redis 断连、Worker/Gateway 故障降级、主题单调度器与模式切换；`npm run check`、`npm run build:admin`、管理端和主题真实浏览器回归。
- dev 实测：两用户私信、通知、页面回读；Gateway 重启后新 ticket 重连；关闭通知策略后私信仍可到达；默认 polling 可正常使用。`COMMUNICATION-RUNTIME-DEV-PREVIEW-2026-11-06.md` 记录过一次独立预览验证，主线合并后还需在当前 dev 入口核对版本与 API。

## 2. 滑块验证：复用现有 Captcha 能力，补齐用户端

### 当前基线与风险

- 后端已有 `GeetestDriver`、`GET /captcha/challenge`、注册和 `me/cards` 的 POST/PATCH 校验入口；SDK 已有 `captcha.challenge()`。登录未接入人机验证，不在本次范围。
- 原主题 `AuthPage.jsx` 在人机验证开启时直接禁用注册，`auth-service.js` 未提交验证结果；卡片写入缺少挑战交互。本轮已接入注册和卡片创建/编辑，但需要 dev 有效凭据验证外部服务。
- 原 `GeetestDriver::verify()` 在驱动 `status=false` 时返回 `true`，站点策略开启时会绕过校验。本轮改为未就绪一律拒绝，二次校验使用 HTTPS POST。
- 原 `ChannelManager` 误读 `default_captcha`，实际配置键为 `default_captcha_driver`，导致选择图片驱动不生效；本轮已修正。

### 最小实现路径

1. 保持配置分层：系统“验证服务”维护 GeeTest v4 ID/Key 与驱动状态，站点“访问与验证”选择是否要求人机验证。公开策略返回要求状态、驱动名和配置就绪状态；`captcha.challenge()` 只返回公开的 Captcha ID，Key 永远只在服务端。启用站点策略或在启用状态下切换默认驱动时检查目标驱动配置；页面显示就绪状态，不把配置完整误称为外部服务已连通。新安装站点开关默认关闭。默认驱动配置键为 `default_captcha_driver` 与 `default_code_driver`，管理端必须使用这两个键并回读保存结果。
2. 主题用一个可复用的挑战调用点加载 GeeTest 官方 Web 脚本并调用 `initGeetest4`；用户通过后取得 `lot_number`、`captcha_output`、`pass_token`、`gen_time`。注册表单和卡片创建/编辑在提交前调用它，将四个字段交给现有 SDK 请求。仅在策略要求的写入动作触发，不拦普通浏览；邮件验证码与人机验证可按策略同时要求。
3. 后端保留卡片 `CaptchaCheck → Driver::verify` 与注册 `Session → Captcha::verify` 两条入口，均使用同一驱动选择器。驱动未就绪、字段不完整、外部超时或校验失败均拒绝受保护写入；不得在 `status=false` 时放行已启用的站点策略。`/validate` 改为 HTTPS POST，完整传入官方六个字段，要求响应 `status=success` 且 `result=success`。对失败请求不记录 token、Key 或完整第三方响应。
4. 前端脚本加载失败、超时或用户取消时保留表单输入并显示可重试提示；不自动绕过挑战。后端节流仍独立生效。先在 dev 用测试凭据验收，再允许启用站点开关；不得把密钥提交到仓库。

### 验收矩阵

| 场景 | 预期 |
| --- | --- |
| 策略关闭 | 注册、卡片写入保持现有路径，无第三方脚本请求 |
| 策略开启，服务配置缺失 | 管理端阻止启用；直接构造请求也不能绕过服务端校验 |
| 有效挑战 | 注册及卡片创建/编辑各完成一次真实写入 |
| 无字段、伪造、过期或重复结果 | 服务端拒绝，业务数据不写入 |
| 第三方脚本/校验服务故障 | 明确提示并允许重试，受保护写入保持拒绝 |
| 邮件验证与人机验证同时开启 | 两项均通过后才注册成功 |

优先写服务端失败场景测试，再接主题 UI 和 SDK 字段；最后做 dev 真实浏览器验收。第三方调用可用官方测试环境验证，常规 CI 使用受控响应桩，不能把浏览器打桩测试当作真实服务验收。

依据：[GeeTest v4 快速集成](https://docs.geetest.com/gt4/quick_integration_guide)、[服务端二次校验](https://docs.geetest.com/gt4/deploy/server/)、[WHATWG SSE](https://html.spec.whatwg.org/dev/server-sent-events.html)。
