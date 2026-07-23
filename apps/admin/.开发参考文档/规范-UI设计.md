# LoveCards Admin UI/UX 设计规范

## 1. 按钮系统

### 1.1 按钮类型与使用场景

| 类型 | Variant | 样式 | 使用场景 |
|------|---------|------|---------|
| **深度按钮** | `elevated` + `bg-accent` | 有阴影、有圆角、粉色背景 | 登录、返回、重试、提交（页面级）、设为主题等**结构性跳转**，与分页按钮样式一致 |
| **平面按钮** | `flat` | 无阴影、平滑 | 保存、确认、删除、批量操作等**表单内确认操作** |
| **文本按钮** | `text` | 无背景 | 取消、上传、编辑、删除、筛选等**辅助操作** |
| **图标按钮** | `text` + icon | 无背景 | 工具性操作：编辑、删除、筛选、设置等 |

### 1.2 颜色规范

| 场景 | Color | 示例 |
|------|-------|------|
| 主要操作 / 页面跳转 | `primary` (#3F51B5) | App Bar、登录（跨页面跳转） |
| 次要操作 / 表单确认 | `accent` (#FF4081) | 保存、确认、登录（在当前表单内） |
| 成功操作 | `success` (#43A047) | 设为主题成功 |
| 危险操作 | `error` (#F44336) | 删除 |

### 1.3 按钮组合示例

```vue
<!-- 深度按钮：登录（跨页面跳转），与分页按钮样式一致 -->
<v-btn color="accent" variant="elevated" size="small" block @click="handleLogin">登录</v-btn>
```

---

## 2. 表单输入

### 2.1 输入框样式

```vue
<!-- 标准输入框 -->
<v-text-field
    v-model="value"
    label="标签名"
    variant="outlined"
    color="accent"
/>

<!-- 搜索框 -->
<v-text-field
    v-model="search"
    label="搜索"
    variant="outlined"
    color="accent"
    append-inner-icon="mdi-magnify"
    density="compact"
/>

<!-- 下划线输入框（对话框内） -->
<v-text-field
    v-model="value"
    label="用户名"
    variant="underlined"
    color="accent"
/>
```

### 2.2 激活颜色

所有输入框激活状态统一使用 `color="accent"`（粉色 #FF4081），与其他页面保持一致。

---

## 3. 卡片与对话框

### 3.1 卡片样式

```vue
<!-- 标准卡片 -->
<v-card class="pa-6" max-width="500" elevation="4">
    <v-card-title class="text-primary text-h4 text-center mb-4">
        标题
    </v-card-title>
    <v-divider class="mb-6"></v-divider>
    <v-card-text>内容</v-card-text>
    <v-card-actions>操作</v-card-actions>
</v-card>
```

### 3.2 对话框操作按钮布局

```vue
<v-card-actions>
    <v-spacer></v-spacer>
    <v-btn color="accent" variant="text" @click="cancel()">取消</v-btn>
    <v-btn color="accent" variant="flat" @click="confirm()">确认</v-btn>
</v-card-actions>
```

---

## 4. 颜色系统

### 4.1 主题色

| 角色 | 颜色 | HEX | 用途 |
|------|------|-----|------|
| Primary | Indigo | #3F51B5 | App Bar、主要操作 |
| Secondary | Amber | #FFC107 | 次要元素 |
| Accent | Pink | #FF4081 | 强调、输入框、确认操作 |
| Error | Red | #F44336 | 错误、危险操作 |
| Info | Blue | #2196F3 | 信息提示 |
| Success | Green | #43A047 | 成功状态 |
| Warning | Orange | #FF9800 | 警告 |

### 4.2 文本颜色

| 类名 | 用途 |
|------|------|
| `text-primary` | 主要文字（使用主题色） |
| `text-medium-emphasis` | 次要说明文字 |
| `text-caption` | 辅助说明、提示 |

---

## 5. 布局规范

### 5.1 App Bar

```vue
<v-app-bar :elevation="3" color="primary">
    <v-app-bar-title>标题</v-app-bar-title>
</v-app-bar>
```

### 5.2 页面容器

```vue
<v-container class="d-flex align-center justify-center" style="height: calc(100vh - 64px)">
    <!-- 垂直居中内容 -->
</v-container>
```

---

## 6. 开发环境标识

### 6.1 Dev 登录页设计规范

- **App Bar**: 显示 "LoveCards - Dev 登录"
- **标题**: "Dev Token 登录"（text-primary）
- **输入框**: `color="accent"`, `variant="outlined"`
- **登录按钮**: `color="accent"`（深度按钮，跨页面跳转）
- **提示文字**: `text-caption`, `text-medium-emphasis`
- **警告提示**: `variant="tonal"`, `type="error"`

---

## 7. 快速对照表

### 场景 → 按钮样式

| 场景 | Color | Variant | Size | 示例 |
|------|-------|---------|------|------|
| 登录 | `accent` | `elevated` | `small` | `<v-btn color="accent" variant="elevated" size="small">登录</v-btn>` |
| 返回 | `accent` | 默认 | `<v-btn color="accent">返回</v-btn>` |
| 重试 | `primary` | 默认 | `<v-btn color="primary">重试</v-btn>` |
| 保存 | `accent` | `flat` | `<v-btn color="accent" variant="flat">保存</v-btn>` |
| 确认 | `accent` | `flat` | `<v-btn color="accent" variant="flat">确认</v-btn>` |
| 删除 | `accent` | `flat` | `<v-btn color="accent" variant="flat">删除</v-btn>` |
| 取消 | `accent` | `text` | `<v-btn color="accent" variant="text">取消</v-btn>` |
| 编辑 | - | `text` | `<v-btn icon="mdi-pencil" variant="text">` |
| 筛选 | - | `text` | `<v-btn icon="mdi-filter" variant="text">` |
