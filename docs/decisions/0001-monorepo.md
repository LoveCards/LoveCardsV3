# ADR 0001: 合并为单一仓库

- 状态：Accepted
- 日期：2026-07-23

## 决策

后端、管理端和 SDK 合并为一个 Git 仓库：

- `apps/backend`
- `apps/admin`
- `packages/sdk`

JavaScript 依赖由根 npm workspaces 和根 `package-lock.json` 统一管理。Composer 仍在后端应用内管理 PHP 依赖。跨 workspace 构建副作用由根脚本编排。

## 原因

- SDK、管理端和后端接口需要原子变更与统一验证。
- 单一锁文件消除相对路径和重复依赖状态。
- 架构约束、Agent 规则与 CI 只维护一份。
- 原仓库历史通过路径重写保留，额外分支和标签使用命名空间归档。

## 后果

- 新改动只进入本仓库，旧目录冻结为迁移前归档。
- 发布版本按组件标签，不假定三个组件必须同时发布。
- 根工具只编排，不把 PHP 与 Node 依赖强行合并为同一包管理体系。
