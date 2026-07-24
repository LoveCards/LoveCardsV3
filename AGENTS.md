# AGENTS.md

本文件是零上下文 Agent 在 LoveCards 仓库中的首要执行约束。

## 先读再改

开始任务前必须：

1. 读取根 `README.md`、本文件和与任务相关的 `docs/engineering/` 文档。
2. 运行 `git status --short --branch`，保留所有既有改动。
3. 明确行为基线、改动边界和可执行的成功标准。
4. 不确定需求或存在多种会改变行为的解释时，先说明取舍并询问。

## 仓库边界

- `apps/backend`：ThinkPHP API、领域规则、持久化和主题运行时。
- `apps/admin`：Nuxt 管理端，只通过 `@lovecards/sdk` 调用后端。
- `packages/sdk`：跨前端消费者共享的 API 契约与客户端，不依赖任何 app。
- 根目录：统一依赖锁、跨模块编排、工程检查和文档。

禁止使用 `file:` 或 `../` 跨 workspace 建立隐式依赖。跨模块关系必须通过包名、HTTP 契约或根脚本表达。

## 改动纪律

- 最小改动解决已确认的问题，不顺手重构无关代码。
- 先用测试、请求样例或可复现命令固定旧行为，再重构。
- 一次只迁移一个垂直切片；新旧实现不得长期双轨运行。
- 不增加“临时兼容层”来掩盖错误边界。确需过渡时，必须写明删除条件和负责人。
- 新抽象至少要消除一个真实耦合点；不能只为目录整齐增加接口。
- 改动架构边界时，同步更新约束表、自动检查和相关文档。
- 不修改 `apps/backend/public/theme/**` 中的生成产物，除非任务明确涉及发布同步；SDK 产物使用根命令 `npm run sync:sdk-theme`。

## 语言与注释

- 代码标识符、类型名、API 字段、配置键和协议值使用英文，不为翻译改变公共契约。
- 面向仓库维护者的代码注释、PHPDoc/JSDoc 说明和工程文档使用中文；框架名、类名、
  capability、HTTP、JWT 等技术名词保留英文。
- 用户可见提示、校验消息和业务错误使用中文；不得借注释整理顺手改变既有文案或错误码。
- 分支名、Conventional Commit 和 PR 标题使用英文，PR 正文可使用中文或中英双语。
- 测试名称使用英文，便于 CI 日志检索；必要的测试说明注释使用中文。
- 不为“统一语言”批量翻译历史文件。新增内容必须遵守本节；修改既有文件时只统一本次触及
  的注释，避免制造与业务无关的大面积 diff。

## 后端依赖方向

当前可执行规则：

- `common` 不依赖 `api`、`frontend`、`system`；现有唯一债务由检查脚本白名单锁定，只能减少。
- `api/controller` 不直接依赖 `api/model`。
- `api/service` 不依赖 `api/controller`。
- Controller 负责协议适配，Application/Service 负责用例，Domain 负责业务规则，Infrastructure 负责框架与外部技术。
- JWT 是 Token 基础设施实现，不代表完整鉴权模块。用户加载、角色能力和访客策略不得进入 JWT 编解码器。

完整目标和 Auth 示例见 `docs/engineering/ARCHITECTURE.md`。

## 验证

所有改动至少执行：

```bash
npm run check:architecture
```

JavaScript/TypeScript 改动执行：

```bash
npm run check
npm run build:admin
```

后端改动至少执行 Composer 校验和受影响 PHP 文件语法检查；涉及 API 行为时补充对应集成验证。无法运行的检查必须在交付说明中明确列出。

## Git

- `main` 始终保持可发布；日常改动使用 `feat/*`、`fix/*`、`refactor/*`、`chore/*`。
- 使用 Conventional Commits；结构迁移与行为变化分开提交。
- 不改写已共享的 `main` 历史，不将临时文件、密钥或本地配置提交进仓库。
- 不直接维护迁移前的三个旧仓库。历史分支位于 `archive/*`，只读保存。

详细流程见 `docs/engineering/GIT-WORKFLOW.md`。
