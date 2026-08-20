# LoveCards 默认主题设计工作区

本目录只保存默认主题的设计事实源、可审阅蓝图和决策记录。可运行 React 前端位于 `../src/`，生产代码不得导入本目录文件。

## 从这里开始

1. 产品目标与反例：[`PRODUCT.md`](./PRODUCT.md)
2. 视觉 Token 与总原则：[`DESIGN.md`](./DESIGN.md)
3. 规范导航：[`docs/README.md`](./docs/README.md)
4. Dialog / Modal 弹窗规范：[`docs/specifications/DIALOGS.md`](./docs/specifications/DIALOGS.md)
5. 蓝图状态与正式入口：[`docs/registries/BLUEPRINTS.md`](./docs/registries/BLUEPRINTS.md)
6. 可视化总览：[`blueprints/index.html`](./blueprints/index.html)

## 目录职责

```text
ui/
├─ README.md                 # 唯一人工入口
├─ PRODUCT.md                # 产品与品牌策略
├─ DESIGN.md                 # 视觉系统总规范
├─ standards/                # 基础、组件、卡片、内容与无障碍
├─ docs/
│  ├─ README.md              # 文档导航
│  ├─ registries/            # 页面、蓝图、卡片、SDK 能力注册表
│  ├─ specifications/        # 交互与功能表面规范
│  ├─ implementation/        # 前后端落地映射与协作要求
│  ├─ decisions/             # 仍有效的设计决策
│  └─ archive/               # 已完成或已失效的阶段资料
├─ blueprints/               # 可运行的设计参考板
│  └─ archive/               # 不再作为正式方向的探索版本
├─ assets/
│  ├─ blueprints/            # 正式参考图
│  ├─ feed/                  # 蓝图使用的媒体资产
│  └─ archive/               # 历史资产
└─ .impeccable/              # 设计 Agent 元数据，不是规范入口
```

## 工作规则

- 新任务先读本文件，再读对应注册表和规范，不遍历历史目录猜测真源。
- 蓝图只有注册表标记为 `canonical` 时才可作为施工依据；`needs-sync` 必须先对照当前 React 实现更新。
- 规范文件名不携带日期、`final`、`set` 或阶段编号；版本与状态写在文件正文。
- `archive/` 只用于追溯，不得被正式蓝图或生产代码引用。
- 同一规则只在一个文件定义，其他文件使用链接引用。
- SDK 能力以生成契约为准；设计资料只记录 UI 如何响应能力，不复制接口定义。

## 本地审阅

启动默认主题开发服务后访问 `/ui/blueprints/index.html`。该入口仅供设计审阅，不进入产品导航或生产构建。
