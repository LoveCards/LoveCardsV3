-- Public immutable user UID foundation.
-- Internal users.id remains the relational/authentication key.
-- @lc-phase expand
DROP PROCEDURE IF EXISTS `mig_user_uid_expand_20260825`;
DELIMITER //
CREATE PROCEDURE `mig_user_uid_expand_20260825`()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'uid') THEN
        ALTER TABLE `users` ADD COLUMN `uid` CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER `id`;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_uid_registry') THEN
        CREATE TABLE `user_uid_registry` (
            `uid` CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            `user_id` INT(11) NULL,
            `issued_at` DATETIME NOT NULL,
            `retired_at` DATETIME NULL,
            PRIMARY KEY (`uid`),
            UNIQUE KEY `uk_user_uid_registry_user_id` (`user_id`),
            CONSTRAINT `fk_user_uid_registry_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=ascii COLLATE=ascii_bin;
    END IF;
END //
DELIMITER ;
CALL `mig_user_uid_expand_20260825`();
DROP PROCEDURE IF EXISTS `mig_user_uid_expand_20260825`;

-- @lc-phase backfill
-- SHA-256-derived decimal values are deterministic for retry-safe migration;
-- new accounts use CSPRNG generation in application code.
UPDATE `users`
SET `uid` = LPAD(CONV(SUBSTRING(SHA2(CONCAT(`id`, ':lovecards-public-uid'), 256), 1, 9), 16, 10), 12, '0')
WHERE `uid` IS NULL OR `uid` = '';
INSERT INTO `user_uid_registry` (`uid`, `user_id`, `issued_at`)
SELECT u.`uid`, u.`id`, NOW() FROM `users` u
WHERE u.`uid` IS NOT NULL AND u.`uid` <> ''
  AND NOT EXISTS (SELECT 1 FROM `user_uid_registry` r WHERE r.`uid` = u.`uid`);

-- @lc-phase verify
DROP PROCEDURE IF EXISTS `mig_user_uid_verify_20260825`;
DELIMITER //
CREATE PROCEDURE `mig_user_uid_verify_20260825`()
BEGIN
    IF EXISTS (SELECT 1 FROM `users` WHERE `uid` IS NULL OR `uid` NOT REGEXP '^[0-9]{12}$') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'users.uid 格式或完整性校验失败';
    END IF;
    IF EXISTS (SELECT `uid` FROM `users` GROUP BY `uid` HAVING COUNT(*) > 1) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'users.uid 存在重复值';
    END IF;
    IF (SELECT COUNT(*) FROM `users` WHERE `uid` IS NOT NULL) <> (SELECT COUNT(*) FROM `user_uid_registry` WHERE `user_id` IS NOT NULL) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'UID 登记表与用户表数量不一致';
    END IF;
END //
DELIMITER ;
CALL `mig_user_uid_verify_20260825`();
DROP PROCEDURE IF EXISTS `mig_user_uid_verify_20260825`;

-- @lc-phase contract
DROP PROCEDURE IF EXISTS `mig_user_uid_contract_20260825`;
DELIMITER //
CREATE PROCEDURE `mig_user_uid_contract_20260825`()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'uk_users_uid') THEN
        ALTER TABLE `users` ADD UNIQUE KEY `uk_users_uid` (`uid`);
    END IF;
    ALTER TABLE `users` MODIFY COLUMN `uid` CHAR(12) CHARACTER SET ascii COLLATE ascii_bin NOT NULL;
END //
DELIMITER ;
CALL `mig_user_uid_contract_20260825`();
DROP PROCEDURE IF EXISTS `mig_user_uid_contract_20260825`;
