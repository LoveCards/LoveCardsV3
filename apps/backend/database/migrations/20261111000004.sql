-- 实时票据绑定现有 Auth 会话；旧票据无绑定，校验时拒绝。
DROP PROCEDURE IF EXISTS `mig_stream_session_20261111`;
DELIMITER //
CREATE PROCEDURE `mig_stream_session_20261111`()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stream_tickets' AND COLUMN_NAME = 'session_public_id'
  ) THEN
    ALTER TABLE `stream_tickets` ADD COLUMN `session_public_id` char(32) DEFAULT NULL AFTER `uid`;
  END IF;
END //
DELIMITER ;
CALL `mig_stream_session_20261111`();
DROP PROCEDURE IF EXISTS `mig_stream_session_20261111`;
