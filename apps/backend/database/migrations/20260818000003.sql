-- LoveCards runtime schema compatibility migration.
-- Aligns cursor-based library/history persistence and creates worker heartbeats.
-- Existing rows are preserved; legacy composite keys remain as unique guards.

CREATE TABLE IF NOT EXISTS `runtime_workers` (
  `worker_id` VARCHAR(128) NOT NULL,
  `worker_type` VARCHAR(64) NOT NULL,
  `state` VARCHAR(16) NOT NULL,
  `release_id` VARCHAR(128) NOT NULL,
  `schema_version` VARCHAR(64) NOT NULL,
  `handler_catalog_hash` CHAR(64) NOT NULL,
  `jobs_processed` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `heartbeat_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`worker_id`),
  KEY `idx_runtime_workers_state` (`state`,`updated_at`),
  KEY `idx_runtime_workers_type` (`worker_type`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `library_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `resource_type` VARCHAR(32) NOT NULL,
  `resource_id` BIGINT NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_library_resource` (`user_id`,`resource_type`,`resource_id`),
  KEY `idx_library_user_created` (`user_id`,`created_at`,`id`),
  CONSTRAINT `fk_library_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `view_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `resource_type` VARCHAR(32) NOT NULL,
  `resource_id` BIGINT NOT NULL,
  `last_viewed_at` DATETIME NOT NULL,
  `view_count` INT UNSIGNED NOT NULL DEFAULT 1,
  `viewed_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_history_resource` (`user_id`,`resource_type`,`resource_id`),
  KEY `idx_history_user_viewed` (`user_id`,`viewed_at`,`id`),
  CONSTRAINT `fk_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP PROCEDURE IF EXISTS `mig_runtime_schema_compat_20260818`;
DELIMITER //
CREATE PROCEDURE `mig_runtime_schema_compat_20260818`()
BEGIN
    DECLARE v_has_id INT DEFAULT 0;
    DECLARE v_primary_is_id INT DEFAULT 0;
    DECLARE v_has_unique INT DEFAULT 0;
    DECLARE v_has_view_count INT DEFAULT 0;
    DECLARE v_has_viewed_at INT DEFAULT 0;
    DECLARE v_has_created_at INT DEFAULT 0;

    SELECT COUNT(*) INTO v_has_id
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_items' AND COLUMN_NAME = 'id';
    IF v_has_id = 0 THEN
        ALTER TABLE `library_items`
            ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE FIRST;
    END IF;

    SELECT COUNT(*) INTO v_primary_is_id
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_items'
      AND INDEX_NAME = 'PRIMARY' AND COLUMN_NAME = 'id';
    IF v_primary_is_id = 0 THEN
        ALTER TABLE `library_items` DROP PRIMARY KEY, ADD PRIMARY KEY (`id`);
    END IF;

    SELECT COUNT(*) INTO v_has_unique
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_items'
      AND INDEX_NAME = 'uk_library_resource';
    IF v_has_unique = 0 THEN
        ALTER TABLE `library_items`
            ADD UNIQUE KEY `uk_library_resource` (`user_id`,`resource_type`,`resource_id`);
    END IF;

    SELECT COUNT(*) INTO v_has_id
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'view_history' AND COLUMN_NAME = 'id';
    IF v_has_id = 0 THEN
        ALTER TABLE `view_history`
            ADD COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE FIRST;
    END IF;

    SELECT COUNT(*) INTO v_primary_is_id
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'view_history'
      AND INDEX_NAME = 'PRIMARY' AND COLUMN_NAME = 'id';
    IF v_primary_is_id = 0 THEN
        ALTER TABLE `view_history` DROP PRIMARY KEY, ADD PRIMARY KEY (`id`);
    END IF;

    SELECT COUNT(*) INTO v_has_unique
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'view_history'
      AND INDEX_NAME = 'uk_history_resource';
    IF v_has_unique = 0 THEN
        ALTER TABLE `view_history`
            ADD UNIQUE KEY `uk_history_resource` (`user_id`,`resource_type`,`resource_id`);
    END IF;

    SELECT COUNT(*) INTO v_has_view_count
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'view_history' AND COLUMN_NAME = 'view_count';
    IF v_has_view_count = 0 THEN
        ALTER TABLE `view_history`
            ADD COLUMN `view_count` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `last_viewed_at`;
    END IF;

    SELECT COUNT(*) INTO v_has_viewed_at
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'view_history' AND COLUMN_NAME = 'viewed_at';
    IF v_has_viewed_at = 0 THEN
        ALTER TABLE `view_history` ADD COLUMN `viewed_at` DATETIME NULL AFTER `view_count`;
    END IF;
    UPDATE `view_history` SET `viewed_at` = `last_viewed_at` WHERE `viewed_at` IS NULL;
    ALTER TABLE `view_history` MODIFY COLUMN `viewed_at` DATETIME NOT NULL;

    SELECT COUNT(*) INTO v_has_created_at
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'view_history' AND COLUMN_NAME = 'created_at';
    IF v_has_created_at = 0 THEN
        ALTER TABLE `view_history` ADD COLUMN `created_at` DATETIME NULL AFTER `viewed_at`;
    END IF;
    UPDATE `view_history` SET `created_at` = `last_viewed_at` WHERE `created_at` IS NULL;
    ALTER TABLE `view_history` MODIFY COLUMN `created_at` DATETIME NOT NULL;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'view_history'
          AND INDEX_NAME = 'idx_history_user_viewed'
    ) THEN
        ALTER TABLE `view_history`
            ADD KEY `idx_history_user_viewed` (`user_id`,`viewed_at`,`id`);
    END IF;
END //
DELIMITER ;

CALL `mig_runtime_schema_compat_20260818`();
DROP PROCEDURE IF EXISTS `mig_runtime_schema_compat_20260818`;
