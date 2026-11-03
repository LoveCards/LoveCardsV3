-- Keep recipient notification reads index-backed for both the all-types and type-filtered cursor paths.
-- Additive/idempotent for MySQL 5.7; no destructive rewrite.
-- @lc-phase expand

DROP PROCEDURE IF EXISTS `mig_notification_inbox_20260821`;
DELIMITER //
CREATE PROCEDURE `mig_notification_inbox_20260821`()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND INDEX_NAME = 'idx_notifications_recipient_id'
    ) THEN
        ALTER TABLE `notifications` ADD KEY `idx_notifications_recipient_id` (`recipient_id`,`id`);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' AND INDEX_NAME = 'idx_notifications_recipient_type_id'
    ) THEN
        ALTER TABLE `notifications` ADD KEY `idx_notifications_recipient_type_id` (`recipient_id`,`type`,`id`);
    END IF;
END //
DELIMITER ;

CALL `mig_notification_inbox_20260821`();
DROP PROCEDURE IF EXISTS `mig_notification_inbox_20260821`;
