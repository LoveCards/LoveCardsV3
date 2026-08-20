# LoveCards Monorepo

本仓库是 LoveCards 的唯一维护入口。原后端、管理端和 SDK 仓库仅作为迁移前归档，不再接收改动。

## 目录

```text
apps/backend   ThinkPHP 后端与站点主题
apps/admin     Nuxt 管理端
apps/default-theme  React 用户前端与默认主题源码
packages/sdk   JavaScript/TypeScript SDK
docs           架构、重构与协作规范
scripts        跨 workspace 编排和架构检查
```

## 后端 API 协议链

后端 API 的维护入口按下面的链路理解：

```text
Route Meta -> Controller -> Validate -> Business -> Resource -> ApiResponse
```

- Validate 是唯一请求字段验证层。
- Resource 是唯一响应内部数据契约层。
- Business 默认使用简单 Service；复杂模块才在模块内拆出 `Application`、`Domain`、`Contract`。
- 不再使用顶层 `apps/backend/app/modules/*/Application` 目录；复杂用例放在 `apps/backend/app/modules/<Module>/Application`。

## 环境

- Node.js 22 或更高版本
- npm 11 或更高版本
- PHP 8.1–8.x 及 Composer（后端）

首次安装统一在仓库根目录运行：

```bash
npm ci
```

不在 `apps/admin` 或 `packages/sdk` 内单独维护锁文件。

## 常用命令

```bash
npm run check                 # 后端行为 + 架构 + API 契约 + SDK/后台类型门禁
npm run build:sdk             # 构建 SDK
npm run build:admin           # 先构建 SDK，再构建管理端
npm run build:default-theme   # 先构建 SDK，再构建用户前端
npm run build                 # 构建 SDK、同步主题产物、构建管理端
npm run dev:admin             # 启动管理端开发服务
npm run dev:default-theme     # 启动用户前端，默认端口 4174
npm run test:sdk:integration  # 需要已运行且准备好测试数据的后端
npm run test:admin:e2e        # 使用隔离临时数据库运行后台浏览器测试
npm run test:theme:e2e        # 对已运行的本地 Theme Gateway 做真实浏览器回归
```

后端依赖仍在 `apps/backend` 内由 Composer 管理：

```bash
cd apps/backend
composer install
php think run
```

可裁剪运行时的最小部署只需要 Nginx、PHP-FPM 与 MySQL。服务器环境可按配置增加 Redis 唤醒、Event Worker、Scheduler 和 SSE；业务 API 与数据契约不变。诊断与运维入口：

```bash
php think lc:runtime:status
php think lc:eventing:work --max-jobs=0
php think lc:scheduler:run-once
php think lc:system:migrate
php think lc:system:upgrade --help
```

## 开始改动前

1. 阅读 [AGENTS.md](AGENTS.md) 和 `docs/engineering/`。
2. 从最新 `main` 创建短生命周期分支。
3. 先记录行为基线和验证方法，再改代码。
4. 提交前至少运行 `npm run check`，并按影响范围执行构建或后端检查。

后端测试分层、接口新增测试流程和后端到 SDK 的质量门禁见 `docs/engineering/BACKEND-TESTING.md`。管理端目录、状态、通知和 SDK 边界见 `docs/engineering/ADMIN-ARCHITECTURE.md`。跨模块依赖、Auth 目标切片和已知债务见 `docs/engineering/ARCHITECTURE.md`。Git 规则见 `docs/engineering/GIT-WORKFLOW.md`。

完整文档索引见 [`docs/README.md`](docs/README.md)，其中包括可裁剪运行时、系统升级、事件投递、日志审计、数据库运维、社区能力和 API/SDK 契约说明。

本轮社区与平台能力的前端接手入口见 [`docs/engineering/FRONTEND-AGENT-HANDOFF.md`](docs/engineering/FRONTEND-AGENT-HANDOFF.md)，2026-08-20 的实现与验证证据见 [`docs/engineering/IMPLEMENTATION-AUDIT-2026-08-20.md`](docs/engineering/IMPLEMENTATION-AUDIT-2026-08-20.md)。
