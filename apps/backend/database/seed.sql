-- LoveCards clean-install defaults only.
-- Do not add schema changes here; use database/migrations and the lifecycle ledger.

-- Redis 是可选系统设施，新安装默认关闭。
INSERT INTO `configs` (`group`, `key`, `value`, `type`, `description`, `created_at`, `updated_at`) VALUES
('redis', '__revision', '1', 'int', '内部配置组版本', NOW(), NOW()),
('redis', 'enabled', '0', 'bool', '启用 Redis 系统连接', NOW(), NOW()),
('redis', 'host', '', 'string', 'Redis 地址', NOW(), NOW()),
('redis', 'port', '6379', 'int', 'Redis 端口', NOW(), NOW()),
('redis', 'database', '0', 'int', 'Redis 数据库编号', NOW(), NOW()),
('redis', 'tls', '0', 'bool', '启用 Redis TLS 并验证证书', NOW(), NOW()),
('redis', 'tls_server_name', '', 'string', 'Redis TLS 证书名称；留空使用主机地址', NOW(), NOW()),
('redis', 'password', '', 'password', 'Redis 认证密码', NOW(), NOW());

-- 通信运行时首次安装默认配置；历史实例由 20261106000001 迁移初始化。
INSERT INTO `runtime_config_state` (`id`, `desired_mode`, `settings_json`, `revision`, `last_apply_state`, `created_at`, `updated_at`) VALUES
(1, 'polling', '{"notification_interval_seconds":30,"message_interval_seconds":45,"unread_interval_seconds":20,"pause_when_hidden":true,"worker_stale_seconds":30,"scheduler_stale_seconds":3600,"gateway_stale_seconds":15}', 1, 'applied', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6));

-- 事件处理独立于浏览器通信；新安装需配置外部 cron，未配置时状态为不健康。
INSERT INTO `eventing_consumer_state` (`id`, `mode`, `state`, `target_mode`, `scheduled_trigger`, `revision`, `created_at`, `updated_at`) VALUES
(1, 'scheduled', 'active', NULL, 'none', 1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6));

-- 主动更新目录由 Handler Registry 定义；这里只保存管理员覆盖值。
INSERT INTO `signal_policy_state` (`id`, `revision`, `overrides_json`, `generations_json`, `created_at`, `updated_at`) VALUES
(1, 1, '{}', '{}', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6));

-- 首次安装直接使用规范事件名；历史实例由 20261107000001 迁移别名。
INSERT INTO `notification_policy_versions` (`policy_revision`, `snapshot_json`, `source`, `reason`, `created_by`, `created_at`) VALUES
(1, '{"content.comment.published":{"enabled":true,"locked":false,"user_disableable":true,"notification_type":"comment.reply"},"content.liked":{"enabled":true,"locked":false,"user_disableable":true,"notification_type":"content.card_liked"},"library.saved":{"enabled":true,"locked":false,"user_disableable":true,"notification_type":"library.saved"},"content.mentions.added":{"enabled":true,"locked":false,"user_disableable":true,"notification_type":"content.mentioned"},"message.sent":{"enabled":true,"locked":false,"user_disableable":true,"notification_type":"message"},"social.followed":{"enabled":true,"locked":false,"user_disableable":true,"notification_type":"social.followed"},"system.notification.issued":{"enabled":true,"locked":true,"user_disableable":false,"notification_type":"system.*"}}', 'seed', '通信运行时初始策略目录', NULL, UTC_TIMESTAMP(6));

INSERT INTO `notification_policies` (`event_type`, `event_version`, `rule_key`, `enabled`, `notification_type`, `user_disableable`, `locked`, `policy_revision`, `updated_by`, `created_at`, `updated_at`) VALUES
('content.comment.published', 1, 'notify', 1, 'comment.reply', 1, 0, 1, NULL, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)),
('content.liked', 1, 'notify', 1, 'content.card_liked', 1, 0, 1, NULL, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)),
('library.saved', 1, 'notify', 1, 'library.saved', 1, 0, 1, NULL, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)),
('content.mentions.added', 1, 'notify', 1, 'content.mentioned', 1, 0, 1, NULL, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)),
('message.sent', 1, 'notify', 1, 'message', 1, 0, 1, NULL, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)),
('social.followed', 1, 'notify', 1, 'social.followed', 1, 0, 1, NULL, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)),
('system.notification.issued', 1, 'notify', 1, 'system.moderation', 0, 1, 1, NULL, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6));

-- System role seed (is_system=1 for all 4 system roles)
INSERT INTO `roles` (`id`, `name`, `slug`, `is_system`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, '超级管理员', 'root', 1, NULL, '2026-01-19 03:07:29', '2026-01-19 03:07:29', NULL),
(2, '管理员', 'admin', 1, NULL, '2026-01-19 03:07:58', '2026-01-19 03:07:58', NULL),
(3, '用户', 'user', 1, NULL, '2026-01-19 03:08:26', '2026-01-19 03:08:26', NULL),
(4, '访客', 'guest', 1, NULL, '2026-01-19 03:08:40', '2026-01-19 03:08:40', NULL);

-- Built-in administration templates use stable slugs but intentionally have no
-- fixed ID anchors. PostInstallLock seeds their minimum grants and never assigns
-- them to an existing user.
INSERT INTO `roles` (`name`, `slug`, `is_system`, `description`, `created_at`, `updated_at`, `deleted_at`) VALUES
('内容运营', 'content_operator', 1, '负责卡片与标签的日常内容治理，不包含审核、用户或系统权限。', NOW(), NOW(), NULL),
('社区运营', 'community_operator', 1, '负责评论、举报和社区聚合信息，不包含内容编辑或系统配置。', NOW(), NOW(), NULL),
('审核员', 'moderator', 1, '处理卡片、评论和举报审核，不获得正文编辑、删除或系统权限。', NOW(), NOW(), NULL),
('安全管理员', 'security_admin', 1, '处理账号状态、会话和恢复事件，并读取安全相关审计记录。', NOW(), NOW(), NULL),
('运维管理员', 'operations_admin', 1, '负责基础设施配置、事件投递、升级迁移和运行恢复。', NOW(), NOW(), NULL),
('审计员', 'auditor', 1, '只读核对获授权的内容、用户、配置摘要、运行状态和审计记录。', NOW(), NOW(), NULL);

-- ============================================================
--  Initial Root User Seed
-- ============================================================
INSERT INTO `users` (`id`, `uid`, `number`, `avatar`, `email`, `phone`, `username`, `password`, `status`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, '000100000000', '1000000000', '', 'admin@lovecards.cn', '', '超级管理员', '!', 0, '2023-12-06 20:09:26', '2025-08-01 20:50:25', NULL);

INSERT INTO `user_roles` (`user_id`, `role_id`, `created_at`) VALUES
(1, 1, '2025-08-01 20:50:25'),
(1, 2, '2025-08-01 20:50:25'),
(1, 3, '2025-08-01 20:50:25');
