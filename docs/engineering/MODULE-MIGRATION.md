# 模块迁移模板

复制下面的内容到任务或 PR 描述中，并在实现前填写。

## 模块

- 名称：
- 负责人/执行 Agent：
- 业务职责：
- 本次范围：
- 明确不做：
- 目标档位：简单 Service / 复杂 Application+Domain+Contract
- 升级触发器：无 / 多驱动 / 第三方 SDK / 状态流转 / 复用规则 / 外部副作用一致性 / 独立测试
- 允许修改的目录：
- 禁止修改的契约：

## 行为基线

- 入口（Route/API/Command）：
- 输出与错误：
- 数据读写：
- 外部副作用：
- 当前验证命令或测试：
- 真实请求样例：

## 依赖

- 调用者：
- 当前依赖：
- 目标 Port/契约：无 / `modules/<Module>/Contract/*`
- Infrastructure 适配器：
- 需要收紧的自动规则：
- 认证输入：无 / `AuthContext` 的哪些字段：

## 迁移提交

简单 Service：

1. 增加特征测试，不改行为。
2. 收口 Controller、Validate、Resource 与 Service 边界。
3. 删除旧入口和临时适配。
4. 更新架构检查与文档。

复杂模块：

1. 增加特征测试，不改行为。
2. 提取稳定 Contract 或上下文对象。
3. 迁移一个完整 Application 用例及其调用者。
4. 必要时提取 Domain 规则，且必须消除真实重复或提供纯测试价值。
5. 删除旧实现和临时适配。
6. 更新架构检查与文档。

## 完成检查

- [ ] 原行为验证通过
- [ ] 无新反向依赖
- [ ] 无双轨实现
- [ ] Controller 未直接访问 Model
- [ ] 简单 Service 未读取 Request、未引用 Controller/Middleware
- [ ] Application 未直接依赖 Model/Db/Infrastructure
- [ ] Domain 未依赖 ThinkPHP、ORM、缓存、SDK 或 `ApiException`
- [ ] Service 所需身份和能力由调用者显式传入
- [ ] 公开路由未假设 `AuthContext` 必然存在
- [ ] 错误与日志语义一致
- [ ] 自动检查覆盖新边界
- [ ] 回退路径明确

## 交给普通业务 Agent 时

任务描述必须给出以下信息，不能只写“参考现有模块”：

1. 一个明确的 Route/API 入口和预期响应。
2. 允许修改的文件或目录，以及不可改变的公共契约。
3. 需要复用的 Service 用例、Port 或 `AuthContext` 字段。
4. 至少一条行为测试和一条真实请求验收方式。
5. 必须执行的根检查与模块检查。

Auth 是后端第一个完成的复杂样板。业务 Agent 应复制它的依赖方向和验证方式，而不是复制
Auth 的目录名称或把非认证业务放入 `service/Auth`。普通模块可以继续使用简单 Service，只有触发复杂档条件时才引入
Application、Domain、Contract。
