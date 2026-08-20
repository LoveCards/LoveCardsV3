# SPA 主题前端交接

后端已固定单一 SPA 主题协议。前端主题 agent 只需要交付构建发布包，不需要实现 PHP Runtime。

## 发布包

```text
theme.json
preview.png       # 可选
README.md         # 可选
dist/
├─ index.html
└─ assets/*
```

`theme.json` 的 `entry` 必须为 `dist/index.html` 或其他主题根目录内的相对 HTML 文件，`mode` 必须为 `spa`。

## Vite 资源

构建必须使用相对资源基址，例如：

```js
export default { base: './' }
```

后端会在 HTML 中注入 `<base>`，将相对资源解析到当前不可变 Release。不要把 Release ID 写入前端源码。

## Bootstrap

入口页面可以读取：

```js
const node = document.getElementById('lc-bootstrap')
const context = JSON.parse(node?.textContent || '{}')
// context.apiUrl === '/api'
// context.theme / context.mode / context.release
// context.assetsBase / context.config
```

业务数据统一通过 `@lovecards/sdk` 获取，生产环境不依赖 Node.js 服务。

社区能力（草稿、主页/关注、通知/私信、恢复、收藏/历史、隐私/举报）以及 SDK 方法清单见 `FRONTEND-AGENT-HANDOFF.md`。

## 深层链接

Theme Gateway 对非保留 GET/HEAD 路径返回同一 Release 的 SPA entry。默认主题当前使用：

```text
/post/:cardId
/post/:cardId/thread/:commentId
/media/:cardId?index=1
```

底层公开文件 URL 是 `/media/:hash`，由后端先检查文件状态与公开性。评论深链应补取目标/父评论，渲染后再展开并定位；不能把“评论不可见”误处理为滚动到第一条评论。

## 激活验证

上传 ZIP 后，管理端使用返回的 `id + release` 激活当前 Host。页面验证路径至少包括 `/`、一个深层路由和一项静态资源加载。

仓库根 `npm run test:theme:e2e` 会验证 entry fallback、公开 API、深层路由、Bootstrap/Release 资源，以及在提供测试账号时的登录、发布和评论流程。
