-- LoveCards platform extensions. Additive, MySQL 5.7 compatible.
-- data.sql in newer clean installs already contains these columns, so the
-- migration must adopt both clean and legacy schemas without duplicate-column
-- failures.
DROP PROCEDURE IF EXISTS `mig_platform_extensions_20260818`;
DELIMITER //
CREATE PROCEDURE `mig_platform_extensions_20260818`()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'security_version'
    ) THEN
        ALTER TABLE `users` ADD COLUMN `security_version` INT UNSIGNED NOT NULL DEFAULT 1;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'credentials_changed_at'
    ) THEN
        ALTER TABLE `users` ADD COLUMN `credentials_changed_at` DATETIME NULL;
    END IF;
END //
DELIMITER ;
CALL `mig_platform_extensions_20260818`();
DROP PROCEDURE IF EXISTS `mig_platform_extensions_20260818`;
CREATE TABLE IF NOT EXISTS `user_follows` (
  `follower_id` INT NOT NULL, `followee_id` INT NOT NULL, `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`follower_id`,`followee_id`), KEY `idx_followee_created` (`followee_id`,`created_at`,`follower_id`),
  CONSTRAINT `fk_follows_follower` FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_follows_followee` FOREIGN KEY (`followee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `library_items` (
  `user_id` INT NOT NULL, `resource_type` VARCHAR(32) NOT NULL, `resource_id` BIGINT NOT NULL, `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`user_id`,`resource_type`,`resource_id`), KEY `idx_library_user_created` (`user_id`,`created_at`,`resource_id`),
  CONSTRAINT `fk_library_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `view_history` (
  `user_id` INT NOT NULL, `resource_type` VARCHAR(32) NOT NULL, `resource_id` BIGINT NOT NULL, `last_viewed_at` DATETIME NOT NULL,
  PRIMARY KEY (`user_id`,`resource_type`,`resource_id`), KEY `idx_history_user_time` (`user_id`,`last_viewed_at`,`resource_id`),
  CONSTRAINT `fk_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `privacy_preferences` (
  `user_id` INT NOT NULL, `profile_visibility` VARCHAR(16) NOT NULL DEFAULT 'public', `messages_from` VARCHAR(16) NOT NULL DEFAULT 'everyone', `show_relations` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` DATETIME NOT NULL, PRIMARY KEY (`user_id`), CONSTRAINT `fk_privacy_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `user_safety_relations` (
  `actor_id` INT NOT NULL, `subject_id` INT NOT NULL, `relation` VARCHAR(16) NOT NULL, `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`actor_id`,`subject_id`,`relation`), KEY `idx_safety_subject` (`subject_id`,`relation`),
  CONSTRAINT `fk_safety_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_safety_subject` FOREIGN KEY (`subject_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `reports` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `reporter_id` INT NOT NULL, `target_type` VARCHAR(32) NOT NULL, `target_id` BIGINT NOT NULL,
  `reason` VARCHAR(64) NOT NULL, `details` TEXT NULL, `status` VARCHAR(16) NOT NULL DEFAULT 'open', `created_at` DATETIME NOT NULL, `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`), KEY `idx_reports_target` (`target_type`,`target_id`,`status`), KEY `idx_reports_reporter` (`reporter_id`,`created_at`),
  CONSTRAINT `fk_reports_reporter` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `report_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `report_id` BIGINT UNSIGNED NOT NULL, `actor_id` INT NULL, `event_type` VARCHAR(32) NOT NULL, `payload` JSON NULL, `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`), KEY `idx_report_events_report` (`report_id`,`created_at`), CONSTRAINT `fk_report_events_report` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `recipient_id` INT NOT NULL, `type` VARCHAR(64) NOT NULL, `actor_id` INT NULL, `resource_type` VARCHAR(32) NULL, `resource_id` BIGINT NULL,
  `payload` JSON NULL, `dedupe_key` VARCHAR(191) NULL, `read_at` DATETIME NULL, `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `uk_notification_dedupe` (`recipient_id`,`dedupe_key`), KEY `idx_notifications_recipient` (`recipient_id`,`created_at`,`id`),
  CONSTRAINT `fk_notifications_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `conversations` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `conversation_members` (`conversation_id` BIGINT UNSIGNED NOT NULL, `user_id` INT NOT NULL, `last_read_message_id` BIGINT UNSIGNED NULL, `created_at` DATETIME NOT NULL, PRIMARY KEY (`conversation_id`,`user_id`), KEY `idx_members_user` (`user_id`,`conversation_id`), CONSTRAINT `fk_members_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE, CONSTRAINT `fk_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `messages` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `conversation_id` BIGINT UNSIGNED NOT NULL, `sender_id` INT NOT NULL, `client_message_id` VARCHAR(128) NOT NULL, `body` TEXT NOT NULL, `created_at` DATETIME NOT NULL, `deleted_at` DATETIME NULL, PRIMARY KEY (`id`), UNIQUE KEY `uk_message_client` (`sender_id`,`client_message_id`), KEY `idx_messages_conversation` (`conversation_id`,`id`), CONSTRAINT `fk_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE, CONSTRAINT `fk_messages_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `card_drafts` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` INT NOT NULL, `title` VARCHAR(255) NOT NULL DEFAULT '', `content` TEXT NOT NULL, `payload` JSON NULL, `version` INT UNSIGNED NOT NULL DEFAULT 1, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NOT NULL, `deleted_at` DATETIME NULL, PRIMARY KEY (`id`), KEY `idx_drafts_user_updated` (`user_id`,`updated_at`,`id`), CONSTRAINT `fk_drafts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `outbox_events` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `event_type` VARCHAR(128) NOT NULL, `event_version` SMALLINT UNSIGNED NOT NULL DEFAULT 1, `aggregate_type` VARCHAR(64) NOT NULL, `aggregate_key` VARCHAR(128) NOT NULL, `actor_id` INT NULL, `correlation_id` VARCHAR(128) NULL, `causation_id` BIGINT UNSIGNED NULL, `payload` JSON NOT NULL, `occurred_at` DATETIME(6) NOT NULL, `created_release_id` VARCHAR(128) NULL, `created_at` DATETIME(6) NOT NULL, PRIMARY KEY (`id`), KEY `idx_outbox_created` (`created_at`,`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `event_deliveries` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `event_id` BIGINT UNSIGNED NOT NULL, `handler_key` VARCHAR(128) NOT NULL, `queue_name` VARCHAR(64) NOT NULL DEFAULT 'default', `execution_mode` VARCHAR(16) NOT NULL DEFAULT 'worker', `status` VARCHAR(16) NOT NULL DEFAULT 'pending', `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0, `available_at` DATETIME(6) NOT NULL, `claim_token` CHAR(64) NULL, `claimed_by` VARCHAR(128) NULL, `lease_expires_at` DATETIME(6) NULL, `last_error` VARCHAR(255) NULL, `completed_at` DATETIME(6) NULL, `created_at` DATETIME(6) NOT NULL, `updated_at` DATETIME(6) NOT NULL, PRIMARY KEY (`id`), UNIQUE KEY `uk_delivery_handler` (`event_id`,`handler_key`), KEY `idx_delivery_queue` (`queue_name`,`execution_mode`,`status`,`available_at`,`id`), CONSTRAINT `fk_delivery_event` FOREIGN KEY (`event_id`) REFERENCES `outbox_events` (`id`) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `schema_migrations` (`migration_id` VARCHAR(128) NOT NULL, `checksum` CHAR(64) NOT NULL, `applied_at` DATETIME(6) NOT NULL, PRIMARY KEY (`migration_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `system_upgrade_steps` (`migration_id` VARCHAR(128) NOT NULL, `checksum` CHAR(64) NOT NULL, `phase` VARCHAR(16) NOT NULL, `status` VARCHAR(16) NOT NULL, `cursor` VARCHAR(2048) NULL, `revision` INT UNSIGNED NOT NULL DEFAULT 0, `attempts` INT UNSIGNED NOT NULL DEFAULT 0, `failure_code` VARCHAR(128) NULL, `created_at` DATETIME NOT NULL, `updated_at` DATETIME NOT NULL, PRIMARY KEY (`migration_id`), KEY `idx_upgrade_status` (`status`,`updated_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `audit_logs` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `actor_id` INT NULL, `action` VARCHAR(128) NOT NULL, `resource_type` VARCHAR(64) NULL, `resource_id` VARCHAR(128) NULL, `request_id` VARCHAR(128) NULL, `ip_hash` CHAR(64) NULL, `payload` JSON NULL, `created_at` DATETIME(6) NOT NULL, PRIMARY KEY (`id`), KEY `idx_audit_actor_time` (`actor_id`,`created_at`), KEY `idx_audit_resource` (`resource_type`,`resource_id`,`created_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `security_challenges` (`id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `user_id` INT NULL, `purpose` VARCHAR(32) NOT NULL, `subject_hash` CHAR(64) NOT NULL, `secret_hash` CHAR(64) NOT NULL, `expires_at` DATETIME NOT NULL, `consumed_at` DATETIME NULL, `attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0, `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`), KEY `idx_challenge_lookup` (`purpose`,`subject_hash`,`expires_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
