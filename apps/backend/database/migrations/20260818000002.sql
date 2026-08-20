-- LoveCards auth session compatibility migration.
-- Converts the legacy data.sql auth_sessions.expires_at shape to the
-- revocable-session contract consumed by SessionRegistry.
-- MySQL 5.7+, additive/idempotent; no session rows are deleted.

DROP PROCEDURE IF EXISTS `mig_auth_sessions_compat_20260818`;
DELIMITER //
CREATE PROCEDURE `mig_auth_sessions_compat_20260818`()
BEGIN
    DECLARE v_table_exists INT DEFAULT 0;
    DECLARE v_has_token_expires INT DEFAULT 0;
    DECLARE v_has_legacy_expires INT DEFAULT 0;
    DECLARE v_has_renewable INT DEFAULT 0;
    DECLARE v_has_security INT DEFAULT 0;
    DECLARE v_has_revoked_reason INT DEFAULT 0;
    DECLARE v_has_rotated_to INT DEFAULT 0;
    DECLARE v_has_expiry_index INT DEFAULT 0;

    SELECT COUNT(*) INTO v_table_exists
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auth_sessions';

    IF v_table_exists = 0 THEN
        CREATE TABLE `auth_sessions` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `jti_hash` CHAR(64) NOT NULL,
          `user_id` INT(11) NOT NULL,
          `token_expires_at` DATETIME NOT NULL,
          `renewable_until` DATETIME NOT NULL,
          `security_version` INT UNSIGNED NOT NULL DEFAULT 1,
          `revoked_at` DATETIME DEFAULT NULL,
          `revoked_reason` VARCHAR(64) DEFAULT NULL,
          `rotated_to_session_id` BIGINT UNSIGNED DEFAULT NULL,
          `created_at` DATETIME NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_auth_sessions_jti` (`jti_hash`),
          KEY `idx_auth_sessions_user` (`user_id`),
          KEY `idx_auth_sessions_expiry` (`token_expires_at`),
          CONSTRAINT `fk_auth_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ELSE
        SELECT COUNT(*) INTO v_has_token_expires
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auth_sessions' AND COLUMN_NAME = 'token_expires_at';
        SELECT COUNT(*) INTO v_has_legacy_expires
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auth_sessions' AND COLUMN_NAME = 'expires_at';

        IF v_has_token_expires = 0 AND v_has_legacy_expires = 1 THEN
            ALTER TABLE `auth_sessions`
                CHANGE COLUMN `expires_at` `token_expires_at` DATETIME NOT NULL;
            SET v_has_token_expires = 1;
        ELSEIF v_has_token_expires = 0 THEN
            SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'auth_sessions requires token_expires_at or legacy expires_at';
        END IF;

        SELECT COUNT(*) INTO v_has_renewable
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auth_sessions' AND COLUMN_NAME = 'renewable_until';
        IF v_has_renewable = 0 THEN
            ALTER TABLE `auth_sessions` ADD COLUMN `renewable_until` DATETIME NULL AFTER `token_expires_at`;
            UPDATE `auth_sessions` SET `renewable_until` = `token_expires_at` WHERE `renewable_until` IS NULL;
            ALTER TABLE `auth_sessions` MODIFY COLUMN `renewable_until` DATETIME NOT NULL;
        ELSE
            UPDATE `auth_sessions` SET `renewable_until` = `token_expires_at` WHERE `renewable_until` IS NULL;
            ALTER TABLE `auth_sessions` MODIFY COLUMN `renewable_until` DATETIME NOT NULL;
        END IF;

        SELECT COUNT(*) INTO v_has_security
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auth_sessions' AND COLUMN_NAME = 'security_version';
        IF v_has_security = 0 THEN
            ALTER TABLE `auth_sessions` ADD COLUMN `security_version` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `renewable_until`;
        ELSE
            UPDATE `auth_sessions` SET `security_version` = 1 WHERE `security_version` IS NULL OR `security_version` < 1;
            ALTER TABLE `auth_sessions` MODIFY COLUMN `security_version` INT UNSIGNED NOT NULL DEFAULT 1;
        END IF;

        SELECT COUNT(*) INTO v_has_revoked_reason
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auth_sessions' AND COLUMN_NAME = 'revoked_reason';
        IF v_has_revoked_reason = 0 THEN
            ALTER TABLE `auth_sessions` ADD COLUMN `revoked_reason` VARCHAR(64) DEFAULT NULL AFTER `revoked_at`;
        END IF;

        SELECT COUNT(*) INTO v_has_rotated_to
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auth_sessions' AND COLUMN_NAME = 'rotated_to_session_id';
        IF v_has_rotated_to = 0 THEN
            ALTER TABLE `auth_sessions` ADD COLUMN `rotated_to_session_id` BIGINT UNSIGNED DEFAULT NULL AFTER `revoked_reason`;
        END IF;

        SELECT COUNT(*) INTO v_has_expiry_index
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auth_sessions'
          AND INDEX_NAME = 'idx_auth_sessions_expiry' AND COLUMN_NAME = 'token_expires_at';
        IF v_has_expiry_index = 0 THEN
            IF EXISTS (
                SELECT 1 FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'auth_sessions'
                  AND INDEX_NAME = 'idx_auth_sessions_expiry'
            ) THEN
                ALTER TABLE `auth_sessions` DROP INDEX `idx_auth_sessions_expiry`;
            END IF;
            ALTER TABLE `auth_sessions` ADD KEY `idx_auth_sessions_expiry` (`token_expires_at`);
        END IF;
    END IF;
END //
DELIMITER ;

CALL `mig_auth_sessions_compat_20260818`();
DROP PROCEDURE IF EXISTS `mig_auth_sessions_compat_20260818`;
