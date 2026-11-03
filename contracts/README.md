# API Contract Workflow

后端接口契约的源码来自：

```text
Route Meta      接口身份、HTTP 方法、权限和 operationId
Validate        请求字段和场景
Resource        响应字段和响应形状
ApiResponse     成功/分页/错误外壳
```

`contracts/openapi.json` 是由后端代码导出的构建产物，不允许手工编辑。SDK 从该文件生成：

```text
packages/sdk/src/generated/api-contract.ts
packages/sdk/src/generated/resources/*.ts
```

日常命令：

```powershell
npm.cmd run audit:api
npm.cmd run export:api
npm.cmd run generate:sdk
npm.cmd run check:api-contract
npm.cmd run typecheck:sdk
```

提交前统一执行 `npm.cmd run check`。该门禁会先运行后端行为测试和严格路由覆盖检查，再验证 API 契约、SDK 生成物以及 SDK/管理端类型；不要只运行某个生成命令后直接提交。

生成目录中的文件带有 `Generated from contracts/openapi.json` 标记，不应直接修改。需要增加客户端便利方法时，应在 SDK 核心或扩展层添加，不要修改生成资源。

动态配置、主题配置、存储/消息/验证码驱动 schema 使用固定外壳和运行时 schema；不能把动态字段伪装成固定 TypeScript 字段，也不能无声明地退化为 `any`。

契约生成前必须先通过 `audit:api`。P0/P1 审计问题会阻止导出，避免把错误接口固化到 SDK。

测试分层、接口新增流程和一次性集成环境要求见 `docs/engineering/BACKEND-TESTING.md`。
