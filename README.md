# LoveCards Monorepo

本仓库是 LoveCards 的唯一维护入口。原后端、管理端和 SDK 仓库仅作为迁移前归档，不再接收改动。

## 目录

```text
apps/backend   ThinkPHP 后端与站点主题
apps/admin     Nuxt 管理端
packages/sdk   JavaScript/TypeScript SDK
docs           架构、重构与协作规范
scripts        跨 workspace 编排和架构检查
```

## 环境

- Node.js 22 或更高版本
- npm 11 或更高版本
- PHP 7.2.5 或更高版本及 Composer（后端）

首次安装统一在仓库根目录运行：

```bash
npm ci
```

不在 `apps/admin` 或 `packages/sdk` 内单独维护锁文件。

## 常用命令

```bash
npm run check                 # 架构约束 + SDK 类型检查
npm run build:sdk             # 构建 SDK
npm run build:admin           # 先构建 SDK，再构建管理端
npm run build                 # 构建 SDK、同步主题产物、构建管理端
npm run dev:admin             # 启动管理端开发服务
npm run test:sdk:integration  # 需要已运行且准备好测试数据的后端
```

后端依赖仍在 `apps/backend` 内由 Composer 管理：

```bash
cd apps/backend
composer install
php think run
```

## 开始改动前

1. 阅读 [AGENTS.md](AGENTS.md) 和 `docs/engineering/`。
2. 从最新 `main` 创建短生命周期分支。
3. 先记录行为基线和验证方法，再改代码。
4. 提交前至少运行 `npm run check`，并按影响范围执行构建或后端检查。

跨模块依赖、Auth 目标切片和已知债务见 `docs/engineering/ARCHITECTURE.md`。Git 规则见 `docs/engineering/GIT-WORKFLOW.md`。
