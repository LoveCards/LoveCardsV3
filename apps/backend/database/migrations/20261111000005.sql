-- 主动提示按 signal_key 冻结资格；部署须先停止旧消费者。
DROP PROCEDURE IF EXISTS `mig_signal_generation_20261111`;
DELIMITER //
CREATE PROCEDURE `mig_signal_generation_20261111`()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'signal_policy_state' AND COLUMN_NAME = 'generations_json') THEN
    ALTER TABLE `signal_policy_state` ADD COLUMN `generations_json` longtext DEFAULT NULL AFTER `overrides_json`;
  END IF;
  UPDATE `signal_policy_state` SET `generations_json` = '{}' WHERE `generations_json` IS NULL;
  IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'signal_policy_state' AND COLUMN_NAME = 'generations_json' AND IS_NULLABLE = 'YES') THEN
    ALTER TABLE `signal_policy_state` MODIFY COLUMN `generations_json` longtext NOT NULL;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_deliveries' AND COLUMN_NAME = 'signal_key') THEN
    ALTER TABLE `event_deliveries` ADD COLUMN `signal_key` varchar(128) DEFAULT NULL AFTER `policy_revision`;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_deliveries' AND COLUMN_NAME = 'signal_generation') THEN
    ALTER TABLE `event_deliveries` ADD COLUMN `signal_generation` bigint(20) unsigned DEFAULT NULL AFTER `signal_key`;
  END IF;
END //
DELIMITER ;
CALL `mig_signal_generation_20261111`();
DROP PROCEDURE IF EXISTS `mig_signal_generation_20261111`;

-- 旧提示无历史资格，不能在重新开启后追发；业务事实仍通过 API 读取。
UPDATE `event_deliveries` SET `status` = 'completed', `policy_decision` = 'skipped', `claim_token` = NULL, `claimed_by` = NULL, `lease_expires_at` = NULL, `completed_at` = UTC_TIMESTAMP(6), `updated_at` = UTC_TIMESTAMP(6)
WHERE `queue_name` = 'realtime' AND `status` IN ('pending', 'processing', 'dead') AND `signal_generation` IS NULL;
