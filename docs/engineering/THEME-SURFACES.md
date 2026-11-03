# 主题发布包边界

正式主题源码位于 `apps/default-theme`，后端只接收其构建发布包，不直接引用前端源码。

```text
theme.zip
├─ theme.json
├─ preview.png        # 可选，平台元数据
├─ README.md           # 安装包文档，不通过主题资源网关暴露
└─ dist/
   ├─ index.html
   └─ assets/*
```

安装后主题进入：

```text
apps/backend/themes/.releases/{themeId}/{version}-{digest}/
```

浏览器只能通过当前 Host 绑定的 `/_lc/theme-assets/{themeId}/{release}/` 读取允许的 Release 资源。
