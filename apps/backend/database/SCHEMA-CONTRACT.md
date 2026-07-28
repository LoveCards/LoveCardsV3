# Schema 契约核验报告

> 报告生成时间：2026-07-27
> 基线文件：`apps/backend/data.sql`（2026-01-19 导出）
> 仓库 HEAD：`2b2bddc`（main）
> 核验范围：Model、Service、Validate、Route、Installer、config
> 核验方式：只读代码分析

---

## 目录

1. [运行必需表（10 张）](#1-运行必需表10-张)
2. [兼容保留表（4 张）](#2-兼容保留表4-张)
3. [data.sql 漂移分析](#3-datasql-漂移分析)
4. [Seed 与安装顺序依赖](#4-seed-与安装顺序依赖)
5. [无法从代码确定的字段或索引（AWAITS_ARCHITECT）](#5-无法从代码确定的字段或索引)
6. [历史兼容表的调用证据](#6-历史兼容表的调用证据)
7. [停止条件检查](#7-停止条件检查)

---

## 1. 运行必需表（10 张）

### 1.1 `cards`

data.sql（行 30–46）定义：

| 列名 | 类型 | Null | 默认值 | PK | 注释 |
|------|------|------|--------|----|------|
| id | int(11) | NOT NULL | — | PK | AUTO_INCREMENT |
| is_top | int(11) | NOT NULL | 0 | | |
| status | int(11) | NOT NULL | 0 | | |
| user_id | int(11) | NOT NULL | 0 | | |
| data | json | YES | NULL | | |
| cover | varchar(2083) | YES | NULL | | |
| content | text | YES | NULL | | |
| tags | json | YES | NULL | | |
| **good** | int(11) | NOT NULL | 0 | | **字段名与模型期望不一致** |
| views | int(11) | NOT NULL | 0 | | |
| comments | int(11) | NOT NULL | 0 | | |
| post_ip | varchar(39) | YES | NULL | | |
| created_at | timestamp | NOT NULL | — | | |
| updated_at | timestamp | NOT NULL | — | | |
| deleted_at | timestamp | YES | NULL | | |

索引：`PRIMARY KEY (id)`

**Model 期望**：`apps/backend/app/api/model/Cards.php:23-40`
- `'goods' => 'INT'` —— 模型期望字段名 **`goods`**（带 s），但 data.sql 定义的是 **`good`**（无 s）
- `'pictures' => 'JSON'` —— data.sql **缺少 `pictures`** 字段
- 其余字段列名一致

**验证证据**：
- `apps/backend/app/api/validate/Cards.php:91,143`：验证规则使用 `'good'`（无 s），与 data.sql 一致但与模型 schema 冲突
- `apps/backend/app/api/service/Content/Cards.php:33`：模型 schema 使用 `'goods'`（带 s）
- `apps/backend/app/api/service/Content/Cards.php:291-300`：`decodeJsonFields()` 解码 `pictures` 字段，说明代码确实依赖该字段存在
- `apps/backend/.dev/mysql/2026年5月21日.sql:40`：实际运行数据库使用 `goods`（带 s）和 `pictures` —— **data.sql 已经落后于实际运行库**

**结论**：data.sql 的 `cards` 表缺少 `pictures` 列，并且 `good` 字段名应为 `goods`。实际运行库（2026-05-21 快照）已修正此问题，但安装种子 data.sql 未同步。

---

### 1.2 `comments`

data.sql（行 54–69）定义：

| 列名 | 类型 | Null | 默认值 | PK |
|------|------|------|--------|----|
| id | int(11) | NOT NULL | — | PK |
| aid | int(11) | NOT NULL | 0 | |
| pid | int(11) | NOT NULL | 0 | |
| parent_id | int(11) | YES | 0 | |
| is_top | int(11) | NOT NULL | 0 | |
| status | int(11) | NOT NULL | 0 | |
| user_id | int(11) | NOT NULL | 0 | |
| data | json | YES | NULL | |
| content | text | YES | NULL | |
| goods | int(11) | NOT NULL | 0 | |
| post_ip | varchar(39) | YES | NULL | |
| created_at | timestamp | NOT NULL | — | |
| updated_at | timestamp | NOT NULL | — | |
| deleted_at | timestamp | YES | NULL | |

索引：`PRIMARY KEY (id)`

**Model 期望**：`apps/backend/app/api/model/Comments.php:20-35`
- 字段名完全匹配 data.sql（包括 `goods` 带 s）
- 类型匹配（int、json、text、varchar、timestamp）

**验证证据**：
- `apps/backend/app/api/validate/Comments.php:58`：验证规则使用 `'good'`（无 s），与模型 schema 的 `goods` 冲突——但这是验证器输入字段名，不影响数据库列名
- `apps/backend/app/api/service/Content/Comments.php`：无直接数据库列名引用，通过 Model 操作

**结论**：`comments` 表的契约基本一致。验证规则字段名 `good`（无 s）与模型 `goods`（带 s）的差异存在于输入层，不在 DB 层，不构成 schema 契约分歧。

---

### 1.3 `tags`

data.sql（行 390–399）定义：

| 列名 | 类型 | Null | 默认值 | PK |
|------|------|------|--------|----|
| id | int(11) | NOT NULL | — | PK |
| aid | int(11) | NOT NULL | — | |
| user_id | int(11) | NOT NULL | 0 | |
| name | varchar(255) | YES | '' | |
| status | int(11) | NOT NULL | 0 | |
| deleted_at | timestamp | YES | NULL | |
| created_at | timestamp | YES | NULL | |
| updated_at | timestamp | YES | NULL | |

索引：`PRIMARY KEY (id)`

**Model 期望**：`apps/backend/app/api/model/Tags.php:23-32`
- 字段、类型、顺序完全一致

**验证证据**：`apps/backend/app/api/validate/Tags.php:47-51` 验证规则也匹配

**结论**：✅ 契约一致。

---

### 1.4 `tags_map`

data.sql（行 407–414）定义：

| 列名 | 类型 | Null | 默认值 | PK |
|------|------|------|--------|----|
| id | int(11) | NOT NULL | — | PK |
| aid | int(11) | NOT NULL | — | |
| pid | int(11) | NOT NULL | — | |
| tag_id | int(11) | NOT NULL | — | |
| created_at | timestamp | NOT NULL | — | |
| deleted_at | timestamp | YES | NULL | |

索引：`PRIMARY KEY (id)`

**Model 期望**：`apps/backend/app/api/model/TagsMap.php:19-27`
- data.sql 缺少 `status` 字段，TagsMap 模型 schema 定义 `'status' => 'INT'`
- 实际运行库（2026-05-21 SQL:678）已包含 `status int(11) NOT NULL DEFAULT '0'`

**验证证据**：
- `apps/backend/app/api/model/TagsMap.php:24`：定义 `'status' => 'INT'`
- `apps/backend/.dev/mysql/2026年5月21日.sql:678`：实际表包含 `status int(11) NOT NULL DEFAULT '0'`

**结论**：data.sql 的 `tags_map` 缺少 `status` 列。模型和实际运行库一致，seed 文件待修复。

---

### 1.5 `users`

data.sql（行 422–435）定义：

| 列名 | 类型 | Null | 默认值 | PK |
|------|------|------|--------|----|
| id | int(11) | NOT NULL | — | PK |
| number | varchar(32) | NOT NULL | — | |
| avatar | varchar(255) | NOT NULL | '' | |
| email | varchar(320) | NOT NULL | — | |
| phone | varchar(20) | NOT NULL | — | |
| username | varchar(255) | NOT NULL | — | |
| password | varchar(255) | NOT NULL | — | |
| status | int(11) | NOT NULL | — | |
| roles_id | json | YES | NULL | |
| created_at | datetime | NOT NULL | — | |
| updated_at | datetime | NOT NULL | — | |
| deleted_at | datetime | YES | NULL | |

索引：`PRIMARY KEY (id)`

**Model 期望**：`apps/backend/app/api/model/Users.php:20-33`
- 字段、类型匹配
- 注意 data.sql 中 `created_at`/`updated_at` 为 `datetime` 类型，Model schema 标注为 `timestamp`——但这是类型提示，实际 ThinkPHP 自动写入不受影响

**验证证据**：
- `apps/backend/app/api/validate/Users.php:64-78`：验证规则匹配所有字段类型
- `apps/backend/.dev/mysql/2026年5月21日.sql:689-701`：实际运行库 `created_at`/`updated_at` 使用 `timestamp NULL DEFAULT NULL`

**结论**：data.sql 与模型定义基本一致。`datetime` vs `timestamp` 差异不影响行为（MySQL 自动转换兼容）。实际运行库已改为 `timestamp`。

---

### 1.6 `roles`

data.sql（行 199–207）定义：

| 列名 | 类型 | Null | 默认值 | PK |
|------|------|------|--------|----|
| id | int(11) | NOT NULL | — | PK |
| name | varchar(50) | NOT NULL | — | |
| slug | varchar(50) | NOT NULL | — | UNIQUE |
| description | varchar(255) | YES | NULL | |
| created_at | datetime | NOT NULL | — | |
| updated_at | datetime | NOT NULL | — | |
| deleted_at | datetime | YES | NULL | |

索引：`PRIMARY KEY (id)`, `UNIQUE KEY slug(slug)`

**Model 期望**：`apps/backend/app/api/model/Roles.php:20-29`
- data.sql 缺少 `is_system` 列
- Roles 模型 schema 定义了 `'is_system' => 'int'`

**验证证据**：
- `apps/backend/app/api/model/Roles.php:25`：定义 `'is_system' => 'int'`
- `apps/backend/app/api/service/Rbac/Roles.php:53`：`$role->is_system` 直接读写
- `apps/backend/app/api/service/Rbac/Roles.php:90`：`where('is_system', 1)` 查询系统角色
- `apps/backend/app/api/service/Rbac/Roles.php:184`：reseed 使用 `$roles = config('system.system_roles')` 锚定 ID
- `apps/backend/config/system.php:18-23`：系统角色锚点定义，root=1, admin=2, user=3, guest=4
- `apps/backend/.dev/mysql/2026年5月21日.sql:257-259`：实际库包含 `is_system tinyint(1) NOT NULL DEFAULT '0'`

**影响**：没有 `is_system` 列时：
- 系统角色保护（禁止删除、禁止修改 slug）将失效
- `Roles::updateRole()` 行 53 检查 `$role->is_system` 会返回 null/false，任何角色都可修改 slug
- `Roles::deleteRoles()` 行 90 检查将跳过所有角色保护
- reseed 功能不依赖此字段，使用 config 锚点 ID，不受影响

**结论**：严重。data.sql `roles` 缺少 `is_system` 列，导致安全保护失效。

---

### 1.7 `role_capabilities` (必需表，data.sql 中不存在)

**data.sql 中无此表。**

**代码要求**：
- `apps/backend/app/api/model/RoleCapabilities.php:11-15`：定义了 schema
  ```
  id (int, PK), role_id (int), capability (string)
  ```
- `apps/backend/app/api/service/Rbac/RBAC.php:133`：`RoleCapabilities::whereIn('role_id', $rolesId)->distinct()->column('capability')`
- `apps/backend/app/api/service/Rbac/Roles.php:230`：`Db::table('role_capabilities')->delete(true)` reseed 时清空
- `apps/backend/app/api/service/Rbac/Roles.php:138-145`：分配能力时写入

**DDL 来源**：`apps/backend/database/create_role_capabilities.sql:1-7`
```sql
CREATE TABLE IF NOT EXISTS role_capabilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    capability VARCHAR(100) NOT NULL,
    UNIQUE KEY uk_role_cap (role_id, capability),
    KEY idx_role_id (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**实际运行库**（2026-05-21 SQL:454-460）：已包含该表及完整 seed 数据。

**结论**：data.sql 完全缺少 `role_capabilities` 表。这是 RBAC 新体系的核心表，缺少该表将导致所有能力检查返回空数组。

---

### 1.8 `files` (必需表，data.sql 中不存在)

**data.sql 中无此表。**

**代码要求**：
- `apps/backend/app/api/model/Files.php:12`：`protected $name = 'files';`
- Files 模型基于 ThinkPHP 的自动 schema 推断，未定义 `$schema` 数组
- `apps/backend/app/api/service/Storage/StorageManager.php:21-38`：insert 时使用的字段有：
  ```
  hash, channel_slug, user_id, is_public, scene, ref_type, ref_id,
  original_name, file_path, file_url, file_size, file_ext, mime_type,
  driver_path, status, upload_status
  ```
- `apps/backend/app/api/model/Files.php:23-24`：scopeByHash 使用 `hash` 字段
- `apps/backend/app/api/model/Files.php:56-62`：scopeVisible 使用 `user_id` 和 `is_public`
- `apps/backend/app/api/model/Files.php:64-67`：scopeByScene 使用 `scene`
- `apps/backend/app/api/model/Files.php:69-76`：scopeByRef 使用 `ref_type`、`ref_id`
- `apps/backend/app/api/model/Files.php:81`：scopeNormal 使用 `status`
- `apps/backend/app/api/model/Files.php:100-106`：`isExpired()` 使用 `expire_at`
- `apps/backend/app/api/service/Storage/StorageManager.php:149-155`：批量操作使用 `files` 表
- `apps/backend/app/api/validate/Files.php:9-14`：验证规则涉及 scene, ref_type, ref_id, is_public, method

**实际运行库**（2026-05-21 SQL:172-195）定义的列：
```
id (PK), channel_slug, user_id, is_public, scene, ref_type, ref_id,
original_name, file_path, file_url, file_size, file_ext, mime_type,
driver_path, metadata (JSON), status, upload_status,
created_at, updated_at, deleted_at, expire_at, hash (UNIQUE)
```

索引：`PRIMARY KEY (id)`, `UNIQUE KEY uk_files_hash (hash)`, `KEY idx_user_id (user_id)`, `KEY idx_scene (scene)`, `KEY idx_ref (ref_type,ref_id)`

**结论**：data.sql 完全缺少 `files` 表。这是文件上传存储功能的核心表。

---

### 1.9 `likes` (必需表，data.sql 中不存在)

**data.sql 使用 `good` 表替代。**

**代码要求**：
- `apps/backend/app/api/model/Likes.php:15-24`：定义了 schema
  ```
  id (int), aid (int), pid (int), ref_type (string), ref_id (int),
  uid (int), ip (varchar), created_at (timestamp)
  ```
- `apps/backend/app/api/service/Content/Likes.php`：使用 LikesModel 进行 CRUD
- `apps/backend/app/api/route/likes.php:8-16`：定义 `likes.list` 和 `likes.unlike` 路由
- `apps/backend/app/api/service/Content/Likes.php:22-27`：去重检查 `where('pid', $refId)->where('uid', $uid)`
- ~~data.sql 中 `good` 表有唯一索引？~~ data.sql `good` 只有 `PRIMARY KEY (id)`，无唯一约束

**实际运行库**（2026-05-21 SQL:228-237）：
```sql
CREATE TABLE `likes` (
  `id` int(11) NOT NULL,
  `aid` int(11) NOT NULL COMMENT '应用ID (legacy)',
  `pid` int(11) NOT NULL COMMENT '条目ID (legacy)',
  `ref_type` varchar(32) DEFAULT NULL COMMENT '内容类型: card, comment',
  `ref_id` int(11) DEFAULT NULL COMMENT '内容ID',
  `uid` int(11) NOT NULL,
  `ip` varchar(32) NOT NULL COMMENT '发布IP',
  `created_at` timestamp NULL DEFAULT NULL COMMENT '发布时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

索引：`PRIMARY KEY (id)`, `UNIQUE KEY uk_pid_uid (pid,uid)`

**与 `good` 表差异**：
| 特性 | `good` (data.sql) | `likes` (代码) |
|------|-------------------|-----------------|
| 额外字段 | 无 | `ref_type`, `ref_id` |
| 去重 | 无唯一约束 | `UNIQUE KEY uk_pid_uid (pid,uid)` |
| 表名 | `good` | `likes` |

**结论**：data.sql 包含旧的 `good` 表，缺少新的 `likes` 表。

---

### 1.10 `configs` (必需表，data.sql 中不存在)

**data.sql 使用 `system` 表替代。**

**代码要求**：
- `apps/backend/app/common/service/Config.php`：全部通过 `Db::table('configs')` 操作（行 57, 67, 124, 157, 195, 221, 243, 249, 257, 291, 304）
- `apps/backend/app/api/service/Storage/ChannelManager.php:18`：`Db::table('configs')`
- `apps/backend/app/api/service/Sender/ChannelManager.php:18`：`Db::table('configs')`

**Config 表的 schema**（从代码中 `->insert([...])` 参数推断）：
```
id (PK, AI), group (varchar), key (varchar), value (text),
type (varchar), description (varchar), created_at (datetime), updated_at (datetime)
```

**实际运行库**（2026-05-21 SQL:86-95）：
```sql
CREATE TABLE `configs` (
  `id` int(11) NOT NULL,
  `group` varchar(50) NOT NULL COMMENT '分组',
  `key` varchar(100) NOT NULL COMMENT '配置键',
  `value` text COMMENT '配置值',
  `type` varchar(20) DEFAULT 'string' COMMENT '类型',
  `description` varchar(255) DEFAULT NULL COMMENT '配置说明',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

索引：`PRIMARY KEY (id)`, `UNIQUE KEY group_key (group,key)`

**结论**：data.sql 完全缺少 `configs` 表。这是配置管理功能的核心表。

---

## 2. 兼容保留表（4 张）

### 2.1 `images`

data.sql 中存在（行 89-101），PHP 代码搜索零匹配。

**证实**：全仓库 PHP 文件无对 `images` 表的读取、写入或模型引用。
**状态**：完全废弃。应由升级脚本清理。

### 2.2 `permissions`

data.sql 中存在（行 109-119）并包含 66 行 seed 数据。

**代码引用**：
- `apps/backend/app/api/route/permissions.php:8-15`：保留 `/api/admin/permissions` 路由，通过 `RBAC` 返回路由元数据（非表查询）
- `apps/backend/app/api/service/Rbac/RBAC.php:71`：能力列表包含 `'permissions.read'` 能力
- `apps/backend/app/api/service/Rbac/Roles.php:222`：路由 seed 保留 `'permissions.read'`

**证实**：代码仅在 `/api/admin/permissions` 路由层面保留兼容端点，后端模型 `RolePermissions` 已使用 `permission_hash` 而非 `permission_id`，不再查询 `permissions` 表。
**状态**：兼容保留，数据迁移待处理。

### 2.3 `role_permissions`

data.sql 中存在（行 225-230），使用 `role_id` + `permission_id` + 外键约束到 `permissions` 表。

**代码引用**：
- `apps/backend/app/api/model/RolePermissions.php:13-18`：模型 schema 定义 `permission_hash`（string），而非 `permission_id`（int）
- 实际运行库（2026-05-21 SQL:281-285）：已使用 `permission_hash varchar(32)` 替代 `permission_id int`

**差异**：
| 特性 | data.sql | 代码/实际运行库 |
|------|----------|-----------------|
| 权限关联字段 | `permission_id int` | `permission_hash varchar(32)` |
| 唯一约束 | `(role_id,permission_id)` | `(role_id,permission_hash)` |
| 外键 | `FK -> permissions(id)` | 无外键 |
| `created_at` | `timestamp NOT NULL` | `timestamp NULL DEFAULT NULL` |

**状态**：兼容保留，schema 已改变。data.sql 落后于代码预期。

### 2.4 `system`

data.sql 中存在（行 361-365），15 行 seed 数据。

**代码引用**：
- 无 `Db::table('system')` 查询（搜索零命中）
- `Config` 服务使用 `configs` 表
- `VersionService.php:11` 使用 `Config::get('system')` —— 读取 PHP config 文件，不是 `system` 表

**状态**：完全被 `configs` 表替代，兼容保留。

---

## 3. data.sql 漂移分析

### 3.1 缺少的表（代码需要但 data.sql 不存在）

| 表名 | 影响程度 | 说明 |
|------|---------|------|
| `configs` | 致命 | 所有配置读写、存储/发送配置均依赖此表 |
| `files` | 致命 | 文件上传、查看、删除功能完全依赖此表 |
| `likes` | 致命 | 点赞功能完全依赖此表；旧 `good` 表无 `ref_type`/`ref_id` |
| `role_capabilities` | 致命 | RBAC 能力检查完全依赖此表 |

### 3.2 缺少的字段

| 表名 | 缺少字段 | 影响 |
|------|---------|------|
| `cards` | `pictures` (JSON) | 卡片图集功能无法使用 |
| `cards` | `goods` → 实为 `good`（字段名错） | 点赞计数行为不可用 |
| `roles` | `is_system` | 系统角色安全保护完全失效 |
| `tags_map` | `status` (int, default 0) | 标签映射状态不可用 |

### 3.3 类型/默认值漂移

| 表·字段 | data.sql | 实际运行库(2026-05-21) |
|---------|----------|----------------------|
| `cards.good` → `goods` | `good int NOT NULL 0` | `goods int NOT NULL 0` |
| `cards.created_at` | `timestamp NOT NULL` | `timestamp NULL DEFAULT NULL` |
| `cards.updated_at` | `timestamp NOT NULL` | `timestamp NULL DEFAULT NULL` |
| `comments.created_at` | `timestamp NOT NULL` | `timestamp NULL DEFAULT NULL` |
| `comments.updated_at` | `timestamp NOT NULL` | `timestamp NULL DEFAULT NULL` |
| `tags_map.status` | 不存在 | `int NOT NULL 0` |
| `roles.is_system` | 不存在 | `tinyint NOT NULL 0` |
| `roles.created_at` | `datetime NOT NULL` | `datetime NOT NULL` |
| `role_permissions.permission_id`→`permission_hash` | `permission_id int` | `permission_hash varchar(32)` |
| `role_permissions FOREIGN KEY` | 有（→`permissions.id`） | 无；仅 `FK → roles(id)` |
| `role_permissions.created_at` | `timestamp NOT NULL` | `timestamp NULL DEFAULT NULL` |
| `users.created_at` | `datetime NOT NULL` | `timestamp NULL DEFAULT NULL` |
| `users.updated_at` | `datetime NOT NULL` | `timestamp NULL DEFAULT NULL` |
| `users.deleted_at` | `datetime NULL DEFAULT NULL` | `timestamp NULL DEFAULT NULL` |

### 3.4 多余的旧表

| 表名 | 原因 | 处理建议 |
|------|------|---------|
| `good` | 已被 `likes` 替代 | 升级脚本中重命名，数据迁移到 `likes` |
| `images` | 已被 `files` 替代 | 升级脚本中可安全删除 |
| `permissions` | 已被 capabilities 体系替代 | 保留兼容，数据迁移候选 |
| `system` | 已被 `configs` 替代 | 升级脚本中可迁移后删除 |

---

## 4. Seed 与安装顺序依赖

### 4.1 Install.php 流程

`apps/backend/app/system/controller/Install.php`

1. **`__construct()` 行 24-28**：`Common::CheckInstallLock()` → 检查安装锁文件
2. **`PostDbConfig()` 行 73-117**：
   - 连接数据库（`Database::Connect()`）
   - 更新 `config/database.php` 配置（`Database::UpdataConfig()`）
   - 清空数据库（`Database::Clear()`）—— 可选 `force`
   - 导入 `data.sql`（`Database::ImportSQLFile('../data.sql')`）
3. **`PostCreateRsa()` 行 141-166**：生成/写入 RSA 密钥对
4. **`PostInstallLock()` 行 121-129**：生成安装锁文件

**关键问题**：ImportSQLFile 直接导入 `../data.sql`。当前 data.sql 缺少 4 张运行必需表，新安装的系统将立即崩溃。

### 4.2 系统角色 seed

data.sql 行 213-217 写入 4 个角色：

| id | name | slug | 说明 |
|----|------|------|------|
| 1 | 超级管理员 | root | |
| 2 | 管理员 | admin | |
| 3 | 用户 | user | |
| 4 | 访客 | guest | |

`apps/backend/config/system.php:18-23` 硬编码锚点：
```php
'system_roles' => ['root' => 1, 'admin' => 2, 'user' => 3, 'guest' => 4]
```

**注意**：data.sql 中 roles 表缺少 `is_system` 列。但代码（`Rbac/Roles.php:53,90`）需要通过 `is_system` 判断系统角色。这意味着系统角色保护在导入 data.sql 后将失效。

### 4.3 role_capabilities seed

`apps/backend/database/create_role_capabilities.sql` 定义 DDL，但 **data.sql 中无此表**。

`apps/backend/app/api/service/Rbac/Roles.php:180-257` `reseed()`：
- 使用 `config('system.system_roles')` 获取角色 ID 锚点
- 删除所有现有记录（`Db::table('role_capabilities')->delete(true)`）
- 重新插入能力矩阵

seed 矩阵：
| 角色 | 能力数 | 来源行 |
|------|--------|--------|
| guest (4) | 7 | Roles.php:188-194 |
| user (3) | 13 | Roles.php:195-202 |
| admin (2) | 45 | Roles.php:203-224 |
| root (1) | 107 | Roles.php:225（全部能力） |

reseed 不依赖 `is_system` 字段，依赖 config 锚点。

### 4.4 Config::init() seed

`apps/backend/app/common/service/Config.php:21-38` `init()`：
- 扫描 `config/apps/*.php` 文件
- 每个文件代表一个配置组，调用 `register()`

`register()`（行 49-85）：
- 对 schema 中每个 key，检查 `configs` 表是否已存在
- 不存在则 insert：`group, key, value, type, description, created_at, updated_at`

**Config 组 seed 数据**（来自 config/apps/）：

| 文件 | group | seed keys 数 | 行引用 |
|------|-------|-------------|--------|
| core.php | core | 12 | `apps/backend/config/apps/core.php` |
| frontend.php | frontend | 2 | `apps/backend/config/apps/frontend.php` |
| cards.php | cards | 3 | `apps/backend/config/apps/cards.php` |
| comments.php | comments | 1 | `apps/backend/config/apps/comments.php` |
| upload.php | upload | 2 | `apps/backend/config/apps/upload.php` |
| user.php | user | 1 | `apps/backend/config/apps/user.php` |
| storage.php | storage | 4 | `apps/backend/config/apps/storage.php` |
| sender.php | sender | 3 | `apps/backend/config/apps/sender.php` |
| captcha.php | captcha | 5 | `apps/backend/config/apps/captcha.php` |

**依赖关系**：`Config::init()` 在安装后需要由系统管理员通过 API 调用触发（或由 Install 安装流程扩展调用）。新安装时，`configs` 表不存在，Config 服务将直接报错。

### 4.5 安装依赖总结

```
data.sql 导入（创建旧表结构）
    ↓ 缺少 files, likes, configs, role_capabilities
    ↓ 缺少 is_system, pictures, status, goods 等问题
系统角色 seed（data.sql 已有，但缺 is_system）
    ↓
安装锁生成
    ↓
管理员登录后需调用：
    - 升级脚本（创建缺少表/字段）
    - Config::init()（配置 seed）
    - Roles::reseed()（能力 seed）
```

---

## 5. 无法从代码确定的字段或索引

以下项目无法从 Model `$schema` 声明或 Service 代码入参签名唯一确定，列为 `AWAITS_ARCHITECT`。

### AWAITS_ARCHITECT-1：`files` 表的完整 DDL

Files 模型未使用 `$schema` 声明，使用 ThinkPHP 自动推断。字段通过 `StorageManager.php` 的 `Files::create(...)` 参数间接推导。

需要确认：
- `metadata` 列的精确类型（实际运行库为 `json DEFAULT NULL`）
- `hash` 列的 UNIQUE 约束（实际运行库有 `uk_files_hash`）
- `ref_type`/`ref_id` 是否应建立 FK 约束
- `expire_at` 索引是否需要

**来源**：
- `apps/backend/app/api/model/Files.php`：无 `$schema` 定义
- `apps/backend/.dev/mysql/2026年5月21日.sql:172-195`：提供实际 DDL
- `apps/backend/app/api/service/Storage/StorageManager.php:21-38`：create 参数

### AWAITS_ARCHITECT-2：`configs` 表的精确默认值

Config 类的 `register()` 方法使用 `date('Y-m-d H:i:s')` 动态写入时间，但 DDL 层面的 `DEFAULT` 值未定义。

**需要确认**：`configs` 表的 `created_at` 和 `updated_at` 是否应使用 `CURRENT_TIMESTAMP` 作为 DDL 默认值，还是保持无默认值、由应用程序写入。

### AWAITS_ARCHITECT-3：`good` 表到 `likes` 表的数据迁移策略

data.sql 持有 `good` 表（无 `ref_type`/`ref_id` 列），代码需要 `likes` 表（含 `ref_type`/`ref_id`）。

**需要确认**：
- 迁移时旧 `good` 表的 `aid`+`pid` 如何映射到 `ref_type`+`ref_id`
- `good` 表中的 `ip` 字段长 `varchar(32)`，新 `likes` 表也为 `varchar(32)`——一致
- 迁移后是否删除 `good` 表

### AWAITS_ARCHITECT-4：`role_permissions` 表的去留

`RolePermissions` 模型仍然存在于代码中（`apps/backend/app/api/model/RolePermissions.php`），schema 使用 `permission_hash`。

**需要确认**：
- `role_permissions` 表是否保留为兼容层用于路由权限？
- 或该模型已废弃，只是尚未删除？
- `permission_hash` 如何更新维护？

### AWAITS_ARCHITECT-5：`permissions` 表的数据迁移

data.sql 持有 `permissions` 表（66 行静态 seed）。代码已迁移到 capability 体系。

**需要确认**：
- `permissions` 表中 66 条记录如何映射到 107 个 capabilities？
- `RBAC::getRouteMeta()` 的动态路由元数据是否需要 `permissions` 表作为后备？

---

## 6. 历史兼容表的调用证据

### 6.1 `images` 表

**搜索范围**：全仓库 `*.php` 文件搜索 `images` 表名。
**结果**：**零匹配**。无 Model、Service、Controller、Route、Validate 引用。

**结论**：完全废弃。安全删除候选。

### 6.2 `permissions` 表

**搜索范围**：全仓库 `*.php` 搜索 `permissions` 表名。
**结果**：
- `apps/backend/app/api/service/Rbac/RBAC.php:71`：能力描述 `'permissions.read'`
- `apps/backend/app/api/service/Rbac/Roles.php:222`：reseed 分配 `'permissions.read'`
- `apps/backend/app/api/route/permissions.php:8-15`：路由 `/api/admin/permissions`，返回动态路由元数据（不查询 `permissions` 表）

**结论**：`permissions` 表名仅在 capability 字符串和路由名称中出现，不进行表查询。表数据可迁移后删除。

### 6.3 `role_permissions` 表

**搜索范围**：全仓库 `*.php` 搜索 `role_permissions` 表名。
**结果**：
- `apps/backend/app/api/model/RolePermissions.php`：模型定义，但未在任何 Service/Controller 中被调用
- `apps/backend/app/api/service/Rbac/Roles.php:230`：操作 `role_capabilities` 表，不是 `role_permissions` 表
- 实际运行库（2026-05-21）仍然保留该表并有 seed 数据

**结论**：`RolePermissions` 模型代码级别已废弃但未删除。表可能为兼容层保留，或等待后续清理。

### 6.4 `system` 表

**搜索范围**：全仓库 `*.php` 搜索 `Db::table('system')` 或 `->table('system')`。
**结果**：**零匹配**。

`Config::get('system')` 是 ThinkPHP 的 `config()` 辅助函数，加载 `apps/backend/config/system.php` 文件，不是数据库查询。

**结论**：完全废弃。安全删除候选。

---

## 7. 停止条件检查

### 7.1 代码是否依赖预期之外的业务表？

否。代码依赖的表已在 1-6 节全覆盖。不存在未被覆盖的业务表依赖。

### 7.2 同一字段是否存在语义冲突？

| 字段 | 冲突说明 |
|------|---------|
| `cards.good` vs `cards.goods` | data.sql 使用 `good`，模型使用 `goods`。验证规则使用 `good`（输入层） |
| `good` 表 vs `likes` 表 | 两个表在 data.sql 和代码中用相同语义存储点赞数据，但 schema 不同 |
| `role_permissions.permission_id` vs `permission_hash` | data.sql 是 `permission_id`，代码模型和实际运行库是 `permission_hash` |

### 7.3 是否需要猜测类型/默认值/唯一性？

是。`files` 表缺少模型 `$schema` 声明，DDL 细节只能从实际运行库快照间接获取（见 AWAITS_ARCHITECT-1）。生产中应通过 `$schema` 显式声明或创建迁移文件锁定契约。

### 7.4 其他风险

- **致命不兼容**：当前 `data.sql` 导入后，系统 4 个核心功能（配置、文件、点赞、RBAC）完全不可用。
- **安全风险**：`roles` 表缺少 `is_system` 列，系统角色删除/修改保护失效。
- **数据不一致**：字段名 `good`/`goods` 在 data.sql 和模型间的不一致会在严格模式下触发 PDO 错误。
- **datetime vs timestamp**：多个表的时间列在 data.sql 为 `datetime NOT NULL`，实际运行库为 `timestamp NULL DEFAULT NULL`。这会影响旧数据导入到新 schema 时的兼容性。

---

## 总结

| 发现类型 | 数量 | 严重程度 |
|---------|------|---------|
| 代码必需但 data.sql 缺少的表 | 4 | 致命 |
| data.sql 缺少的字段 | 4 | 高 |
| 字段名/类型漂移 | 12 | 中 |
| 多余旧表 | 4 | 低（但待清理） |
| 需要 Architect 确认项 | 5 | — |

**核心结论**：`data.sql` 已经严重落后于代码预期。新安装将因缺少 4 张必需表和多个关键字段导致系统不可用。必须在 production 分支修复 data.sql 和 Install.php 初始化流程。

---

## Round 2 修复与验证

> 更新日期：2026-07-27
> 状态：**pending verification**
>
> 本节记录 Round 2 修复状态。修复完成前，所有声明为待验证状态，不得视为通过。
> 实际验证必须通过 PHP 绝对路径执行测试脚本并得到 exit 0。

### 当前修复进展

1. **SchemaContract.php 测试修复**
   - `extractAllCapabilitiesFromRbac()` 改为只解析 `getAllCapabilities()` 返回数组（不再扫描全文件 `'key' =>` 模式）。权威数量 73。
   - `fk_role_permissions_permission` 检查改为使用 strip-commented SQL，避免匹配注释中的引用。
   - 状态：待 PHP 验证。

2. **InstallBaseline.php 测试修复**
   - `InstallLock()` 检测改为先提取 `PostInstallLock()` 方法体，再精确匹配 `Common::InstallLock()`。
   - 新增 `Roles::seedSystemCapabilities()` 顺序检查（ConfigService::init() → seedSystemCapabilities() → Common::InstallLock()）。
   - `fk_role_permissions_permission` 检查改为 strip-commented SQL。
   - 状态：待 PHP 验证。

3. **Install.php 接线**
   - `PostInstallLock()` 中在 `ConfigService::init()` 成功后、`Common::InstallLock()` 前调用 `Roles::seedSystemCapabilities()`。
   - 状态：待验证。

4. **Roles.php 单一矩阵**
   - 新增 `getSystemRoleCapabilityMatrix(): array` 方法，返回角色能力映射。
   - `reseed()` 和 `seedSystemCapabilities()` 都调用此方法，消除重复矩阵。
   - 状态：待验证。

5. **Migration Phase 1 结构检查**
   - 新增 `mig_preflight_configs_structure`、`mig_preflight_files_structure`、`mig_preflight_likes_structure`、`mig_preflight_role_cap_structure` 预检存储过程。
   - 全部在 Phase 1 执行，含关键列缺失 SIGNAL 和同名错误索引 SIGNAL。
   - Phase 2 的 `mig_fix_*` 存储过程中的结构 SIGNAL 已替换为 SELECT INFO 消息。
   - `good/goods` 比较改为 NULL-safe `NOT (good <=> goods)`。
   - 状态：待验证。

6. **data.sql 注释更新**
   - `role_capabilities` 种子注释从 `Roles::reseed()` 改为 `Roles::seedSystemCapabilities()`，并注明禁止在 clean-install 使用 reseed。
   - 状态：待验证。

### 验证方法

```bash
cd /c/Users/admin/Desktop/lovecards3/agent/worktrees/feat-db-schema-baseline/apps/backend

# PHP Syntax
"C:\Program Files\phpstudy_pro\Extensions\php\php8.0.2nts\php.exe" -l app/system/controller/Install.php
"C:\Program Files\phpstudy_pro\Extensions\php\php8.0.2nts\php.exe" -l app/api/service/Rbac/Roles.php
"C:\Program Files\phpstudy_pro\Extensions\php\php8.0.2nts\php.exe" -l tests/Database/SchemaContract.php
"C:\Program Files\phpstudy_pro\Extensions\php\php8.0.2nts\php.exe" -l tests/Database/InstallBaseline.php

# SchemaContract — 必须 exit 0，输出 RBAC 73
"C:\Program Files\phpstudy_pro\Extensions\php\php8.0.2nts\php.exe" tests/Database/SchemaContract.php

# InstallBaseline — 必须 exit 0
"C:\Program Files\phpstudy_pro\Extensions\php\php8.0.2nts\php.exe" tests/Database/InstallBaseline.php
```

### Round 3 Dynamic Verification（2026-07-28）

在三组隔离的 MySQL 5.7.26 数据库上执行完整迁移测试：

| 组 | 基线 | 迁移后总表数 | 运行必需表 | 能力计数 | 状态 |
|-----|------|-----------|-----------|---------|------|
| A | feat/db-schema-baseline (clean install) | 15 | 10/10 | Root=73, Admin=56, User=13, Guest=7 | ✅ PASS |
| B | origin/main (legacy, FK removed) | 15 | 10/10 | Root=73, Admin=56, User=13, Guest=7 | ✅ PASS |
| C | 2026-05-21 historical (schema only) | 12 | 10/10 | Root=73, Admin=56, User=13, Guest=7 | ✅ PASS |

> **注：** FILES-AUTH-002 新增 files.update / files.update.all 后，能力计数更新为：
> Root=75, Admin=57, User=13, Guest=7。详见 `20260728000001.sql`。

**备注：** C 的 12 表包含全部 10 张运行必需表，另含 `role_permissions`、`system` 两张遗留兼容表。`good`、`images`、`permissions` 为本批次不创建的兼容/废弃表，不构成失败。

**关键验证：**
- A：自定义角色 id=9001 在两轮 migration 中保留，`dryrun.custom.preserve` 能力不变。第二次 migration 0 重新插入（幂等）。
- B：3 条受控 good 数据正确迁移到 `likes`，`ref_type='card'`、`ref_id=pid`、无 `(pid,uid)` 重复。pass2 `source=3, already_equal=3, inserted=0`。
- C：migration 创建 `role_capabilities` 并精确 seed 四角色能力。pass2 幂等。
- Migration SHA-256：`42D3DCB28E0EDE78BFEC943B27929A421CB9AAAEF982D246ACC387A7FB4DF21D`
- Runner 断言：49/49 全部通过，exit 0。无修改代码、无重试、无 `--force`、无 `FOREIGN_KEY_CHECKS=0`。

### 已知限制

- Migration 含 `DELIMITER`，必须由 MySQL 命令行客户端执行，不支持 `Database::ImportSQLFile()`。
- **Phase 1 使用临时存储过程**，其自身的 `CREATE PROCEDURE` DDL 会隐式提交（MySQL 限制）。
  但 Phase 1 不修改任何业务表结构或数据。如果 Phase 1 失败：
  - 临时 procedure 在下次重跑开头被 `DROP PROCEDURE IF EXISTS` 清理
  - 业务表 DDL/DML 尚未开始（在 Phase 2），因此没有业务表变更需要回滚
- **Phase 2** 开始执行业务表 CREATE TABLE / ALTER TABLE / INSERT 等 DDL/DML 操作。
  Phase 1 已确保所有结构检查通过，Phase 2 不再发现新冲突。
- 本批次不删除 `good`、`images`、`permissions`、`role_permissions`、`system` 等兼容表。
