# 管理端驱动三域架构导读

> ⚠️ **本文件性质声明：本文由 AI Agent 基于代码阅读整理，是 Agent 的理解总结，仅限参考。**
> 它不是经过评审的规范文件；与代码冲突时，一律以代码和既有 ADMIN-* 规范为准。
> 引用的机制均在 2026-11-04 的 `main`（`938e20f6`）上核实过文件与行为，
> 但措辞、归纳与"原因"部分属于 Agent 的推断性解释，未经人工逐条确认。

> 日期：2026-11-04
>
> 范围：存储、消息、验证三个驱动配置页从 UI 到驱动实现的纵向架构导读。菜单归属以 [管理端菜单与页面归属规范](ADMIN-NAVIGATION-CONVENTION-2026-11-07.md) 为准。

## 1. 结论概览

后台可配置的"驱动面"共三块：**存储（storage）、消息（sender）、验证（captcha）**，
分别对应页面 `/apps/settings/storage`、`/apps/settings/messaging`、`/apps/settings/verification`。
站点信息、访问与验证、内容规则、主题外观属于**站点配置**，不属于驱动面。

三块驱动共享同一条纵向链路：

```text
导航/RBAC 声明 → 页面(Nuxt) → @lovecards/sdk（唯一后端边界）
→ Controller → Validate → Service/Application → 配置面（configs 表 + config/apps/*.php schema）
→ Factory → 驱动类（自描述 meta） → 厂商 SDK / 本地磁盘
```

## 2. 导航与权限层

`apps/admin/config/navigation.ts` 中三个驱动条目位于“系统”菜单；各自要求
`config.read` 和对应的组级读取权限（`storage.read`、`sender.read` 或 `captcha.read`）。
写入还需 `config.update`，安装和测试操作另有 capability。

权限模型不识别"驱动"概念，只识别能力；驱动页的高风险性由 `sensitive` 标记进入
RBAC 可见性与审计口径。新增驱动页无需改动权限系统。

## 3. 页面层（同构两页）

每个驱动域固定为两页：

- `index.vue`（渠道列表）：`channels()` + `config.list()` + `channelStats()`；
  渲染安装/可用状态、当前默认、用量统计；"安装"调 `install()`；
  切换默认 = `config.update({ storage: { default: slug } })`（消息、验证同理换组名）。
- `[slug].vue`（渠道详情）：表单字段**不写死**，来自后端 `channel.fields`
  （即驱动类自描述，见 §6）；保存 = `config.update({ storage_oss: {...} })`；
  "测试连接" = `testChannel({ channel })`。

两页复用全站统一的脏状态守卫（`useAdminUnsavedChanges`）与 SaveBar 组件。

## 4. SDK 边界层

前端唯一后端出口是 `@lovecards/sdk`。驱动相关能力分两类：

- **通用配置面**：`config.list / get / update / init`——按组读写一切配置值；
- **驱动专用动作**：`storage.channels/install/meta/testChannel/channelStats`、
  `sender.*`（含 `templates`、`testChannel`）、`captcha.*`。

规律：**配置值一律走通用 config 接口；只有驱动特有动作（安装、连通测试、统计）
才有专用端点**。API 面积不随驱动数量增长。

## 5. 配置面（三域共用地基）

- 单一 `configs` 表（group/key/value/type/description）。
- schema 文件位于 `apps/backend/config/apps/`：
  驱动侧为 `storage.php`（default、direct_upload_expire、速率限制）、
  `sender.php`（default / default_smtp / default_sms）、
  `captcha.php`（default_code_driver / default_captcha_driver / code_enabled /
  captcha_enabled / code_channel）。
- **渠道实例是动态组**：`ChannelManager::loadChannels()` 以
  `WHERE group LIKE 'storage_%'` 枚举渠道（如 `storage_oss`）；
  渠道组默认行由 `install()` 在启用该驱动时播种。
- 读取优先级：**env（`PHP_` 前缀）→ 缓存 → SQL → schema 默认值**。
  env 层构成"运维底线"：服务器环境变量可锁死键值，管理端无法覆盖。
- 写入触发 `CacheManager::clearDomain('config')`，即时生效。
- `ConfigManager::update` 仅接受已注册 schema 的键（未注册键被静默丢弃），
  因此部署新 schema 后需调用一次 `POST /api/admin/config/init`（幂等）。

## 6. 驱动层（自描述模式）

三个域的 Infrastructure 结构完全一致：

| 域 | Factory | 渠道管理 | 驱动实现 |
|---|---|---|---|
| 存储 | `StorageFactory` | `ChannelManager` | `LocalDriver` `OssDriver` `CosDriver` `QiniuDriver` |
| 消息 | `SenderFactory` | `SenderManager` | `SmtpDriver` `AliyunSmsDriver` |
| 验证 | `CaptchaFactory` | `CaptchaManager` | `CodeDriver` `GeetestDriver` `ImageDriver` |

关键机制是驱动类的 **`meta()` 自描述**：声明 `type/name/icon/fields`，
字段含 `type: 'password'`（如 SecretKey）与渠道级限额键
（`max_file_size`、`allow_mime_types`）。UI 表单、后端字段规范化
（`normalizeFields`）、渠道限额均从这一份声明派生。
`DriverInterface` 仅约定 `upload/delete/getUrl/meta` 四个方法。

接入新厂商的预期成本 = 新增一个驱动类；导航、页面、SDK、API、配置面均不改动。

`AbstractDriver::checkSize / checkMime` 使用 finfo 真实嗅探（不信任浏览器声明的
MIME），执行的是**渠道级底线**。

## 7. 验证域的两级驱动链

验证码不是孤立一条链，而是骑在消息域之上：

```text
user.captcha（注册邮件验证开关，站点信息页）
→ captcha.code_enabled / default_code_driver（默认 smtp_code）
→ CodeDriver → sender.code_channel（smtp / sms）/ default_smtp / default_sms
→ Sender 驱动（SmtpDriver / AliyunSmsDriver）
```

因此：验证页只决定"用哪个 code 驱动、走哪类渠道"，发送凭据维护在消息页。
人机验证（geetest_v4 / image）独立于消息域。

## 8. 分层限额语义（AND，不是覆盖）

上传类限制存在三层，逐层**只能更严，不能更宽**：

1. 业务层：内容规则页的场景桶限额（`upload.avatar_*` / `upload.content_*`，
   见 ADMIN-IA-DESIGN 与决策记录 D-4）；
2. 渠道层：驱动详情页的 `max_file_size` / `allow_mime_types`；
3. 厂商层：对象存储 / 邮件 / 短信服务商自身的硬限制。

同一路径上三层是 AND 语义；业务层先拦只是提前给出可读报错，不构成豁免。
（对应决策记录 D-5。）

## 9. 错误与可观测性设计

- `testChannel` 失败返回 **200 + success:false + message**：连通性失败是业务结果
  而非协议错误，UI 可展示可读的厂商诊断。
- `channelStats` 提供按渠道的用量观测（存储等域）。
- 驱动配置的写入经 capability 校验并进入 Operations 审计日志。

## 10. Agent 观察到的一处现状（未定性，仅供参考）

渠道凭据（如 OSS SecretKey）以 `configs` 表的普通行存储，
字段 `type: 'password'` 仅提供 **UI 遮蔽**；持有 `config.read` 能力的角色
可通过配置接口读取原值，当前未见 at-rest 加密层。
若未来处理该问题，Agent 认为较合理的挂点是 ConfigService 读写层
（对 password 字段加密 + 响应脱敏），不影响上层结构。**此为 Agent 推测，
是否构成缺陷、如何处理均需人工决策。**

## 11. 与既有规范的关系

- 页面交互、导航与 IA 规范以 `ADMIN-IA-DESIGN.md` 为准；
- 分层与依赖方向以 `ADMIN-ARCHITECTURE.md`、`MODULE-ARCHITECTURE.md` 为准；
- 本文件仅是三驱动域的**纵向导读**，不定义新规范，不得作为改动依据。
