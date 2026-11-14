-- 事件消费策略与通信模式分开；现有实时实例保持常驻消费。
CREATE TABLE IF NOT EXISTS `eventing_consumer_state` (
  `id` TINYINT UNSIGNED NOT NULL,
  `mode` VARCHAR(16) NOT NULL,
  `state` VARCHAR(16) NOT NULL DEFAULT 'active',
  `target_mode` VARCHAR(16) NULL,
  `scheduled_trigger` VARCHAR(16) NOT NULL DEFAULT 'none',
  `revision` INT UNSIGNED NOT NULL DEFAULT 1,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `eventing_consumer_state` (`id`, `mode`, `state`, `target_mode`, `scheduled_trigger`, `revision`, `created_at`, `updated_at`)
SELECT 1,
  CASE WHEN `desired_mode` = 'realtime' THEN 'resident' ELSE 'scheduled' END,
  'active', NULL,
  CASE WHEN `desired_mode` = 'realtime' THEN 'none' ELSE 'cli' END,
  1, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
FROM `runtime_config_state`
WHERE `id` = 1 AND NOT EXISTS (SELECT 1 FROM `eventing_consumer_state` WHERE `id` = 1);

DROP PROCEDURE IF EXISTS `mig_scheduler_trigger_source_20261111`;
DELIMITER //
CREATE PROCEDURE `mig_scheduler_trigger_source_20261111`()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'scheduler_runs' AND COLUMN_NAME = 'trigger_source'
  ) THEN
    ALTER TABLE `scheduler_runs`
      ADD COLUMN `trigger_source` VARCHAR(16) NOT NULL DEFAULT 'legacy' AFTER `worker_id`;
  END IF;
END //
DELIMITER ;
CALL `mig_scheduler_trigger_source_20261111`();
DROP PROCEDURE IF EXISTS `mig_scheduler_trigger_source_20261111`;
