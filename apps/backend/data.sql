-- ============================================================
-- LoveCardsV3 — Clean Install SQL Package
-- Version: 1.0.0
-- Date: 2026-07-27
-- Description: Complete schema for a fresh installation.
--   Includes 10 required tables + 5 legacy compatibility tables.
--   MySQL 5.7.26 compatible, InnoDB, utf8mb4.
--   Does NOT contain DROP TABLE, TRUNCATE, or REPLACE INTO.
-- ============================================================

-- ------------------------------------------------------------
--  Required Table: users
--  Stores user accounts and authentication data.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `number` VARCHAR(32) NOT NULL,
  `avatar` VARCHAR(255) NOT NULL DEFAULT '',
  `email` VARCHAR(320) NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `username` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `status` INT(11) NOT NULL,
  `roles_id` JSON DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=COMPACT;

-- ------------------------------------------------------------
--  Required Table: roles
--  Role definitions with system role protection flag.
--  System roles: root(1), admin(2), user(3), guest(4).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL COMMENT '角色名称',
  `slug` VARCHAR(50) NOT NULL COMMENT '角色标识（唯一）',
  `is_system` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '系统角色标记',
  `description` VARCHAR(255) DEFAULT NULL COMMENT '角色描述',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='角色表';

-- System role seed (is_system=1 for all 4 system roles)
INSERT INTO `roles` (`id`, `name`, `slug`, `is_system`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, '超级管理员', 'root', 1, NULL, '2026-01-19 03:07:29', '2026-01-19 03:07:29', NULL),
(2, '管理员', 'admin', 1, NULL, '2026-01-19 03:07:58', '2026-01-19 03:07:58', NULL),
(3, '用户', 'user', 1, NULL, '2026-01-19 03:08:26', '2026-01-19 03:08:26', NULL),
(4, '访客', 'guest', 1, NULL, '2026-01-19 03:08:40', '2026-01-19 03:08:40', NULL);

-- ------------------------------------------------------------
--  Required Table: role_capabilities
--  RBAC capability assignments (role_id, capability) unique.
--  Seed data is initialized by Roles::reseed() after install.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `role_capabilities` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `role_id` INT(11) NOT NULL,
  `capability` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_cap` (`role_id`, `capability`),
  KEY `idx_role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NOTE: role_capabilities seed data is initialized after install by the non-destructive
-- Roles::seedSystemCapabilities() during PostInstallLock(). Never use Roles::reseed()
-- for clean-install as it deletes all capability data.

-- ------------------------------------------------------------
--  Required Table: configs
--  Application configuration store (key-value per group).
--  Seed data is initialized by Config::init() after install.
--  `group` is a reserved word, escaped with backticks.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `configs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `group` VARCHAR(50) NOT NULL COMMENT '分组',
  `key` VARCHAR(100) NOT NULL COMMENT '配置键',
  `value` TEXT COMMENT '配置值',
  `type` VARCHAR(20) DEFAULT 'string' COMMENT '类型',
  `description` VARCHAR(255) DEFAULT NULL COMMENT '配置说明',
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_configs_group_key` (`group`, `key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NOTE: configs seed data is initialized after install by
-- Config::init() which scans config/apps/*.php.
-- No static seed data is provided here.

-- ------------------------------------------------------------
--  Required Table: files
--  File storage metadata (uploaded files, images, attachments).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `files` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `channel_slug` VARCHAR(64) DEFAULT NULL,
  `user_id` INT(11) NOT NULL DEFAULT 0,
  `is_public` TINYINT(1) NOT NULL DEFAULT 0,
  `scene` VARCHAR(64) DEFAULT NULL,
  `ref_type` VARCHAR(64) DEFAULT NULL,
  `ref_id` INT(11) DEFAULT NULL,
  `original_name` VARCHAR(255) DEFAULT NULL,
  `file_path` VARCHAR(512) DEFAULT NULL,
  `file_url` VARCHAR(512) DEFAULT NULL,
  `file_size` INT(11) DEFAULT 0,
  `file_ext` VARCHAR(32) DEFAULT NULL,
  `mime_type` VARCHAR(128) DEFAULT NULL,
  `driver_path` VARCHAR(512) DEFAULT NULL,
  `hash` VARCHAR(64) NOT NULL,
  `metadata` JSON DEFAULT NULL,
  `status` TINYINT(1) NOT NULL DEFAULT 0,
  `upload_status` VARCHAR(32) DEFAULT NULL,
  `expire_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_files_hash` (`hash`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_scene` (`scene`),
  KEY `idx_ref` (`ref_type`, `ref_id`),
  KEY `idx_pending_expire` (`upload_status`, `expire_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  Required Table: likes
--  Like/favorite records (replaces legacy `good` table).
--  Supports polymorphic ref_type/ref_id for cards, comments.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `likes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `aid` INT(11) NOT NULL COMMENT '应用ID (legacy)',
  `pid` INT(11) NOT NULL COMMENT '条目ID (legacy)',
  `ref_type` VARCHAR(32) DEFAULT NULL COMMENT '内容类型: card, comment',
  `ref_id` INT(11) DEFAULT NULL COMMENT '内容ID',
  `uid` INT(11) NOT NULL,
  `ip` VARCHAR(32) NOT NULL COMMENT '发布IP',
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT '发布时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_likes_pid_uid` (`pid`, `uid`),
  KEY `idx_uid` (`uid`),
  KEY `idx_ref` (`ref_type`, `ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  Required Table: cards
--  Card content with JSON pictures and goods count.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cards` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `is_top` INT(11) NOT NULL DEFAULT 0,
  `status` INT(11) NOT NULL DEFAULT 0,
  `user_id` INT(11) NOT NULL DEFAULT 0,
  `data` JSON DEFAULT NULL,
  `cover` VARCHAR(2083) DEFAULT NULL,
  `content` TEXT,
  `tags` JSON DEFAULT NULL,
  `goods` INT(11) NOT NULL DEFAULT 0,
  `pictures` JSON DEFAULT NULL,
  `views` INT(11) NOT NULL DEFAULT 0,
  `comments` INT(11) NOT NULL DEFAULT 0,
  `post_ip` VARCHAR(39) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  Required Table: comments
--  Comments on cards with nested reply support.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comments` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `aid` INT(11) NOT NULL DEFAULT 0,
  `pid` INT(11) NOT NULL DEFAULT 0,
  `parent_id` INT(11) DEFAULT 0,
  `is_top` INT(11) NOT NULL DEFAULT 0,
  `status` INT(11) NOT NULL DEFAULT 0,
  `user_id` INT(11) NOT NULL DEFAULT 0,
  `data` JSON DEFAULT NULL,
  `content` TEXT,
  `goods` INT(11) NOT NULL DEFAULT 0,
  `post_ip` VARCHAR(39) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  Required Table: tags
--  Tag definitions.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tags` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `aid` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL DEFAULT 0,
  `name` VARCHAR(255) DEFAULT '',
  `status` INT(11) NOT NULL DEFAULT 0,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=COMPACT;

-- ------------------------------------------------------------
--  Required Table: tags_map
--  Tag-to-content mapping with status field.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tags_map` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `aid` INT(11) NOT NULL,
  `pid` INT(11) NOT NULL,
  `tag_id` INT(11) NOT NULL,
  `status` INT(11) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=COMPACT;

-- ============================================================
--  Legacy Compatibility Tables
--  Preserved for backward compatibility and rollback support.
--  These tables are NOT used by the current runtime code but
--  are kept to allow safe migration from older installations.
-- ============================================================

-- ------------------------------------------------------------
--  Legacy Table: good
--  Superseded by `likes` table. Preserved for rollback.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `good` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `aid` INT(11) NOT NULL COMMENT '应用ID',
  `pid` INT(11) NOT NULL COMMENT '条目ID',
  `uid` INT(11) NOT NULL,
  `ip` VARCHAR(32) NOT NULL COMMENT '发布IP',
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT '发布时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  Legacy Table: images
--  Superseded by `files` table. Preserved for rollback.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `images` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `aid` INT(11) NOT NULL COMMENT '应用ID',
  `pid` INT(11) NOT NULL COMMENT '条目ID',
  `user_id` INT(11) NOT NULL,
  `url` VARCHAR(256) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  Legacy Table: permissions
--  Route-based permissions (66 entries).
--  Superseded by RBAC capability system. Preserved for audit.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL COMMENT '权限名称',
  `slug` VARCHAR(100) NOT NULL COMMENT '权限标识（唯一）',
  `path` VARCHAR(255) NOT NULL COMMENT '权限路径（如：/api/admin/users）',
  `method` VARCHAR(10) NOT NULL DEFAULT 'GET' COMMENT 'HTTP方法：GET,POST,PUT,PATCH,DELETE,*',
  `description` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `path_method` (`path`, `method`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='权限表';

-- Legacy permissions seed data (66 entries)
INSERT INTO `permissions` (`id`, `name`, `slug`, `path`, `method`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, '系统更新检查', 'system-update', '/api/system/updata', 'GET', '检查系统更新', '2026-01-19 04:01:18', '2026-01-19 04:01:18', NULL),
(2, '获取主题列表', 'system-themes', '/api/system/themes', 'GET', '获取可用主题列表', '2026-01-19 04:01:18', '2026-01-19 04:01:18', NULL),
(3, '获取系统配置', 'system-config-get', '/api/system/config', 'GET', '获取系统配置信息', '2026-01-19 04:01:18', '2026-01-19 04:01:18', NULL),
(4, '设置系统配置', 'system-config-set', '/api/system/config', 'POST', '设置系统配置', '2026-01-19 04:01:18', '2026-01-19 04:01:18', NULL),
(5, '系统站点设置', 'system-site', '/api/system/site', 'POST', '系统站点设置', '2026-01-19 04:01:18', '2026-01-19 04:01:18', NULL),
(6, '系统邮箱设置', 'system-email', '/api/system/email', 'PATCH', '系统邮箱设置', '2026-01-19 04:01:18', '2026-01-19 04:01:18', NULL),
(7, '设置主题配置', 'system-theme-config', '/api/system/theme-config', 'POST', '设置主题配置', '2026-01-19 04:01:18', '2026-01-19 04:01:18', NULL),
(8, '设置主题', 'system-set-theme', '/api/system/set-theme', 'POST', '设置当前主题', '2026-01-19 04:01:18', '2026-01-19 04:01:18', NULL),
(9, '获取用户列表', 'admin-users-list', '/api/admin/users', 'GET', '获取用户列表', '2026-01-19 04:01:18', '2026-01-19 04:01:18', NULL),
(10, '更新用户', 'admin-user-update', '/api/admin/user', 'PATCH', '更新用户信息', '2026-01-19 04:01:18', '2026-01-19 04:01:18', NULL),
(11, '删除用户', 'admin-user-delete', '/api/admin/user', 'DELETE', '删除用户', '2026-01-19 04:01:18', '2026-01-19 04:01:18', NULL),
(12, '批量操作用户', 'admin-users-batch', '/api/admin/users/batch-operate', 'POST', '批量操作用户', '2026-01-19 04:01:18', '2026-01-19 04:01:18', NULL),
(13, '获取单个卡片', 'admin-card-get', '/api/admin/card', 'GET', '获取单个卡片详情', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(14, '获取卡片列表', 'admin-cards-list', '/api/admin/cards', 'GET', '获取卡片列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(15, '更新卡片', 'admin-card-update', '/api/admin/card', 'PATCH', '更新卡片信息', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(16, '删除卡片', 'admin-cards-delete', '/api/admin/cards', 'DELETE', '删除卡片', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(17, '批量操作卡片', 'admin-cards-batch', '/api/admin/cards/batch-operate', 'POST', '批量操作卡片', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(18, '获取评论列表', 'admin-comments-list', '/api/admin/comments', 'GET', '获取评论列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(19, '更新评论', 'admin-comment-update', '/api/admin/comment', 'PATCH', '更新评论信息', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(20, '删除评论', 'admin-comment-delete', '/api/admin/comment', 'DELETE', '删除评论', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(21, '批量操作评论', 'admin-comments-batch', '/api/admin/comments/batch-operate', 'POST', '批量操作评论', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(22, '获取标签列表', 'admin-tags-list', '/api/admin/tags', 'GET', '获取标签列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(23, '创建标签', 'admin-tag-create', '/api/admin/tag', 'POST', '创建标签', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(24, '更新标签', 'admin-tag-update', '/api/admin/tag', 'PATCH', '更新标签信息', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(25, '删除标签', 'admin-tag-delete', '/api/admin/tag', 'DELETE', '删除标签', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(26, '批量操作标签', 'admin-tags-batch', '/api/admin/tags/batch-operate', 'POST', '批量操作标签', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(27, '控制台', 'admin-dashboard', '/api/admin/dashboard', 'GET', '访问控制台', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(28, '获取角色列表', 'admin-roles-list', '/api/admin/roles', 'GET', '获取角色列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(29, '获取单个角色', 'admin-role-get', '/api/admin/role', 'GET', '获取单个角色详情', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(30, '创建角色', 'admin-role-create', '/api/admin/role', 'POST', '创建角色', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(31, '更新角色', 'admin-role-update', '/api/admin/role', 'PATCH', '更新角色信息', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(32, '删除角色', 'admin-role-delete', '/api/admin/role', 'DELETE', '删除角色', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(33, '分配权限', 'admin-role-assign', '/api/admin/role/assign-permissions', 'POST', '为角色分配权限', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(34, '获取角色权限', 'admin-role-permissions', '/api/admin/role/permissions', 'GET', '获取角色的权限列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(35, '获取权限列表', 'admin-permissions-list', '/api/admin/permissions', 'GET', '获取权限列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(36, '获取单个权限', 'admin-permission-get', '/api/admin/permission', 'GET', '获取单个权限详情', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(37, '创建权限', 'admin-permission-create', '/api/admin/permission', 'POST', '创建权限', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(38, '更新权限', 'admin-permission-update', '/api/admin/permission', 'PATCH', '更新权限信息', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(39, '删除权限', 'admin-permission-delete', '/api/admin/permission', 'DELETE', '删除权限', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(40, '获取所有权限', 'admin-permissions-all', '/api/admin/permissions/all', 'GET', '获取所有权限（不分页）', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(41, '添加角色权限', 'admin-role-permission-add', '/api/admin/role-permission', 'POST', '为角色添加权限', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(42, '移除角色权限', 'admin-role-permission-remove', '/api/admin/role-permission', 'DELETE', '移除角色权限', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(43, '批量添加角色权限', 'admin-role-permissions-batch-add', '/api/admin/role-permissions/batch-add', 'POST', '批量添加角色权限', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(44, '批量移除角色权限', 'admin-role-permissions-batch-remove', '/api/admin/role-permissions/batch-remove', 'POST', '批量移除角色权限', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(45, '获取标签列表', 'user-tags-list', '/api/tags', 'GET', '获取标签列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(46, '获取卡片图集', 'user-card-images', '/api/card/images', 'GET', '获取卡片图集', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(47, '获取卡片列表', 'user-cards-list', '/api/cards', 'GET', '获取卡片列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(48, '喜欢卡片', 'user-card-like', '/api/card/like', 'POST', '喜欢卡片', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(49, '创建评论', 'user-card-comment', '/api/card/comment', 'POST', '创建评论', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(50, '创建卡片', 'user-card-create', '/api/card', 'POST', '创建卡片', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(51, '删除卡片', 'user-card-delete', '/api/card', 'DELETE', '删除卡片', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(52, '删除评论', 'user-comment-delete', '/api/comment', 'DELETE', '删除评论', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(53, '取消喜欢', 'user-like-delete', '/api/like', 'DELETE', '取消喜欢', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(54, '获取评论列表', 'user-comments-list', '/api/comments', 'GET', '获取评论列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(55, '获取喜欢列表', 'user-likes-list', '/api/likes', 'GET', '获取喜欢列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(56, '更新用户信息', 'user-info-update', '/api/user/info', 'PATCH', '更新用户信息', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(57, '获取用户信息', 'user-info-get', '/api/user/info', 'GET', '获取用户信息', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(58, '修改密码', 'user-password', '/api/user/password', 'POST', '修改密码', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(59, '绑定邮箱', 'user-email', '/api/user/email', 'POST', '绑定邮箱', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(60, '获取邮箱验证码', 'user-email-captcha', '/api/user/email-captcha', 'POST', '获取邮箱验证码', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(61, '上传用户图片', 'upload-user-images', '/api/upload/user-images', 'POST', '上传用户图片', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(62, '访客-获取卡片列表', 'guest-cards-list', '/api/cards', 'GET', '访客获取卡片列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(63, '访客-获取评论列表', 'guest-comments-list', '/api/comments', 'GET', '访客获取评论列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(64, '访客-获取喜欢列表', 'guest-likes-list', '/api/likes', 'GET', '访客获取喜欢列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(65, '访客-获取用户信息', 'guest-user-info', '/api/user/info', 'GET', '访客获取用户信息', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL),
(66, '访客-获取标签列表', 'guest-tags-list', '/api/tags', 'GET', '访客获取标签列表', '2026-01-19 04:01:19', '2026-01-19 04:01:19', NULL);

-- ------------------------------------------------------------
--  Legacy Table: role_permissions
--  Role-to-permission association (legacy system).
--  Preserved for compatibility. Current runtime uses
--  role_capabilities table instead.
--
--  NOTE: fk_role_permissions_permission FK was removed in
--  DB-SCHEMA-BASELINE-001 because clean baseline has no
--  compatible target (permissions 67-80 referenced by seed
--  data do not exist). The FK remains in existing upgrades
--  for backward compatibility.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `role_id` INT(11) NOT NULL COMMENT '角色ID',
  `permission_id` INT(11) NOT NULL COMMENT '权限ID',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_permission` (`role_id`, `permission_id`),
  KEY `role_id` (`role_id`),
  KEY `permission_id` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='角色权限关联表';

-- Legacy role_permissions seed data
INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES
(1, 1, 1, '2026-01-18 20:01:19'),
(2, 1, 2, '2026-01-18 20:01:19'),
(3, 1, 3, '2026-01-18 20:01:19'),
(4, 1, 4, '2026-01-18 20:01:19'),
(5, 1, 5, '2026-01-18 20:01:19'),
(6, 1, 6, '2026-01-18 20:01:19'),
(7, 1, 7, '2026-01-18 20:01:19'),
(8, 1, 8, '2026-01-18 20:01:19'),
(9, 1, 9, '2026-01-18 20:01:19'),
(10, 1, 10, '2026-01-18 20:01:19'),
(11, 1, 11, '2026-01-18 20:01:19'),
(12, 1, 12, '2026-01-18 20:01:19'),
(13, 1, 13, '2026-01-18 20:01:19'),
(14, 1, 14, '2026-01-18 20:01:19'),
(15, 1, 15, '2026-01-18 20:01:19'),
(16, 1, 16, '2026-01-18 20:01:19'),
(17, 1, 17, '2026-01-18 20:01:19'),
(18, 1, 18, '2026-01-18 20:01:19'),
(19, 1, 19, '2026-01-18 20:01:19'),
(20, 1, 20, '2026-01-18 20:01:19'),
(21, 1, 21, '2026-01-18 20:01:19'),
(22, 1, 22, '2026-01-18 20:01:19'),
(23, 1, 23, '2026-01-18 20:01:19'),
(24, 1, 24, '2026-01-18 20:01:19'),
(25, 1, 25, '2026-01-18 20:01:19'),
(26, 1, 26, '2026-01-18 20:01:19'),
(27, 1, 27, '2026-01-18 20:01:19'),
(28, 1, 28, '2026-01-18 20:01:19'),
(29, 1, 29, '2026-01-18 20:01:19'),
(30, 1, 30, '2026-01-18 20:01:19'),
(31, 1, 31, '2026-01-18 20:01:19'),
(32, 1, 32, '2026-01-18 20:01:19'),
(33, 1, 33, '2026-01-18 20:01:19'),
(34, 1, 34, '2026-01-18 20:01:19'),
(35, 1, 35, '2026-01-18 20:01:19'),
(36, 1, 36, '2026-01-18 20:01:19'),
(37, 1, 37, '2026-01-18 20:01:19'),
(38, 1, 38, '2026-01-18 20:01:19'),
(39, 1, 39, '2026-01-18 20:01:19'),
(40, 1, 40, '2026-01-18 20:01:19'),
(41, 1, 41, '2026-01-18 20:01:19'),
(42, 1, 42, '2026-01-18 20:01:19'),
(43, 1, 43, '2026-01-18 20:01:19'),
(44, 1, 44, '2026-01-18 20:01:19'),
(45, 1, 45, '2026-01-18 20:01:19'),
(46, 1, 46, '2026-01-18 20:01:19'),
(47, 1, 47, '2026-01-18 20:01:19'),
(48, 1, 48, '2026-01-18 20:01:19'),
(49, 1, 49, '2026-01-18 20:01:19'),
(50, 1, 50, '2026-01-18 20:01:19'),
(51, 1, 51, '2026-01-18 20:01:19'),
(52, 1, 52, '2026-01-18 20:01:19'),
(53, 1, 53, '2026-01-18 20:01:19'),
(54, 1, 54, '2026-01-18 20:01:19'),
(55, 1, 55, '2026-01-18 20:01:19'),
(56, 1, 56, '2026-01-18 20:01:19'),
(57, 1, 57, '2026-01-18 20:01:19'),
(58, 1, 58, '2026-01-18 20:01:19'),
(59, 1, 59, '2026-01-18 20:01:19'),
(60, 1, 60, '2026-01-18 20:01:19'),
(61, 1, 61, '2026-01-18 20:01:19'),
(62, 1, 62, '2026-01-18 20:01:19'),
(63, 1, 63, '2026-01-18 20:01:19'),
(64, 1, 64, '2026-01-18 20:01:19'),
(65, 1, 65, '2026-01-18 20:01:19'),
(66, 1, 66, '2026-01-18 20:01:19'),
(67, 1, 67, '2026-01-18 20:01:19'),
(68, 1, 68, '2026-01-18 20:01:19'),
(69, 1, 69, '2026-01-18 20:01:19'),
(70, 1, 70, '2026-01-18 20:01:19'),
(71, 1, 71, '2026-01-18 20:01:19'),
(72, 1, 72, '2026-01-18 20:01:19'),
(73, 1, 73, '2026-01-18 20:01:19'),
(74, 1, 74, '2026-01-18 20:01:19'),
(75, 1, 75, '2026-01-18 20:01:19'),
(76, 1, 76, '2026-01-18 20:01:19'),
(77, 1, 77, '2026-01-18 20:01:19'),
(78, 1, 78, '2026-01-18 20:01:19'),
(79, 1, 79, '2026-01-18 20:01:19'),
(80, 1, 80, '2026-01-18 20:01:19'),
(128, 2, 13, '2026-01-18 20:01:19'),
(129, 2, 15, '2026-01-18 20:01:19'),
(130, 2, 17, '2026-01-18 20:01:19'),
(131, 2, 16, '2026-01-18 20:01:19'),
(132, 2, 14, '2026-01-18 20:01:19'),
(133, 2, 20, '2026-01-18 20:01:19'),
(134, 2, 19, '2026-01-18 20:01:19'),
(135, 2, 21, '2026-01-18 20:01:19'),
(136, 2, 18, '2026-01-18 20:01:19'),
(137, 2, 27, '2026-01-18 20:01:19'),
(138, 2, 23, '2026-01-18 20:01:19'),
(139, 2, 25, '2026-01-18 20:01:19'),
(140, 2, 24, '2026-01-18 20:01:19'),
(141, 2, 26, '2026-01-18 20:01:19'),
(142, 2, 22, '2026-01-18 20:01:19'),
(143, 3, 61, '2026-01-18 20:01:19'),
(144, 3, 49, '2026-01-18 20:01:19'),
(145, 3, 50, '2026-01-18 20:01:19'),
(146, 3, 51, '2026-01-18 20:01:19'),
(147, 3, 46, '2026-01-18 20:01:19'),
(148, 3, 48, '2026-01-18 20:01:19'),
(149, 3, 47, '2026-01-18 20:01:19'),
(150, 3, 52, '2026-01-18 20:01:19'),
(151, 3, 54, '2026-01-18 20:01:19'),
(152, 3, 59, '2026-01-18 20:01:19'),
(153, 3, 60, '2026-01-18 20:01:19'),
(154, 3, 57, '2026-01-18 20:01:19'),
(155, 3, 56, '2026-01-18 20:01:19'),
(156, 3, 53, '2026-01-18 20:01:19'),
(157, 3, 55, '2026-01-18 20:01:19'),
(158, 3, 58, '2026-01-18 20:01:19'),
(159, 3, 45, '2026-01-18 20:01:19'),
(174, 4, 62, '2026-01-18 20:01:19'),
(175, 4, 63, '2026-01-18 20:01:19'),
(176, 4, 64, '2026-01-18 20:01:19'),
(177, 4, 66, '2026-01-18 20:01:19'),
(178, 4, 65, '2026-01-18 20:01:19');

-- ------------------------------------------------------------
--  Legacy Table: system
--  Superseded by `configs` table. Preserved for rollback.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) DEFAULT '',
  `value` VARCHAR(2555) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Legacy system seed data (15 entries)
INSERT INTO `system` (`id`, `name`, `value`) VALUES
(1, 'siteUrl', 'lovecards.cn'),
(2, 'siteName', 'LoveCardsV2.4'),
(3, 'siteICPId', ''),
(4, 'siteKeywords', ''),
(5, 'siteDes', ''),
(10, 'siteFooter', ''),
(11, 'LCEAPI', ''),
(12, 'siteCopyright', ''),
(13, 'siteTitle', 'LoveCards'),
(14, 'smtpSecure', ''),
(15, 'smtpName', '');

-- ============================================================
--  Initial Root User Seed
-- ============================================================
INSERT INTO `users` (`id`, `number`, `avatar`, `email`, `phone`, `username`, `password`, `status`, `roles_id`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, '1000000000', '', 'admin@lovecards.cn', '', '超级管理员', '$2y$10$uBowOFgOBNTx1NT1uYJTleEo1r8d91R9iwxRCqncPJUShfsJoMvr6', 0, '[1, 2, 3]', '2023-12-06 20:09:26', '2025-08-01 20:50:25', NULL);

-- ============================================================
--  Foreign Key Constraints (legacy role_permissions)
--  NOTE: fk_role_permissions_permission removed in
--  DB-SCHEMA-BASELINE-001 — see role_permissions table comment.
-- ============================================================
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;
