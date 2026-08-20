# LoveCards Default Theme

`apps/default-theme` 是 LoveCards 用户前端的可运行源码，也是可独立部署的默认 SPA 主题。React 页面通过 `@lovecards/sdk` 使用后端能力，不直接依赖后端文件或 SDK 源码。

## 目录边界

- `src/`：页面、路由、交互、SDK adapter、运行样式和测试。
- `src/assets/`：运行和显式设计预览所需的前端资产。
- `ui/`：设计规范、蓝图、参考图和设计决策；不进入产品依赖图或生产构建。
- `dist/`：构建产物，不是源码。
- `theme.json`：LCV3 SPA 主题 Manifest，入口固定为 `dist/index.html`。
- `release/`：本地生成的主题 ZIP，不提交仓库。

`src/` 不得 import `ui/` 中的 HTML、CSS 或资产。需要进入产品的视觉资产必须明确放入 `src/assets/`，以保证设计参考可独立归档。

## 本地运行

在仓库根目录执行：

```bash
npm install
npm run dev:default-theme
```

产品默认地址是 `http://127.0.0.1:4174/`，设计蓝图总览是 `http://127.0.0.1:4174/ui/blueprints/all-blueprints-index.html`。蓝图地址只用于开发审阅，不进入生产构建。

## 数据模式

- `VITE_DATA_MODE=api`：使用 `VITE_API_URL` 配置的真实 API。
- `VITE_DATA_MODE=design-preview`：使用只读预览数据，操作不会写入后端。

API 模式缺少 `VITE_API_URL` 时必须显示配置错误，不得自动回退为演示数据。

LCV3 托管时，后端通过 `#lc-bootstrap` 注入 `apiUrl`、主题 Release 和配置。Bootstrap 的 `apiUrl` 优先于构建环境变量；前端不得硬编码主题 Release。

## 构建与打包

```bash
npm run package:default-theme
```

命令会构建相对资源路径的 `dist/`，并生成 `apps/default-theme/release/default-1.0.0.zip`。ZIP 根目录只包含 `theme.json`、`README.md` 和 `dist/`，可直接通过主题管理 API 上传。

## 验证

```bash
npm run check:default-theme
npm run build:default-theme
```

视觉与页面完成标准见 [`ui/README.md`](ui/README.md)。
