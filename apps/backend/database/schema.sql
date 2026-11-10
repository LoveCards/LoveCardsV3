-- LoveCards clean-install target schema.
-- Generated from the migrated disposable schema and kept structure-only.
-- Default rows belong in database/seed.sql; historical changes remain in
-- database/migrations and are recorded by Operations/SystemLifecycle.

CREATE TABLE `audit_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `actor_id` int(11) DEFAULT NULL,
  `action` varchar(128) NOT NULL,
  `resource_type` varchar(64) DEFAULT NULL,
  `resource_id` varchar(128) DEFAULT NULL,
  `request_id` varchar(128) DEFAULT NULL,
  `correlation_id` varchar(128) DEFAULT NULL,
  `ip_hash` char(64) DEFAULT NULL,
  `payload` JSON DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_actor_time` (`actor_id`,`created_at`),
  KEY `idx_audit_resource` (`resource_type`,`resource_id`,`created_at`),
  KEY `idx_audit_action_time` (`action`,`created_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `capabilities` (
  `capability` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `risk_level` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`capability`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `card_comment_pins` (
  `card_id` int(11) NOT NULL,
  `comment_id` int(11) NOT NULL,
  `pinned_by` int(11) NOT NULL DEFAULT 0,
  `pinned_at` datetime NOT NULL,
  PRIMARY KEY (`card_id`),
  UNIQUE KEY `uq_card_comment_pins_comment` (`comment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `cards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `is_top` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `visibility` varchar(16) NOT NULL DEFAULT 'public',
  `user_id` int(11) NOT NULL DEFAULT 0,
  `data` JSON DEFAULT NULL,
  `cover` varchar(2083) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `tags` JSON DEFAULT NULL,
  `goods` int(11) NOT NULL DEFAULT 0,
  `pictures` JSON DEFAULT NULL,
  `views` int(11) NOT NULL DEFAULT 0,
  `comments` int(11) NOT NULL DEFAULT 0,
  `pinned_comment_id` int(11) DEFAULT NULL COMMENT '当前卡片置顶的一级评论',
  `post_ip` varchar(39) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cards_public_user` (`user_id`,`status`,`deleted_at`,`created_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL DEFAULT 0,
  `pid` int(11) NOT NULL DEFAULT 0,
  `parent_id` int(11) DEFAULT 0,
  `is_top` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `data` JSON DEFAULT NULL,
  `content` text DEFAULT NULL,
  `goods` int(11) NOT NULL DEFAULT 0,
  `post_ip` varchar(39) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_comments_card_thread` (`pid`,`parent_id`,`status`,`deleted_at`,`created_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `configs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group` varchar(50) NOT NULL COMMENT '分组',
  `key` varchar(100) NOT NULL COMMENT '配置键',
  `value` text DEFAULT NULL COMMENT '配置值',
  `type` varchar(20) DEFAULT 'string' COMMENT '类型',
  `description` varchar(255) DEFAULT NULL COMMENT '配置说明',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_configs_group_key` (`group`,`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `content_mentions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `resource_type` varchar(16) NOT NULL,
  `resource_id` bigint(20) unsigned NOT NULL,
  `card_id` bigint(20) unsigned NOT NULL,
  `comment_id` bigint(20) unsigned DEFAULT NULL,
  `actor_id` int(11) NOT NULL,
  `mentioned_user_id` int(11) NOT NULL,
  `mentioned_username` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_content_mentions_resource_user` (`resource_type`,`resource_id`,`mentioned_user_id`),
  KEY `idx_content_mentions_user_id` (`mentioned_user_id`,`id`),
  KEY `idx_content_mentions_card_id` (`card_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `conversations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `direct_key` char(64) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_conversations_direct` (`direct_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `channel_slug` varchar(64) DEFAULT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `scene` varchar(64) DEFAULT NULL,
  `ref_type` varchar(64) DEFAULT NULL,
  `ref_id` int(11) DEFAULT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(512) DEFAULT NULL,
  `file_url` varchar(512) DEFAULT NULL,
  `file_size` int(11) DEFAULT 0,
  `file_ext` varchar(32) DEFAULT NULL,
  `mime_type` varchar(128) DEFAULT NULL,
  `driver_path` varchar(512) DEFAULT NULL,
  `hash` varchar(64) NOT NULL,
  `metadata` JSON DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `upload_status` varchar(32) DEFAULT NULL,
  `expire_at` datetime DEFAULT NULL,
  `purge_status` varchar(24) DEFAULT NULL,
  `purge_attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `purge_requested_at` datetime DEFAULT NULL,
  `purge_storage_deleted_at` datetime DEFAULT NULL,
  `purge_error` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_files_hash` (`hash`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_scene` (`scene`),
  KEY `idx_ref` (`ref_type`,`ref_id`),
  KEY `idx_pending_expire` (`upload_status`,`expire_at`),
  KEY `idx_files_purge` (`purge_status`,`updated_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `good` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL COMMENT '应用ID',
  `pid` int(11) NOT NULL COMMENT '条目ID',
  `uid` int(11) NOT NULL,
  `ip` varchar(32) NOT NULL COMMENT '发布IP',
  `created_at` timestamp NULL DEFAULT NULL COMMENT '发布时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL COMMENT '应用ID',
  `pid` int(11) NOT NULL COMMENT '条目ID',
  `user_id` int(11) NOT NULL,
  `url` varchar(256) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL COMMENT '应用ID (legacy)',
  `pid` int(11) NOT NULL COMMENT '条目ID (legacy)',
  `ref_type` varchar(32) NOT NULL COMMENT '内容类型: card, comment',
  `ref_id` int(11) NOT NULL COMMENT '内容ID',
  `uid` int(11) NOT NULL,
  `ip` varchar(32) NOT NULL COMMENT '发布IP',
  `created_at` timestamp NULL DEFAULT NULL COMMENT '发布时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_likes_ref_uid` (`ref_type`,`ref_id`,`uid`),
  KEY `idx_uid` (`uid`),
  KEY `idx_ref` (`ref_type`,`ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `outbox_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_type` varchar(128) NOT NULL,
  `event_version` smallint(5) unsigned NOT NULL DEFAULT 1,
  `aggregate_type` varchar(64) NOT NULL,
  `aggregate_key` varchar(128) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `correlation_id` varchar(128) DEFAULT NULL,
  `causation_id` bigint(20) unsigned DEFAULT NULL,
  `payload` JSON NOT NULL,
  `occurred_at` datetime(6) NOT NULL,
  `created_release_id` varchar(128) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_outbox_created` (`created_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '权限名称',
  `slug` varchar(100) NOT NULL COMMENT '权限标识（唯一）',
  `path` varchar(255) NOT NULL COMMENT '权限路径（如：/api/admin/users）',
  `method` varchar(10) NOT NULL DEFAULT 'GET' COMMENT 'HTTP方法：GET,POST,PUT,PATCH,DELETE,*',
  `description` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `path_method` (`path`,`method`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='权限表';
CREATE TABLE `role_capabilities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `capability` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_cap` (`role_id`,`capability`),
  KEY `idx_role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '角色名称',
  `slug` varchar(50) NOT NULL COMMENT '角色标识（唯一）',
  `is_system` tinyint(1) NOT NULL DEFAULT 0 COMMENT '系统角色标记',
  `description` varchar(255) DEFAULT NULL COMMENT '角色描述',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='角色表';
CREATE TABLE `runtime_workers` (
  `worker_id` varchar(128) NOT NULL,
  `worker_type` varchar(64) NOT NULL,
  `state` varchar(16) NOT NULL,
  `release_id` varchar(128) NOT NULL,
  `schema_version` varchar(64) NOT NULL,
  `handler_catalog_hash` char(64) NOT NULL,
  `queue_names_json` varchar(2048) NOT NULL DEFAULT '[]',
  `jobs_processed` bigint(20) unsigned NOT NULL DEFAULT 0,
  `heartbeat_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`worker_id`),
  KEY `idx_runtime_workers_state` (`state`,`updated_at`),
  KEY `idx_runtime_workers_type` (`worker_type`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `runtime_config_state` (
  `id` tinyint(3) unsigned NOT NULL,
  `desired_mode` varchar(16) NOT NULL DEFAULT 'polling',
  `settings_json` text NOT NULL,
  `revision` int(10) unsigned NOT NULL DEFAULT 1,
  `last_apply_state` varchar(16) NOT NULL DEFAULT 'applied',
  `last_apply_reason` varchar(64) DEFAULT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `signal_policy_state` (
  `id` tinyint(3) unsigned NOT NULL,
  `revision` int(10) unsigned NOT NULL DEFAULT 1,
  `overrides_json` longtext NOT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `scheduler_runs` (
  `run_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `worker_id` varchar(128) NOT NULL,
  `started_at` datetime(6) NOT NULL,
  `finished_at` datetime(6) DEFAULT NULL,
  `outcome` varchar(16) NOT NULL DEFAULT 'running',
  `jobs_processed` int(10) unsigned NOT NULL DEFAULT 0,
  `release_id` varchar(128) NOT NULL DEFAULT '',
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL,
  PRIMARY KEY (`run_id`),
  KEY `idx_scheduler_runs_outcome` (`outcome`,`finished_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `schema_migrations` (
  `migration_id` varchar(128) NOT NULL,
  `checksum` char(64) NOT NULL,
  `applied_at` datetime(6) NOT NULL,
  PRIMARY KEY (`migration_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `security_challenges` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `purpose` varchar(32) NOT NULL,
  `subject_hash` char(64) NOT NULL,
  `secret_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `consumed_at` datetime DEFAULT NULL,
  `attempts` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_challenge_lookup` (`purpose`,`subject_hash`,`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `system` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT '',
  `value` varchar(2555) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `stream_tickets` (
  `ticket_hash` char(64) NOT NULL,
  `uid` bigint(20) unsigned NOT NULL,
  `expires_at` datetime(6) NOT NULL,
  `used_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  PRIMARY KEY (`ticket_hash`),
  KEY `idx_stream_tickets_uid_created` (`uid`,`created_at`),
  KEY `idx_stream_tickets_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `system_backups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `backup_id` varchar(128) NOT NULL,
  `plan_id` varchar(128) DEFAULT NULL,
  `backup_type` varchar(24) NOT NULL,
  `driver` varchar(32) NOT NULL,
  `location` varchar(1024) NOT NULL,
  `checksum` char(64) DEFAULT NULL,
  `size_bytes` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `metadata_json` JSON DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `verified_at` datetime(6) DEFAULT NULL,
  `restored_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_system_backups_key` (`backup_id`),
  KEY `idx_system_backups_plan` (`plan_id`,`backup_type`,`created_at`,`id`),
  KEY `idx_system_backups_status` (`status`,`created_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `system_leases` (
  `lease_name` varchar(128) NOT NULL,
  `holder_token` char(64) NOT NULL,
  `lease_expires_at` datetime(6) NOT NULL,
  `revision` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL,
  PRIMARY KEY (`lease_name`),
  KEY `idx_system_leases_expiry` (`lease_expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `system_releases` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `release_id` varchar(128) NOT NULL,
  `sequence` bigint(20) unsigned NOT NULL,
  `version` varchar(64) NOT NULL,
  `manifest_checksum` char(64) NOT NULL,
  `manifest_json` JSON NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'staged',
  `created_by` int(11) DEFAULT NULL,
  `verified_at` datetime(6) NOT NULL,
  `activated_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_system_releases_release` (`release_id`),
  UNIQUE KEY `uk_system_releases_sequence` (`sequence`),
  KEY `idx_system_releases_status` (`status`,`updated_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `system_upgrade_plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `plan_id` varchar(128) NOT NULL,
  `release_id` varchar(128) NOT NULL,
  `previous_release_id` varchar(128) DEFAULT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `current_step` varchar(32) DEFAULT NULL,
  `contract_approved` tinyint(1) NOT NULL DEFAULT 0,
  `context_json` JSON DEFAULT NULL,
  `revision` int(10) unsigned NOT NULL DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL,
  `completed_at` datetime(6) DEFAULT NULL,
  `failed_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_system_upgrade_plans_key` (`plan_id`),
  KEY `idx_system_upgrade_plans_status` (`status`,`updated_at`,`id`),
  KEY `idx_system_upgrade_plans_release` (`release_id`,`created_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `system_upgrade_steps` (
  `migration_id` varchar(128) NOT NULL,
  `checksum` char(64) NOT NULL,
  `phase` varchar(16) NOT NULL,
  `status` varchar(16) NOT NULL,
  `cursor` varchar(2048) DEFAULT NULL,
  `revision` int(10) unsigned NOT NULL DEFAULT 0,
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `failure_code` varchar(128) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`migration_id`),
  KEY `idx_upgrade_status` (`status`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL,
  `user_id` int(11) NOT NULL DEFAULT 0,
  `name` varchar(255) DEFAULT '',
  `status` int(11) NOT NULL DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=COMPACT;
CREATE TABLE `tags_map` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `aid` int(11) NOT NULL,
  `pid` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tags_map_card_tag` (`pid`,`tag_id`),
  KEY `idx_tags_map_tag_card` (`tag_id`,`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=COMPACT;
CREATE TABLE `theme_bindings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `hostname` varchar(253) NOT NULL,
  `theme_id` varchar(64) NOT NULL,
  `release_key` varchar(128) NOT NULL,
  `config_overrides` JSON DEFAULT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_theme_bindings_hostname` (`hostname`),
  KEY `idx_theme_bindings_theme` (`theme_id`,`release_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` char(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `number` varchar(32) NOT NULL,
  `avatar` varchar(255) NOT NULL DEFAULT '',
  `email` varchar(320) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `username` varchar(255) NOT NULL,
  `bio` varchar(160) DEFAULT NULL,
  `location` varchar(64) DEFAULT NULL,
  `gender` varchar(24) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `status` int(11) NOT NULL,
  `roles_id` JSON DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `security_version` int(10) unsigned NOT NULL DEFAULT 1,
  `credentials_changed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_username` (`username`(191)),
  UNIQUE KEY `uk_users_uid` (`uid`),
  KEY `idx_users_public_handle` (`username`(191),`status`,`deleted_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=COMPACT;
CREATE TABLE `auth_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` char(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `jti_hash` char(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_expires_at` datetime NOT NULL,
  `renewable_until` datetime NOT NULL,
  `security_version` int(10) unsigned NOT NULL DEFAULT 1,
  `revoked_at` datetime DEFAULT NULL,
  `revoked_reason` varchar(64) DEFAULT NULL,
  `rotated_to_session_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_auth_sessions_public_id` (`public_id`),
  UNIQUE KEY `uk_auth_sessions_jti` (`jti_hash`),
  KEY `idx_auth_sessions_user` (`user_id`),
  KEY `idx_auth_sessions_expiry` (`token_expires_at`),
  KEY `idx_auth_sessions_user_active` (`user_id`,`revoked_at`,`token_expires_at`,`id`),
  CONSTRAINT `fk_auth_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `card_drafts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT '',
  `content` text NOT NULL,
  `payload` JSON DEFAULT NULL,
  `version` int(10) unsigned NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_drafts_user_updated` (`user_id`,`updated_at`,`id`),
  CONSTRAINT `fk_drafts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `conversation_members` (
  `conversation_id` bigint(20) unsigned NOT NULL,
  `user_id` int(11) NOT NULL,
  `last_read_message_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`conversation_id`,`user_id`),
  KEY `idx_members_user` (`user_id`,`conversation_id`),
  KEY `idx_members_unread` (`user_id`,`last_read_message_id`,`conversation_id`),
  CONSTRAINT `fk_members_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `event_deliveries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `handler_key` varchar(128) NOT NULL,
  `queue_name` varchar(64) NOT NULL DEFAULT 'default',
  `execution_mode` varchar(16) NOT NULL DEFAULT 'request_safe',
  `policy_revision` int(10) unsigned DEFAULT NULL,
  `policy_decision` varchar(24) DEFAULT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `attempt_count` int(10) unsigned NOT NULL DEFAULT 0,
  `available_at` datetime(6) NOT NULL,
  `claim_token` char(64) DEFAULT NULL,
  `claimed_by` varchar(128) DEFAULT NULL,
  `lease_expires_at` datetime(6) DEFAULT NULL,
  `last_error` varchar(2048) DEFAULT NULL,
  `completed_at` datetime(6) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_delivery_handler` (`event_id`,`handler_key`),
  KEY `idx_delivery_queue` (`queue_name`,`execution_mode`,`status`,`available_at`,`id`),
  KEY `idx_delivery_status_updated` (`status`,`updated_at`,`id`),
  KEY `idx_delivery_claim` (`claim_token`,`status`,`id`),
  CONSTRAINT `fk_delivery_event` FOREIGN KEY (`event_id`) REFERENCES `outbox_events` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `file_references` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `file_id` int(11) NOT NULL,
  `resource_type` varchar(32) NOT NULL,
  `resource_id` varchar(128) NOT NULL,
  `purpose` varchar(32) NOT NULL DEFAULT 'attachment',
  `is_public` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_file_reference` (`file_id`,`resource_type`,`resource_id`,`purpose`),
  KEY `idx_file_reference_resource` (`resource_type`,`resource_id`,`file_id`),
  CONSTRAINT `fk_file_references_file` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `library_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `resource_type` varchar(32) NOT NULL,
  `resource_id` varchar(128) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `uk_library_resource` (`user_id`,`resource_type`,`resource_id`),
  KEY `idx_library_user_created` (`user_id`,`created_at`,`resource_id`),
  CONSTRAINT `fk_library_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `sender_id` int(11) NOT NULL,
  `client_message_id` varchar(128) NOT NULL,
  `body` text NOT NULL,
  `created_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_message_client` (`sender_id`,`client_message_id`),
  KEY `idx_messages_conversation` (`conversation_id`,`id`),
  CONSTRAINT `fk_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `notification_preferences` (
  `user_id` int(11) NOT NULL,
  `notification_type` varchar(64) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`user_id`,`notification_type`),
  CONSTRAINT `fk_notification_preferences_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `notification_policies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_type` varchar(128) NOT NULL,
  `event_version` smallint(5) unsigned NOT NULL,
  `rule_key` varchar(128) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `notification_type` varchar(64) DEFAULT NULL,
  `recipient_rule` varchar(64) NOT NULL DEFAULT 'handler',
  `user_disableable` tinyint(1) NOT NULL DEFAULT 1,
  `locked` tinyint(1) NOT NULL DEFAULT 0,
  `policy_revision` int(10) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  `updated_at` datetime(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_np_rule` (`event_type`,`event_version`,`rule_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `notification_policy_versions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `policy_revision` int(10) unsigned NOT NULL,
  `snapshot_json` longtext NOT NULL,
  `source` varchar(16) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` datetime(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_np_rev` (`policy_revision`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `recipient_id` int(11) NOT NULL,
  `type` varchar(64) NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `resource_type` varchar(32) DEFAULT NULL,
  `resource_id` varchar(128) DEFAULT NULL,
  `payload` JSON DEFAULT NULL,
  `dedupe_key` varchar(191) DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_notification_dedupe` (`recipient_id`,`dedupe_key`),
  KEY `idx_notifications_recipient` (`recipient_id`,`created_at`,`id`),
  KEY `idx_notifications_type_created` (`type`,`created_at`,`id`),
  KEY `idx_notifications_recipient_id` (`recipient_id`,`id`),
  KEY `idx_notifications_recipient_type_id` (`recipient_id`,`type`,`id`),
  KEY `idx_notifications_recipient_read_id` (`recipient_id`,`read_at`,`id`),
  CONSTRAINT `fk_notifications_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `privacy_preferences` (
  `user_id` int(11) NOT NULL,
  `profile_visibility` varchar(16) NOT NULL DEFAULT 'public',
  `messages_from` varchar(16) NOT NULL DEFAULT 'everyone',
  `show_relations` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime NOT NULL,
  `gender_visibility` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'private',
  `birth_date_visibility` varchar(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'private',
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_privacy_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reporter_id` int(11) NOT NULL,
  `target_type` varchar(32) NOT NULL,
  `target_id` varchar(128) NOT NULL,
  `reason` varchar(64) NOT NULL,
  `details` text DEFAULT NULL,
  `evidence_snapshot` JSON DEFAULT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'open',
  `assignee_id` int(11) DEFAULT NULL,
  `resolution` varchar(255) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_reports_target` (`target_type`,`target_id`,`status`),
  KEY `idx_reports_reporter` (`reporter_id`,`created_at`),
  KEY `idx_reports_status_updated` (`status`,`updated_at`,`id`),
  CONSTRAINT `fk_reports_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `role_grants` (
  `role_id` int(11) NOT NULL,
  `capability` varchar(100) NOT NULL,
  `scope` varchar(16) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`role_id`,`capability`,`scope`),
  KEY `idx_role_grants_capability` (`capability`),
  CONSTRAINT `fk_role_grants_capability` FOREIGN KEY (`capability`) REFERENCES `capabilities` (`capability`),
  CONSTRAINT `fk_role_grants_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL COMMENT '角色ID',
  `permission_id` int(11) NOT NULL COMMENT '权限ID',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_permission` (`role_id`,`permission_id`),
  KEY `role_id` (`role_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='角色权限关联表';
CREATE TABLE `system_upgrade_plan_steps` (
  `plan_pk` bigint(20) unsigned NOT NULL,
  `step_name` varchar(32) NOT NULL,
  `ordinal` smallint(5) unsigned NOT NULL,
  `state` varchar(16) NOT NULL DEFAULT 'pending',
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `cursor` varchar(2048) DEFAULT NULL,
  `failure_code` varchar(128) DEFAULT NULL,
  `started_at` datetime(6) DEFAULT NULL,
  `completed_at` datetime(6) DEFAULT NULL,
  `updated_at` datetime(6) NOT NULL,
  PRIMARY KEY (`plan_pk`,`step_name`),
  UNIQUE KEY `uk_system_upgrade_plan_order` (`plan_pk`,`ordinal`),
  KEY `idx_system_upgrade_steps_state` (`state`,`updated_at`,`plan_pk`),
  CONSTRAINT `fk_system_upgrade_steps_plan` FOREIGN KEY (`plan_pk`) REFERENCES `system_upgrade_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `user_follows` (
  `follower_id` int(11) NOT NULL,
  `followee_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`follower_id`,`followee_id`),
  KEY `idx_followee_created` (`followee_id`,`created_at`,`follower_id`),
  KEY `idx_followee_follower` (`followee_id`,`follower_id`),
  CONSTRAINT `fk_follows_followee` FOREIGN KEY (`followee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_follows_follower` FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `user_roles` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `idx_user_roles_role` (`role_id`),
  CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `user_safety_relations` (
  `actor_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `relation` varchar(16) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`actor_id`,`subject_id`,`relation`),
  KEY `idx_safety_subject` (`subject_id`,`relation`),
  KEY `idx_safety_blocked_page` (`actor_id`,`relation`,`created_at`,`subject_id`),
  CONSTRAINT `fk_safety_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_safety_subject` FOREIGN KEY (`subject_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `user_uid_registry` (
  `uid` char(12) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `issued_at` datetime NOT NULL,
  `retired_at` datetime DEFAULT NULL,
  PRIMARY KEY (`uid`),
  UNIQUE KEY `uk_user_uid_registry_user_id` (`user_id`),
  CONSTRAINT `fk_user_uid_registry_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_bin;
CREATE TABLE `view_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `resource_type` varchar(32) NOT NULL,
  `resource_id` varchar(128) NOT NULL,
  `last_viewed_at` datetime NOT NULL,
  `view_count` int(10) unsigned NOT NULL DEFAULT 1,
  `viewed_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  UNIQUE KEY `uk_history_resource` (`user_id`,`resource_type`,`resource_id`),
  KEY `idx_history_user_time` (`user_id`,`last_viewed_at`,`resource_id`),
  KEY `idx_history_user_viewed` (`user_id`,`viewed_at`,`id`),
  CONSTRAINT `fk_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `report_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `report_id` bigint(20) unsigned NOT NULL,
  `actor_id` int(11) DEFAULT NULL,
  `event_type` varchar(32) NOT NULL,
  `payload` JSON DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_report_events_report` (`report_id`,`created_at`),
  CONSTRAINT `fk_report_events_report` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
