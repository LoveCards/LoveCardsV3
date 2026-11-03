-- User profile gender/birth date and privacy visibility.
-- MySQL 5.7 compatible; additive and idempotent.
-- @lc-phase expand
DROP PROCEDURE IF EXISTS `mig_profile_fields_expand_20260825`;
DELIMITER //
CREATE PROCEDURE `mig_profile_fields_expand_20260825`()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'gender') THEN
        ALTER TABLE `users` ADD COLUMN `gender` VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER `location`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'birth_date') THEN
        ALTER TABLE `users` ADD COLUMN `birth_date` DATE NULL AFTER `gender`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'privacy_preferences' AND COLUMN_NAME = 'gender_visibility') THEN
        ALTER TABLE `privacy_preferences` ADD COLUMN `gender_visibility` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'private';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'privacy_preferences' AND COLUMN_NAME = 'birth_date_visibility') THEN
        ALTER TABLE `privacy_preferences` ADD COLUMN `birth_date_visibility` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'private';
    END IF;
END //
DELIMITER ;
CALL `mig_profile_fields_expand_20260825`();
DROP PROCEDURE IF EXISTS `mig_profile_fields_expand_20260825`;

-- @lc-phase contract
UPDATE `privacy_preferences`
SET `gender_visibility` = 'private'
WHERE `gender_visibility` IS NULL OR `gender_visibility` NOT IN ('public','followers','private');
UPDATE `privacy_preferences`
SET `birth_date_visibility` = 'private'
WHERE `birth_date_visibility` IS NULL OR `birth_date_visibility` NOT IN ('public','followers','private');
