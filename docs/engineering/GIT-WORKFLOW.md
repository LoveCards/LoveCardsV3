# Git 工作流

## 仓库模型

- 单一仓库、单一长期分支 `main`。
- `main` 始终可构建、可发布，启用保护后禁止直接 push。
- 开发分支短期存在：`feat/*`、`fix/*`、`refactor/*`、`chore/*`。
- 不建立长期 `dev` 分支；它会增加合并队列和分支漂移。
- 迁移前分支保存在 `archive/backend/*`、`archive/admin/*`，仅供追溯，不从中继续开发。
- 后端历史标签使用 `backend/v*` 命名空间。后续统一仓库发布建议按组件命名，例如 `backend/v2.5.0`、`sdk/v2.1.0`、`admin/v1.0.0`。

## 提交

使用 Conventional Commits：

```text
feat(auth): add authentication context
fix(sdk): preserve refreshed token header
refactor(auth): move JWT behind token service
chore(repo): update architecture checks
```

一个提交只表达一个可验证意图。推荐顺序是测试基线、结构迁移、调用方切换、旧代码删除；不要把格式化、生成产物和业务变化混在同一提交中。

## 合并

1. 分支从最新 `main` 创建并保持短小。
2. PR 描述写明行为基线、架构影响、验证结果和回退方式。
3. 必须通过 CI 和至少一次审查。
4. 默认 squash merge 保持主线清晰；需要保留分阶段重构证据时使用 rebase merge。
5. 禁止在共享分支 force push，禁止改写已发布标签。

## 远端初始化

本地迁移完成后再创建空远端，首次推送包括主线、归档分支和标签：

```bash
git remote add origin <url>
git push -u origin main
git push origin 'refs/heads/archive/*:refs/heads/archive/*'
git push origin 'refs/tags/*:refs/tags/*'
```

远端地址必须由仓库所有者提供，不在脚本或文档中写死凭据。
