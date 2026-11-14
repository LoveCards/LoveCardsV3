-- 人工重试保留累计尝试；NULL 沿用 Handler 默认上限，显式值为授权绝对上限。
DROP PROCEDURE IF EXISTS `mig_delivery_retry_limit_20261111`;
DELIMITER //
CREATE PROCEDURE `mig_delivery_retry_limit_20261111`()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_deliveries' AND COLUMN_NAME = 'retry_attempt_limit'
  ) THEN
    ALTER TABLE `event_deliveries`
      ADD COLUMN `retry_attempt_limit` INT UNSIGNED NULL DEFAULT NULL AFTER `attempt_count`;
  END IF;
END //
DELIMITER ;
CALL `mig_delivery_retry_limit_20261111`();
DROP PROCEDURE IF EXISTS `mig_delivery_retry_limit_20261111`;
