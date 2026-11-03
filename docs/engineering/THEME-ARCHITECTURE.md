# 前台主题架构

LoveCards 生产环境只支持一种正式主题运行模式：`mode: "spa"`。
主题由 React/Vite 或其他前端工具构建为静态文件，PHP 只负责 Host 绑定、Release 选择、入口返回和资源服务；业务数据全部由浏览器通过 SDK 获取。

## 发布链路

```text
前端源码
  -> 构建 dist/
  -> theme.json + dist/ 打包 ZIP
  -> 安全安装器校验
  -> themes/.releases/{id}/{version}-{digest}/
  -> Host Binding 激活
  -> Theme Gateway 返回 entry
  -> 浏览器读取 Bootstrap 并通过 SDK 请求 /api
```

生产环境不运行 Node.js，也不使用可变 Web Root 主题目录。旧 `engine: html`、ThinkPHP 模板和伪 SSR Runtime 不属于正式运行链。

## Manifest v1

```json
{
  "schemaVersion": 1,
  "id": "default",
  "name": "LoveCards 默认主题",
  "version": "1.0.0",
  "mode": "spa",
  "entry": "dist/index.html",
  "requires": { "sdk": "^2.0.0" },
  "config": {
    "siteName": {
      "type": "string",
      "label": "站点名称",
      "default": "LoveCards"
    }
  }
}
```

`id` 是机器标识，`name` 是展示名称，`version` 必须是 SemVer，`entry` 必须是主题根目录内的相对 HTML 文件。Manifest 不再接受 `engine`、`settings`、`routes` 或 `notFound`。

## Host Binding

主题文件不存数据库。数据库只保存当前 Host 使用的不可变 Release 和 Host 配置：

```text
hostname -> theme_id + release_key + config_overrides
```

因此不同域名可以使用同一主题的不同 Release 或不同配置，主题文件仍始终从 Release 文件系统目录读取。

## 页面与资源请求

所有非保留的 GET/HEAD 页面路径都返回当前 Release 的 `entry`，因此 React Router 的深层刷新不会被后端 404 截断。以下路径由平台保留：

```text
/api  /admin  /system  /storage  /_lc
```

页面响应使用 `no-store`。Release 资源使用带 Release 标识的 URL、ETag 和 immutable 缓存；页面和资源都必须通过当前 Host Binding 校验。

## Bootstrap

后端在 HTML 中注入非执行 JSON：

```html
<script id="lc-bootstrap" type="application/json">
{
  "apiUrl": "/api",
  "theme": "default",
  "mode": "spa",
  "release": "1.0.0-abcd1234",
  "assetsBase": "/_lc/theme-assets/default/1.0.0-abcd1234/",
  "config": {}
}
</script>
```

主题前端通过 `document.getElementById('lc-bootstrap')` 读取。配置使用安全 JSON 编码；不依赖 `unsafe-inline` 执行脚本。

## 管理 API

主题管理以 `id + release` 为机器定位键，`name` 只用于展示。上传生成 Release，激活绑定当前 Host，配置更新要求携带当前 Release，正在被任一 Host 使用的 Release 不得删除。
