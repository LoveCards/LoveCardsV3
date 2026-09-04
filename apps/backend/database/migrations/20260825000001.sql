-- Ordinary-user self-service profile, session, safety and notification preferences.
-- MySQL 5.7 compatible; expansion is additive and existing sessions receive an opaque public ID.
-- @lc-phase expand

DROP PROCEDURE IF EXISTS `mig_user_self_service_expand_20260825`;
DELIMITER //
CREATE PROCEDURE `mig_user_self_service_expand_20260825`()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'bio') THEN
        ALTER TABLE `users` ADD COLUMN `bio` VARCHAR(160) NULL AFTER `username`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'location') THEN
        ALTER TABLE `users` ADD COLUMN `location` VARCHAR(64) NULL AFTER `bio`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auth_sessions' AND COLUMN_NAME = 'public_id') THEN
        ALTER TABLE `auth_sessions` ADD COLUMN `public_id` CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER `id`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_safety_relations' AND INDEX_NAME = 'idx_safety_blocked_page') THEN
        ALTER TABLE `user_safety_relations` ADD KEY `idx_safety_blocked_page` (`actor_id`,`relation`,`created_at`,`subject_id`);
    END IF;
END //
DELIMITER ;
CALL `mig_user_self_service_expand_20260825`();
DROP PROCEDURE IF EXISTS `mig_user_self_service_expand_20260825`;

CREATE TABLE IF NOT EXISTS `notification_preferences` (
  `user_id` INT NOT NULL,
  `notification_type` VARCHAR(64) NOT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`user_id`,`notification_type`),
  CONSTRAINT `fk_notification_preferences_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- @lc-phase migrate
UPDATE `auth_sessions`
SET `public_id` = LOWER(REPLACE(UUID(), '-', ''))
WHERE `public_id` IS NULL OR `public_id` = '';

-- @lc-phase contract
DROP PROCEDURE IF EXISTS `mig_user_self_service_contract_20260825`;
DELIMITER //
CREATE PROCEDURE `mig_user_self_service_contract_20260825`()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auth_sessions' AND INDEX_NAME = 'uk_auth_sessions_public_id') THEN
        ALTER TABLE `auth_sessions` ADD UNIQUE KEY `uk_auth_sessions_public_id` (`public_id`);
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auth_sessions' AND COLUMN_NAME = 'public_id' AND IS_NULLABLE = 'YES') THEN
        ALTER TABLE `auth_sessions` MODIFY COLUMN `public_id` CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL;
    END IF;
END //
DELIMITER ;
CALL `mig_user_self_service_contract_20260825`();
DROP PROCEDURE IF EXISTS `mig_user_self_service_contract_20260825`;
