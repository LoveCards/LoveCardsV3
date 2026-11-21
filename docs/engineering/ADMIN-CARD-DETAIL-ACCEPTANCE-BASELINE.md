# 管理端卡片详情验收基线

> 2026-11-21 冻结的卡片垂直切片验收记录。设计规则以 [详情与编辑视觉规范](ADMIN-DETAIL-EDIT-DESIGN-STANDARD.md) 为准；本文只记录已实现行为、验证方法和进入主线前仍需取得的证据。[临时 HTML 原型](prototypes/2026-11-admin-card-record-detail.html) 仅供追溯，不作为运行码或后续资源的组件模板。

## 行为边界

| 入口 | 容器与初始状态 | 成功标准 |
| --- | --- | --- |
| 卡片主列表的“编辑” | 880px 详情弹窗，直接进入编辑态；窄屏全屏 | 列表 URL、查询和页码不变；保存后关闭弹窗并刷新列表；关闭后焦点回到触发行操作 |
| 其他资源引用、直接访问、书签 | `/admin/apps/content/cards/:id` 独立详情页，先阅读再原位编辑 | 刷新后仍能读取同一记录；面包屑、返回和未保存离开保护有效 |

两种容器使用同一个 `CardDetailContent`。正文、媒体、标签、发布范围、扩展 JSON、治理状态、作者和统计的顺序与事实一致；卡片没有独立标题字段时只显示“卡片 #ID”，不截取正文当标题，也不在下方重复编号。发布范围目前只读，因为管理端更新契约尚不接受该字段。

## 保存与权限契约

- 普通草稿只通过 SDK 的 `cards.updateManaged` 保存受权可写字段。JSON 完整显示，顶层必须是对象；解析失败不得发 PATCH，错误只在该字段附近显示一次。
- 标签使用搜索多选与 chip；已关联标签即使候选加载失败也保留可读名称或 ID。
- 新图片由 SDK `files.upload` 上传，multipart 必须带 `scene=card`；卡片保存再提交 `media_file_ids` 和 `cover_file_id`。顺序、封面和移除在草稿中管理。上传已产生文件副作用，取消草稿不等于撤销上传；缺少可靠 `file_id` 的旧媒体不得猜测并重新绑定。
- 审核、隐藏、封禁、恢复和置顶按现有能力与状态显示为独立命令，经确认后立即执行；不混入普通草稿保存。通知仅由后端按 [通知规范](NOTIFICATIONS.md) 判定和投递。
- 无写权限时，状态呈只读事实，不展示虚假的可编辑下拉框；后端继续裁决权限、状态和文件场景。

## 自动验证

| 层级 | 固定入口 | 必须覆盖 |
| --- | --- | --- |
| Resource 与 PHP | `php apps/backend/tests/Api/ResourceContractBaseline.php`；受影响 PHP 文件逐个 `php -l` | 管理端单条读取的 `cover_file_id` 契约与语法 |
| 架构、契约、类型和构建 | `npm run check`；`npm run build:admin` | 模块依赖、Route/Resource/SDK 一致性、管理端类型与产物 |
| Mock 浏览器 | `apps/admin/e2e/card-detail-form.spec.ts` 与 `content-hierarchy.spec.ts` | 列表弹窗和独立页复用、上传场景及媒体绑定、非法 JSON 拒绝、脏状态、焦点回归、390px 无横向溢出 |
| 真实 HTTP 与数据库 | 一次性隔离环境中的 `npm run test:sdk:integration` 和 `npm run test:admin:e2e` | 后端实际文件场景校验、封面引用持久化、权限与状态命令、重新读取的最终事实 |

Mock 浏览器测试拦截 `/api/**`，证明前端请求与交互，不证明真实数据库写入。真实集成测试只能按 [后端测试规范](BACKEND-TESTING.md) 在一次性本地数据库中运行；不得以 20089 共享 dev 数据库为目标执行写入测试。`npm run check` 需要仓库支持的 PHP 版本及 `fileinfo` 扩展；云端默认 PHP 8.5 缺少该扩展时，其失败不能解释为本切片通过或失败，须换用完整的 PHP 运行时重跑并记录结果。

2026-11-21 云端 dev 的留存结果：`PATH=/www/server/php/82/bin:$PATH npm run check` 通过，`npm run build:admin` 通过；卡片专项 3 项 Mock 浏览器检查通过，内容层级套件 12 项首轮通过、2 项更新旧控件名断言后复跑通过。云端默认 PHP 8.5 缺少 `fileinfo`，本轮完整门禁使用已安装且带该扩展的 PHP 8.2。已运行的浏览器检查可按下列方式复现，前提是本机已有 20089 管理端与 6001 后端服务：

```bash
LOVECARDS_DEV_ADMIN_ORIGIN=http://127.0.0.1:20089 \
LOVECARDS_DEV_BACKEND_ORIGIN=http://127.0.0.1:6001 \
LOVECARDS_E2E_MANAGED_SERVERS=1 \
npx playwright test -c apps/admin/playwright.config.ts --project=chromium --no-deps apps/admin/e2e/card-detail-form.spec.ts
```

## 人工验收

1. 在浅色和暗色、1440／960／600／390px、200% 缩放下分别看独立页和弹窗：只有一层主表面，正文优先，字段标签不重复，动作可触达且无整页横向滚动。
2. 用真实有权与只读账号检查：编辑、上传、治理命令只在对应权限下可用；错误、加载、保存和刷新状态连续。
3. 用一条含多张图片和标签的卡片检查：添加、排序、设封面、移除、保存、重新打开，最终顺序及封面与后端一致；再检查旧媒体缺 ID 的安全限制。
4. 分别验证非法 JSON、保存失败、上传部分失败、Esc／遮罩／关闭按钮、切换对象及独立页离开时的草稿处理和焦点回归。
5. 核对审核、隐藏、封禁、恢复、置顶的可用状态、确认文案、服务端结果、审计和按通知规范产生的后端副作用。

卡片切片完成不代表用户、评论、标签等资源已采用同一布局；其他资源应按各自保存范围逐个迁移和验收。
