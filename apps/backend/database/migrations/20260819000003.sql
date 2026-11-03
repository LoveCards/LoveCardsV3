-- LoveCards system release, upgrade-plan and backup ledgers.
-- Additive and idempotent on MySQL 5.7+.
-- @lc-phase expand

CREATE TABLE IF NOT EXISTS `system_releases` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `release_id` VARCHAR(128) NOT NULL,
  `sequence` BIGINT UNSIGNED NOT NULL,
  `version` VARCHAR(64) NOT NULL,
  `manifest_checksum` CHAR(64) NOT NULL,
  `manifest_json` JSON NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'staged',
  `created_by` INT NULL,
  `verified_at` DATETIME(6) NOT NULL,
  `activated_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_system_releases_release` (`release_id`),
  UNIQUE KEY `uk_system_releases_sequence` (`sequence`),
  KEY `idx_system_releases_status` (`status`,`updated_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `system_upgrade_plans` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `plan_id` VARCHAR(128) NOT NULL,
  `release_id` VARCHAR(128) NOT NULL,
  `previous_release_id` VARCHAR(128) NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
  `current_step` VARCHAR(32) NULL,
  `contract_approved` TINYINT(1) NOT NULL DEFAULT 0,
  `context_json` JSON NULL,
  `revision` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_by` INT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  `completed_at` DATETIME(6) NULL,
  `failed_at` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_system_upgrade_plans_key` (`plan_id`),
  KEY `idx_system_upgrade_plans_status` (`status`,`updated_at`,`id`),
  KEY `idx_system_upgrade_plans_release` (`release_id`,`created_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `system_upgrade_plan_steps` (
  `plan_pk` BIGINT UNSIGNED NOT NULL,
  `step_name` VARCHAR(32) NOT NULL,
  `ordinal` SMALLINT UNSIGNED NOT NULL,
  `state` VARCHAR(16) NOT NULL DEFAULT 'pending',
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `cursor` VARCHAR(2048) NULL,
  `failure_code` VARCHAR(128) NULL,
  `started_at` DATETIME(6) NULL,
  `completed_at` DATETIME(6) NULL,
  `updated_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`plan_pk`,`step_name`),
  UNIQUE KEY `uk_system_upgrade_plan_order` (`plan_pk`,`ordinal`),
  KEY `idx_system_upgrade_steps_state` (`state`,`updated_at`,`plan_pk`),
  CONSTRAINT `fk_system_upgrade_steps_plan`
    FOREIGN KEY (`plan_pk`) REFERENCES `system_upgrade_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `system_backups` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `backup_id` VARCHAR(128) NOT NULL,
  `plan_id` VARCHAR(128) NULL,
  `backup_type` VARCHAR(24) NOT NULL,
  `driver` VARCHAR(32) NOT NULL,
  `location` VARCHAR(1024) NOT NULL,
  `checksum` CHAR(64) NULL,
  `size_bytes` BIGINT UNSIGNED NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
  `metadata_json` JSON NULL,
  `created_by` INT NULL,
  `created_at` DATETIME(6) NOT NULL,
  `verified_at` DATETIME(6) NULL,
  `restored_at` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_system_backups_key` (`backup_id`),
  KEY `idx_system_backups_plan` (`plan_id`,`backup_type`,`created_at`,`id`),
  KEY `idx_system_backups_status` (`status`,`created_at`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
