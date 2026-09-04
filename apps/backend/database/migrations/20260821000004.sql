-- Persist content mentions so publication retries and later edits cannot duplicate notifications.
-- Additive/idempotent for MySQL 5.7; no destructive rewrite.
-- @lc-phase expand

CREATE TABLE IF NOT EXISTS `content_mentions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `resource_type` VARCHAR(16) NOT NULL,
  `resource_id` BIGINT UNSIGNED NOT NULL,
  `card_id` BIGINT UNSIGNED NOT NULL,
  `comment_id` BIGINT UNSIGNED NULL,
  `actor_id` INT NOT NULL,
  `mentioned_user_id` INT NOT NULL,
  `mentioned_username` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_content_mentions_resource_user` (`resource_type`,`resource_id`,`mentioned_user_id`),
  KEY `idx_content_mentions_user_id` (`mentioned_user_id`,`id`),
  KEY `idx_content_mentions_card_id` (`card_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP PROCEDURE IF EXISTS `mig_content_mentions_20260821`;
DELIMITER //
CREATE PROCEDURE `mig_content_mentions_20260821`()
BEGIN
    DECLARE v_index_parts INT DEFAULT 0;
    DECLARE v_valid_index_parts INT DEFAULT 0;

    IF EXISTS (
        SELECT 1 FROM `users`
        GROUP BY LEFT(`username`, 191)
        HAVING COUNT(*) > 1
        LIMIT 1
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'users.username uniqueness preflight failed before enabling mentions';
    END IF;

    SELECT COUNT(*) INTO v_index_parts
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND INDEX_NAME = 'uk_users_username';

    IF v_index_parts = 0 THEN
        ALTER TABLE `users`
            ADD UNIQUE KEY `uk_users_username` (`username`(191)),
            ALGORITHM=INPLACE,
            LOCK=NONE;
    ELSE
        SELECT COUNT(*) INTO v_valid_index_parts
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'users'
              AND INDEX_NAME = 'uk_users_username'
              AND NON_UNIQUE = 0
              AND SEQ_IN_INDEX = 1
              AND COLUMN_NAME = 'username'
              AND (SUB_PART = 191 OR SUB_PART IS NULL);
        IF v_index_parts <> 1 OR v_valid_index_parts <> 1 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'uk_users_username exists with an incompatible definition';
        END IF;
    END IF;
END //
DELIMITER ;

CALL `mig_content_mentions_20260821`();
DROP PROCEDURE IF EXISTS `mig_content_mentions_20260821`;
