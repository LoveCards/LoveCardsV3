# API 模块迁移模板

本模板用于新增模块或整理既有模块。目标不是给每个模块制造相同的目录，而是让每个接口遵守同一条可验证的调用链：

```text
Route -> Controller -> Validate -> Service -> Resource -> ApiResponse
```

## 模块信息

- 模块名称：
- 业务负责人：
- 执行 Agent：
- 业务职责：
- 本次范围：
- 明确不做：
- 允许修改的目录：
- 不可改变的契约：路由、请求字段、响应外壳、权限 capability、数据库结构

## 接口基线

- Route/API/Command 入口：
- 公开性和 capability：
- Validate 场景：
- Resource 类型：
- 成功响应示例：
- 错误状态码和业务码：
- 数据读写范围：
- 是否有事务：
- 是否有外部副作用：
- 真实 HTTP 请求示例：

## 依赖设计

- 调用方：
- Service 入口：
- 需要复用的 Contract/Port：
- Infrastructure 适配器：
- AuthContext 字段：无 / uid / roles / capabilities：
- 是否需要第三方 SDK、缓存、文件系统或多驱动：

只有在存在真实边界时才增加 Contract。简单 CRUD 可以由 Service 直接调用 Model；第三方 SDK、驱动、缓存和 ORM 适配必须放在 Infrastructure。

## 迁移步骤

1. 增加行为测试，先锁定现有行为。
2. 提取 Service 用例和必要的 Contract。
3. 将技术实现移动到 Infrastructure，并在 `app/provider.php` 绑定接口。
4. Controller 改为只调用 Validate、Service 和 Resource。
5. 切换所有调用方和测试。
6. 删除旧入口、重复实现和空目录。
7. 更新架构检查与文档。

## Files/Storage 特殊规则

```text
Files Service
  -> Storage Contract
  -> Storage Infrastructure Driver
```

Files 负责文件记录、归属、权限、状态和引用；Storage 负责物理上传、删除、URL、直传凭证和渠道配置。不要把驱动 Factory 或第三方 SDK 放回 Files Service，也不要让 Storage Driver 创建业务文件记录。

## 完成检查

- [ ] 原行为测试通过
- [ ] 新增接口契约测试通过
- [ ] 无 Controller -> Model/Driver/Factory 依赖
- [ ] Service 不读取 HTTP Request 状态
- [ ] Resource 覆盖所有固定结构响应
- [ ] 动态接口采用固定外壳 + 动态内部 schema/value
- [ ] 无旧入口和双轨实现
- [ ] 无反向依赖
- [ ] 公开路由未强制假设 `AuthContext`
- [ ] 错误状态码、业务码和日志语义不变
- [ ] `npm run check` 通过
- [ ] `composer validate --no-check-publish` 通过
- [ ] `composer test` 通过
- [ ] 真实 HTTP 回归通过

## 交接要求

任务描述必须写明：

1. 一个明确的 Route/API 入口和预期响应。
2. 允许修改的文件或目录，以及不可改变的公共契约。
3. 要复用的 Service、Contract 或 `AuthContext` 字段。
4. 至少一条行为测试和一条真实请求验收方式。
5. 必须执行的根检查和模块检查。

不要在结构重构中同时修改 Token 语义、响应外壳、权限名称或数据库结构。
