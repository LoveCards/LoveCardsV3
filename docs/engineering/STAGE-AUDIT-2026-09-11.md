# 阶段审计：2026-09-11

审计基线：`feat/platform-foundation-checkpoint`，提交 `5fab83c53e8545eb9099423b59441e780c7e5c40`。开始时工作区干净，本地与缓存的远端跟踪分支一致；本次未在线核对 GitHub。

结论：可以继续开发和受控演示，不满足发行版验收。当前卡片可见范围仍有权限绕过及媒体泄露风险。先前“全面检查完成”和接口返回 200 的结论不能作为这项能力的发布证明。

本轮已按报告优先级施工并修改业务代码；数据库迁移历史未改写，未执行远端提交或推送。

## 6. 本轮施工结果（2026-09-11）

- A1：生产 ThinkPHP Facade 不再因 `method_exists()` 误判而放行；`assertReadable()` 真实查询并统一返回数组。测试替身仅在非 vendor Facade 下兼容。
- A3：卡片 audience 与资料页隐私解耦，`public/followers/private` 在列表、详情、互动写入使用同一套卡片规则。
- A4：评论列表、置顶、取消置顶、清理和水合统一使用 `cards.pinned_comment_id`；旧 `card_comment_pins` 仅保留为迁移历史。
- A5：收藏/历史在权限过滤后继续补页扫描，避免不可见资源造成漏项或错误 `has_more`。
- A6：保留现有本人/公开资料统一的有效卡片获赞口径；未新增独立的 visibility 统计分支，避免破坏已冻结 SDK 语义。
- A2：创建、更新、状态/可见范围变化和删除流程均同步附件/封面公开标记；受限媒体的登录态流式访问仍是后续独立协议项。

定向回归：可见性、卡片批量操作、评论行为、库行为均通过。完整后端套件在当前 PHP 环境仍有 10 个既有环境扩展失败（mbstring、OpenSSL、ZipArchive、fileinfo），不是本轮代码断言失败。

## 1. 发布阻断问题

### A1 / P1：卡片权限校验在真实框架中直接放行

位置：`apps/backend/app/modules/Content/Cards/Service/CardVisibilityPolicy.php:63`。

`assertReadable()` 以 `method_exists(Db::class, 'table')` 判断是否有查询能力。但真实 `think\facade\Db` 的 `table` 通过 Facade 动态分发，`method_exists` 返回 false。代码因此直接构造正常、公开的卡片数组，根本不查库。

使用项目真实 vendor、PHP 8.2 实测：

```text
method_exists(think\facade\Db::class, 'table') => false
CardVisibilityPolicy::assertReadable(2147483647, 0)
=> ['id' => 2147483647, 'status' => 0, 'visibility' => 'public', 'user_id' => 0]
```

影响：调用该方法的评论列表/详情、评论创建、卡片/评论点赞、收藏和历史写入跳过卡片可见性检查。部分调用方仍有自己的存在性或状态校验，但不能代替作者、关注关系和拉黑判断。

处理：删除生产代码中的测试替身放行分支。使用真实查询或明确注入的查询端口；测试应调整替身而不是让生产授权逻辑退化。验收需要证明不存在、私密、未关注、拉黑、隐藏、删除的目标都被正确拒绝，且没有写入副作用。

### A2 / P1：卡片媒体公开性没有接入 visibility

位置：`Cards/Service/Cards.php:352、442、1113`；`Platform/Files/Application/ResolvePublicMedia.php:15`；`app/frontend/controller/Media.php:17`。

创建和媒体更新只依据 `status=0` 把附件/封面标记为公开；仅更新 `visibility` 时不会同步文件引用。`syncMediaVisibility()` 本身也只读取 status/deleted_at。媒体端点固定以访客身份查文件公开标记，不判断卡片或关注关系。

后果：正常状态的 private/followers 卡片可能生成公开文件；公开卡片收紧可见范围后，已知媒体地址仍可能访问。即便简单把文件设为非公开，当前媒体端点也不能识别获准的关注者，不能完成私密媒体访问闭环。

处理：统一设计附件引用授权、访客/登录访问、共享文件多引用规则和撤权缓存；覆盖封面、附件、HEAD、Range、条件请求及对象存储 URL。不能仅靠隐藏卡片响应或猜测 hash 保密。

### A3 / P1：匿名和登录用户对主页隐私的判断不一致

位置：`CardVisibilityPolicy.php:35、90`；`Platform/Social/Public/Social.php:29、59`。

匿名分支只检查卡片 public；登录的非作者分支还检查作者 profile_visibility。用户主页接口对匿名用户也检查主页隐私。因此同一作者主页设为 private/followers 时，一张 public 卡片可能通过匿名热榜或详情读取，但登录非关注者却不可读。

处理：冻结“主页隐私是否限制独立公开卡片”的统一规则。推荐依照当前登录策略和既有主页权限要求统一执行；若产品选择独立规则，也必须同步所有入口、统计与文档。不能保留退出登录后权限反而增加的隐式差异。

### A4 / P1：置顶未收敛到 cards.pinned_comment_id，且读取类型错误

位置：`Content/Comments/Service/Comments.php:194、197、199、326、374、502`。

`assertReadable()` 声明返回 array，但评论列表用 `$card->pinned_comment_id` 读取对象属性，随后退回 `card_comment_pins` 查询。列表水合、置顶、取消置顶仍读写旧表。

后果：卡片字段不是实际唯一事实源；若只更新卡片字段，可能显示旧置顶或不显示；缺失旧表仍可能让评论接口 500。移除 A1 的放行分支不会自动修好这里的数组/对象问题。

处理：以卡片字段为唯一事实源，批量读取置顶投影；核对迁移回填、一级评论合法性、清理与旧表退役流程。保留历史迁移的可执行性，不直接改坏已部署迁移历史。

## 2. 正确性和测试缺口

### A5 / P2：收藏和历史在分页后过滤权限，导致提前结束

位置：`Platform/Library/Infrastructure/ThinkOrmLibraryRepository.php:99–121`。

先读取 limit+1 条，再过滤不可访问卡片，最后根据过滤后数量计算 has_more。例：limit=2，头三条原始记录中有一条不可读，过滤后剩两条，就返回 has_more=false；数据库中更旧的可读条目永远读不到。

处理：在排序分页前执行权限过滤；或采用有明确扫描游标语义的补页方案。测试需含连续不可读条目、空页后仍有可读记录和多页稳定性。当前每卡调用 canRead 还会重复读取作者、偏好、关系，存在随页大小增长的查询开销。

### A6 / P2：公开获赞统计未排除非公开卡片

位置：`Platform/User/Public/UserProfileStats.php:10`；`Platform/Social/Public/Social.php:42、67`。

聚合仅检查 ref_type、作者、status 和 deleted_at，没有 `c.visibility='public'`。曾公开获赞的卡片改成 private/followers 后仍计入公开主页统计，与用户要求的“只统计公开且未删除卡片”不一致。

处理：明确本人统计和公开统计的口径，避免修改共享聚合时意外改变本人契约。覆盖公开转私密/关注者、删除、取消点赞和其他作者记录。

### A7 / P2：现有回归测试没有验证授权与真实 ORM 行为

位置：`tests/Cards/AuthenticatedVisibilityRegression.php`；`scripts/check-architecture.mjs:274`。

新增回归只检查 SQL 字符串和路由是否挂中间件，没有执行 assertReadable、权限矩阵、媒体访问或真实 ORM 绑定。因此 A1 能在 72 个后端测试程序全部通过时存在。架构检查也没有完整执行跨模块必须通过 Public 的规范，Library/Notifications 直接引用 Cards Service 仍通过检查。

需要补行为测试及隔离数据库集成；不要继续用源码字符串数量或“没有绑定参数”证明安全。

## 3. 对热榜故障原因的更正

项目真实 ThinkORM `ParamsBind::bindParams()` 使用普通 str_replace 替换命名占位符。`:vis_profile_public` 是 `:vis_profile_public2` 的前缀，先替换前者会破坏后者；日志 getRealSql 随后找不到绑定名，在 strpos 返回 false 时产生 TypeError。

本次使用真实 vendor 的 trait 和 Mysql::getRealSql，在不连接数据库的独立 PHP 进程中复现：

```text
present_placeholder
missing_placeholder
TypeError: substr_replace(): Argument #3 ($offset) must be of type array|int, bool given
```

因此准确原因是占位符前缀碰撞，不是所有命名参数或所有嵌套 SQL 都不兼容。当前整数和固定枚举内联避开了本例，但不能推广成“禁用参数化查询”；外部输入仍应绑定，使用互不碰撞的命名或正确的位置参数并补真实 ORM 回归。

## 4. 验证记录与边界

- 已实测 A1 的 Facade 放行路径和热榜占位符碰撞，无数据库写入。
- 后端 72/72 个测试程序通过；这个数字不是权限场景覆盖数量。
- 架构、严格路由、管理端能力一致性、API/OpenAPI、SDK 生成一致性通过。
- SDK transport 27 次本地 HTTP 请求通过；SDK package、调试脱敏和浏览器资源一致性通过。
- SDK 和 Admin 类型检查通过；默认主题 32 个测试文件、279 条测试通过。
- 根 `npm run check` 完整运行结束，退出码 0；默认主题生产构建通过。构建提示主 JS 包约 924 KB（gzip 276 KB），作为后续性能优化项，不作为本轮权限问题的替代结论。
- 本次访问 localhost/127.0.0.1:6001 无法连接。未确认数据库迁移实际执行状态，未执行真实 SDK CRUD 集成、Admin/Theme/Upgrade E2E，也没有以共享数据库冒充隔离环境。
- 管理端本轮只验证架构、契约和类型，未完成页面视觉与真实角色浏览器验收。

## 5. 建议施工顺序与验收条件

1. 优先修 A1 和 A4：授权必须真实执行；卡片置顶统一字段与返回类型。用隔离数据覆盖 owner/follower/stranger/anonymous/block/deleted/status 矩阵。
2. 完成 A2 和 A3：统一卡片、主页、媒体权限，验证公开转私密和取消关注后的资源撤权。明确媒体请求认证及缓存规则，输出默认主题适配内容。
3. 修 A5、A6：权限过滤前置分页，冻结公开/本人统计口径；整页查询批量化并用实际查询计划验证，不凭猜测加索引。
4. 补 A7：真实 ORM 绑定回归、真实鉴权副作用测试、迁移与角色浏览器用例；完善跨模块边界检查。
5. 发布验收：根 check、管理端构建、SDK/主题资产一致性全部通过；一次性数据库执行 SDK/Admin/Theme/Upgrade 集成；确认迁移前后行为与撤权场景；再提交新的阶段验收结果。

前端需要配合的实质项是受限媒体访问协议及统一不可访问状态。可见范围字段、评论置顶和统计继续消费生成 SDK；具体媒体协议应在后端方案确定后交付，当前不要求前端自行补权限判断。

## 7. 复验更新（2026-09-12）

本轮已在本地 MySQL 隔离数据库完成实际验收，临时数据库由测试脚本创建并在结束时清理：

- 根 `npm run check`（使用项目捆绑 PHP 8.2）通过，退出码 0。
- SDK 隔离集成：136 项通过、0 项失败、24 项按条件跳过；覆盖卡片可见性、媒体撤权、关注关系、评论/消息/举报校验等场景。
- Admin Playwright：71/71 通过。
- 默认主题 SPA E2E：通过入口回退、深链、资源网关、登录、发布、评论与清理。
- Upgrade E2E：通过上传/等待、显式契约批准、迁移 SQL、激活、运行时切换与健康检查（21 次 advance）。

因此，原报告中“尚未执行真实 SDK CRUD 集成、Admin/Theme/Upgrade E2E”的边界已解除。仍保留的非阻断项：SDK 条件跳过项（匿名 token、无 fixture 的详情/评论等）、主题包主 JS 体积约 924 KB（gzip 约 276 KB），以及后端测试环境扩展缺失时的既有兼容性提示。未执行远端提交、推送或生产配置变更。

## 8. 最终复验与收尾（2026-09-14）

本节结果取代第 7 节中的条件跳过和主题包体积待办记录：

- API/权限审计 A1–A7 均完成；真实隔离数据库验证卡片可见性、媒体授权/撤权、匿名/关注者/拉黑、置顶、共享媒体、游标分页与 SDK CRUD。
- SDK 隔离集成：166/166 通过，0 失败，0 跳过。共享媒体审计夹具现在会删除 sibling 卡片，避免待清理文件污染后续主题 E2E。
- Admin Playwright：完整套件 71/71 通过；后续懒加载复验的 setup + dashboard 冒烟 2/2 通过。
- Theme SPA E2E：完整登录、发布、评论、路由与资源网关流程通过；路由懒加载后的独立复验也通过。
- Upgrade E2E：通过上传、等待、显式契约批准、迁移 SQL、激活、运行时切换与健康检查，共 21 次 advance。
- 最终根 `npm run check` 通过：后端 75/75、主题 33 个测试文件 282/282、SDK/Admin 类型检查、190 路由契约、架构检查及生产构建均通过；API 审计 P0/P1/P2 均为 0。
- 完成主题路由级懒加载后，生产入口 JS 从 925.63 KB（gzip 276.83 KB）降为 195.07 KB（gzip 61.10 KB，入口 chunk 减少约 79%）；页面代码输出为异步路由 chunk，构建不再出现超过 500 KB 的 chunk 警告。保留现有同步组件测试，通过测试专用路由表与一致性测试核对路由路径。
- 两轮 E2E 使用的 `lovecards_e2e_*` 数据库均由脚本清理；确认无残留后已关闭本轮启动的隔离 MariaDB 实例。未改生产配置、未触碰其他 MySQL 实例、未执行远端写入/推送/提交。
