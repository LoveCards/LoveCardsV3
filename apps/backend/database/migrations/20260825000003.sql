-- Correct immutable creation timestamps and add high-frequency content indexes.
-- MySQL 5.7 compatible; additive/index changes are guarded and safe to rerun.
-- @lc-phase expand
SET @lc_old_sql_mode_20260825 = @@SESSION.sql_mode;
SET SESSION sql_mode = '';
DROP PROCEDURE IF EXISTS `mig_content_integrity_expand_20260825`;
DELIMITER //
CREATE PROCEDURE `mig_content_integrity_expand_20260825`()
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cards' AND COLUMN_NAME = 'created_at') THEN
        ALTER TABLE `cards` MODIFY COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comments' AND COLUMN_NAME = 'created_at') THEN
        ALTER TABLE `comments` MODIFY COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comments' AND COLUMN_NAME = 'updated_at') THEN
        UPDATE `comments` SET `updated_at` = `created_at` WHERE `updated_at` = '0000-00-00 00:00:00';
        ALTER TABLE `comments` MODIFY COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL;
    END IF;
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tags_map' AND COLUMN_NAME = 'created_at') THEN
        ALTER TABLE `tags_map` MODIFY COLUMN `created_at` TIMESTAMP NULL DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'comments' AND INDEX_NAME = 'idx_comments_card_thread') THEN
        ALTER TABLE `comments` ADD KEY `idx_comments_card_thread` (`pid`,`parent_id`,`status`,`deleted_at`,`created_at`,`id`);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tags_map' AND INDEX_NAME = 'idx_tags_map_card_tag') THEN
        ALTER TABLE `tags_map` ADD KEY `idx_tags_map_card_tag` (`pid`,`tag_id`);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tags_map' AND INDEX_NAME = 'idx_tags_map_tag_card') THEN
        ALTER TABLE `tags_map` ADD KEY `idx_tags_map_tag_card` (`tag_id`,`pid`);
    END IF;
END //
DELIMITER ;
CALL `mig_content_integrity_expand_20260825`();
DROP PROCEDURE IF EXISTS `mig_content_integrity_expand_20260825`;
SET SESSION sql_mode = @lc_old_sql_mode_20260825;

-- @lc-phase contract
SELECT 1;
