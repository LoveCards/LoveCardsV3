# 系统通知统计与发布基线

> 2026-11-27。替代此前“管理员逐条收件明细”的设计。通知、审计与事件边界仍以 NOTIFICATIONS.md 为准。

## 范围与收束

系统 → 站内通知保留一个主入口，分为概览、发送系统通知两个原生 Tab。概览仅显示现存通知总数、已读、未读、覆盖用户数及类型数量，不展示普通用户逐条通知。已读数为总数减未读；覆盖用户数不是全站人数，统计不等于送达率。没有接口的趋势或阅读时间不补造。

发送支持指定、多选及全站正式账号；全站排除访客、已注销及已删除账号，保留受限账号的既有系统通知接收规则。选择器仅返回用户名和编号，以用户名#ID 显示。手动发布固定 system.notice，级别为普通或提醒；安全与审核通知仍由对应业务事实产生。纯文本及可选站内路径服从既有 4096 字节 payload 预算；不增加富文本、图片、模板、定时、召回或跨渠道发送。

## 设计方向

THESIS：统计用于判断数量，发布用于一次明确的平台告知；不复制全站用户收件箱。
OWN-WORLD：沿用 Vuetify、Indigo、中性表面及既有浅深色 token。
STORY：选择范围与内容 → 冻结预览 → 核对人数 → 确认 → 查询回执。
FIRST VIEWPORT：1040px 页头与两个真实任务 Tab；概览紧凑数字，发送一份对齐表单与一组动作。
FORM：seed: none (pinned)。用户要求“页面只显示数据数量”，并确认“指定用户、多选用户及全站用户”。
FINISH：unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## 业务规则

1. prepare 不发送。服务端冻结正文、路径、级别、收件 ID，生成本人可查的预览批次。全站 ID 在一致性事务中分块读取并持久化，不将名单返回浏览器。确认后不可编辑，新增用户不补发。
2. 发布者与 request_key 唯一：相同内容返回原批次，不同内容 409；他人批次不可读写。latest 支持按 request_key 只读核对未知结果。
3. confirm 重读最新权限，在事务内确认批次、记录审计并发布首个续批事件。双击、并发及超时重放只确认一次。
4. 每次续批最多 100 人，复用 notifications 队列和 Scheduler/Worker。单收件人继续通过 SystemNoticeIssuer 与 system.notification.issued@1；系统 actor 为 null，真实发布者在批次和审计中，避免自收件被过滤。
5. 收件快照关联本模块签发事件、任务与结果。原系统通知 Handler 在事务内标记生成或跳过；其间已注销或变成访客的账号跳过。普通系统通知不要求批次关系。
6. 回执区分待处理、已生成、跳过、失败；受理不代表送达，已生成不代表用户已读。续批任务失败须单独说明；进度不可读取时返回失败，不伪造正常状态。
7. 重试只处理原失败任务，保留已生成结果与累计尝试，携带 expected_generation 与 1–255 字原因。旧代次请求只返回当前回执，不再次授权尝试。
8. 网络结果未知先 GET 核对，不自动重发。浏览器只暂存本人 request_key，不持久化正文。未确认预览经核对为空可明确放弃；不能用新建掩盖已确认动作的未知结果。
9. 审计保留批次、发布者、范围、人数、正文摘要、路径及重试原因，不逐收件人写审计；正文保存在模块批次中。

## 权限、接口与模块

notifications.read 只读统计，原 communications.read 继续可读原统计。notifications.publish 允许本人发布批次；全站另需 notifications.broadcast。两项发布权限 risk 3，仅登记、不自动授予既有非 root 角色。

原 GET /admin/operations/notifications 保持统计形状。新增 GET /admin/notifications/recipients；POST batches 准备预览；GET batches/latest 和 batches/:id 核对；POST batches/:id/confirm、retry 确认和重试。

撤下原 GET /admin/notifications 和 /:id 管理员收件明细、对应 SDK 方法和详情组件；旧详情页面地址只重定向概览。用户端自身列表、已读、偏好接口保持原语义。

仅新增 system_notice_batches、system_notice_recipients 两张模块表，不增加业务模块、一级菜单、队列、全局 Store、依赖或通用任务引擎。User Public 负责正式收件资格；Notifications 负责发布与收件事实；Eventing Public 提供受限任务进度及既有重试，不跨模块直接读写对方表。

## 验收

验证准备不发送、冻结范围、后注册排除、自收件系统身份、他人隔离、撤权后确认拒绝、并发与重放、审计回滚、100 人续批、失败恢复、代次重试、统计只读。真实写入只使用一次性本地数据库，常驻 dev 不执行群发测试。

界面核对浅深色、1440/960/720/390/320px、键盘、Escape、焦点、Reduced Motion、错误与旧值、未知结果只读核对。最终发布走现有签名升级，并合入当前 active Release 保留其他功能。

## 本轮验证与 dev 发布记录

- 当前云端组合工作树与隔离提交候选的 npm run check、npm run build:admin 均通过；PHP 28 个改动文件语法、Composer validate 与 Auth/JWT 回归通过。
- 独立 MySQL 验证并发准备／确认、重复消费、最多 100 人续批、后注册排除、删除后跳过、失败恢复、代次与审计回滚；迁移双次执行保持授权不变。全站 ID 与进度均以 500 条分块，快照仍有 O(N) 数据库读写成本。
- 持久化真实 SDK 发布测试 17/17 通过，走真实 HTTP 与正常 Scheduler；隔离数据库与实例均清理成功。环境安全检查增加 12 项拒绝路径。
- 浏览器首轮 19/21，发现旧 404 断言和失败状态文案；修正后定向 3/3（含新增视觉用例）通过，共覆盖 22 个独立场景。桌面浅色概览、表单及 390／320px 深色回执截图已查看。
- 实际后端由 r20 升级至 dev-main-r21-system-notices-20261127，签名包 2819 个文件；三方合并 BaseController、运维路由、安装 schema，其余 2799 个 active 文件保留。
- 升级计划 upgrade-20261127082221-b485bb068efaf25b 最终 completed，revision 39，无失败步骤。新增 Handler 后旧 CLI 的已加载目录不能冒充新 Release；新进程运行正常 Scheduler 后继续原计划通过健康验证，未绕过 Catalog、账本或手改指针。
- 实际库 dev_lc2_v3_20261115 账本由 66 至 67，仅新增 20261127000002；迁移 SHA-256 2f45f0448dfb38c792c56b4a0d3f4a07b5a8a92cb007365bee68eccde99819a8。发布能力 risk 3，持久授权行仍为 0，默认 root 保留。
- dev 页面 HTTP 200，统计与批次接口未登录返回 401，原明细接口 404，r21 健康为 true。真实批次与收件快照均为 0，本轮没有向真实用户发送测试通知；统计查询未改变已读状态。
- 发布回执、补丁、合并清单、日志与截图位于服务器 /root/lovecards-dev-evidence/2026-11-27-system-notices；自动验证与渲染复核通过，可进入 dev 人工验收。

隔离集成命令为 npm run test:sdk:system-notices，必须同时设置 SDK/E2E 隔离标记、e2e 环境名、匹配的 lovecards_e2e_* 数据库和仓库外实例根；HTTP 仅允许随机 localhost 端口，拒绝 6001、20087、20089。变量与硬栅栏以持久测试脚本为准，不得禁用。
