# LoveCards Admin

这是 LoveCards 的 Nuxt 管理端，部署路径为 `/admin/`，以 SPA 方式运行。

管理端的后端请求只能通过 `~/lib/api.ts` 提供的 `@lovecards/sdk` 客户端完成。页面可以直接调用 SDK；Pinia 只保存跨页面共享状态。

## 开发

在仓库根目录执行：

```bash
npm ci
npm run dev:admin
```

开发服务器默认运行在 `http://127.0.0.1:3000/admin/`，`/api` 由 Nuxt 代理到后端。

## 验证

```bash
npm run typecheck:admin
npm run build:admin
npm run test:admin:e2e
```

完整目录、状态、通知和 SDK 边界见 [管理端架构规范](../../docs/engineering/ADMIN-ARCHITECTURE.md)。
