-- Expand likes from card-only to card/comment targets without losing existing card likes.
-- Legacy rows are normalized before the composite uniqueness constraint changes.
-- @lc-phase expand

DROP PROCEDURE IF EXISTS `mig_likes_comment_targets_20260821`;
DELIMITER //
CREATE PROCEDURE `mig_likes_comment_targets_20260821`()
BEGIN
    UPDATE `likes`
    SET `ref_type` = 'card'
    WHERE `ref_type` IS NULL OR `ref_type` = '';

    UPDATE `likes`
    SET `ref_id` = `pid`
    WHERE `ref_id` IS NULL;

    IF EXISTS (
        SELECT 1 FROM (
            SELECT `ref_type`, `ref_id`, `uid`
            FROM `likes`
            GROUP BY `ref_type`, `ref_id`, `uid`
            HAVING COUNT(*) > 1
        ) AS conflicting_likes
    ) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'conflicting likes for (ref_type, ref_id, uid); manual cleanup required';
    END IF;

    ALTER TABLE `likes`
        MODIFY COLUMN `ref_type` VARCHAR(32) NOT NULL COMMENT '内容类型: card, comment',
        MODIFY COLUMN `ref_id` INT(11) NOT NULL COMMENT '内容ID';

    IF EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND INDEX_NAME = 'uk_likes_pid_uid'
    ) THEN
        ALTER TABLE `likes` DROP INDEX `uk_likes_pid_uid`;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'likes' AND INDEX_NAME = 'uk_likes_ref_uid'
    ) THEN
        ALTER TABLE `likes` ADD UNIQUE KEY `uk_likes_ref_uid` (`ref_type`, `ref_id`, `uid`);
    END IF;
END //
DELIMITER ;

CALL `mig_likes_comment_targets_20260821`();
DROP PROCEDURE IF EXISTS `mig_likes_comment_targets_20260821`;
