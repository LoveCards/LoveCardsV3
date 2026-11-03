-- Community administration and messaging compatibility.
-- Additive/idempotent for MySQL 5.7; no destructive rewrite.

DROP PROCEDURE IF EXISTS `mig_admin_community_20260819`;
DELIMITER //
CREATE PROCEDURE `mig_admin_community_20260819`()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reports' AND COLUMN_NAME = 'evidence_snapshot'
    ) THEN
        ALTER TABLE `reports` ADD COLUMN `evidence_snapshot` JSON NULL AFTER `details`;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conversations' AND COLUMN_NAME = 'direct_key'
    ) THEN
        ALTER TABLE `conversations` ADD COLUMN `direct_key` CHAR(64) NULL AFTER `id`;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conversations' AND INDEX_NAME = 'uk_conversations_direct'
    ) THEN
        ALTER TABLE `conversations` ADD UNIQUE KEY `uk_conversations_direct` (`direct_key`);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conversation_members' AND INDEX_NAME = 'idx_members_unread'
    ) THEN
        ALTER TABLE `conversation_members` ADD KEY `idx_members_unread` (`user_id`,`last_read_message_id`,`conversation_id`);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auth_sessions' AND INDEX_NAME = 'idx_auth_sessions_user_active'
    ) THEN
        ALTER TABLE `auth_sessions` ADD KEY `idx_auth_sessions_user_active` (`user_id`,`revoked_at`,`token_expires_at`,`id`);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND COLUMN_NAME = 'purge_status'
    ) THEN
        ALTER TABLE `files` ADD COLUMN `purge_status` VARCHAR(24) NULL AFTER `expire_at`;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND COLUMN_NAME = 'purge_attempts'
    ) THEN
        ALTER TABLE `files` ADD COLUMN `purge_attempts` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `purge_status`;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND COLUMN_NAME = 'purge_requested_at'
    ) THEN
        ALTER TABLE `files` ADD COLUMN `purge_requested_at` DATETIME NULL AFTER `purge_attempts`;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND COLUMN_NAME = 'purge_storage_deleted_at'
    ) THEN
        ALTER TABLE `files` ADD COLUMN `purge_storage_deleted_at` DATETIME NULL AFTER `purge_requested_at`;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND COLUMN_NAME = 'purge_error'
    ) THEN
        ALTER TABLE `files` ADD COLUMN `purge_error` VARCHAR(255) NULL AFTER `purge_storage_deleted_at`;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files' AND INDEX_NAME = 'idx_files_purge'
    ) THEN
        ALTER TABLE `files` ADD KEY `idx_files_purge` (`purge_status`,`updated_at`,`id`);
    END IF;
END //
DELIMITER ;

CALL `mig_admin_community_20260819`();
DROP PROCEDURE IF EXISTS `mig_admin_community_20260819`;

CREATE TABLE IF NOT EXISTS `file_references` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `file_id` INT NOT NULL,
  `resource_type` VARCHAR(32) NOT NULL,
  `resource_id` VARCHAR(128) NOT NULL,
  `purpose` VARCHAR(32) NOT NULL DEFAULT 'attachment',
  `is_public` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_file_reference` (`file_id`,`resource_type`,`resource_id`,`purpose`),
  KEY `idx_file_reference_resource` (`resource_type`,`resource_id`,`file_id`),
  CONSTRAINT `fk_file_references_file` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `file_references` (`file_id`,`resource_type`,`resource_id`,`purpose`,`is_public`,`created_at`)
SELECT f.id, f.ref_type, CAST(f.ref_id AS CHAR), 'attachment', f.is_public, COALESCE(f.created_at, NOW())
FROM `files` f
LEFT JOIN `file_references` existing
  ON existing.file_id = f.id
 AND existing.resource_type = f.ref_type
 AND existing.resource_id = CAST(f.ref_id AS CHAR)
 AND existing.purpose = 'attachment'
WHERE f.ref_type IS NOT NULL AND f.ref_type <> '' AND f.ref_id IS NOT NULL
  AND existing.id IS NULL;

INSERT INTO `capabilities` (`capability`, `description`, `risk_level`)
SELECT source.capability, source.description, source.risk_level
FROM (
  SELECT 'operations.read' capability, 'View operations status' description, 1 risk_level
  UNION ALL SELECT 'operations.migrate', 'Advance system migrations', 3
  UNION ALL SELECT 'operations.retry', 'Retry asynchronous delivery', 2
  UNION ALL SELECT 'audit.read', 'View audit logs', 2
  UNION ALL SELECT 'reports.read', 'View reports', 2
  UNION ALL SELECT 'reports.manage', 'Manage reports', 2
  UNION ALL SELECT 'users.security.read', 'View user security status', 2
  UNION ALL SELECT 'users.sessions.revoke', 'Revoke user sessions', 3
  UNION ALL SELECT 'users.recovery.read', 'View account recovery status', 2
  UNION ALL SELECT 'communications.read', 'View communications operations metadata', 2
  UNION ALL SELECT 'community.insights.read', 'View privacy-safe community aggregates', 1
  UNION ALL SELECT 'files.usage.read', 'View file usage references', 2
) source
LEFT JOIN `capabilities` existing ON existing.capability = source.capability
WHERE existing.capability IS NULL;

INSERT INTO `role_grants` (`role_id`, `capability`, `scope`, `created_at`)
SELECT roles.role_id, source.capability, 'global', NOW()
FROM (
  SELECT 1 role_id UNION ALL SELECT 2
) roles
CROSS JOIN (
  SELECT 'operations.read' capability
  UNION ALL SELECT 'operations.migrate'
  UNION ALL SELECT 'operations.retry'
  UNION ALL SELECT 'audit.read'
  UNION ALL SELECT 'reports.read'
  UNION ALL SELECT 'reports.manage'
  UNION ALL SELECT 'users.security.read'
  UNION ALL SELECT 'users.sessions.revoke'
  UNION ALL SELECT 'users.recovery.read'
  UNION ALL SELECT 'communications.read'
  UNION ALL SELECT 'community.insights.read'
  UNION ALL SELECT 'files.usage.read'
) source
LEFT JOIN `role_grants` existing
  ON existing.role_id = roles.role_id
 AND existing.capability = source.capability
 AND existing.scope = 'global'
WHERE existing.role_id IS NULL;
