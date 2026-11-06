-- 通信运行时（Phase 3）：通知策略表 + 版本快照 + event_deliveries 冻结列（契约 §5.1/§5.2）。
-- expand/backfill/verify/complete 四阶段；回滚=只停写不 DROP。

-- @lc-phase expand
CREATE TABLE IF NOT EXISTS `notification_policies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_type` VARCHAR(128) NOT NULL,
  `event_version` SMALLINT UNSIGNED NOT NULL,
  `rule_key` VARCHAR(128) NOT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `notification_type` VARCHAR(64) NULL,
  `recipient_rule` VARCHAR(64) NOT NULL DEFAULT 'handler',
  `user_disableable` TINYINT(1) NOT NULL DEFAULT 1,
  `locked` TINYINT(1) NOT NULL DEFAULT 0,
  `policy_revision` INT UNSIGNED NOT NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_np_rule` (`event_type`, `event_version`, `rule_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `notification_policy_versions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `policy_revision` INT UNSIGNED NOT NULL,
  `snapshot_json` LONGTEXT NOT NULL,
  `source` VARCHAR(16) NOT NULL,
  `reason` VARCHAR(255) NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_np_rev` (`policy_revision`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP PROCEDURE IF EXISTS `mig_deliveries_policy_cols_20261106`;
DELIMITER //
CREATE PROCEDURE `mig_deliveries_policy_cols_20261106`()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_deliveries' AND COLUMN_NAME = 'policy_revision'
    ) THEN
        ALTER TABLE `event_deliveries`
            ADD COLUMN `policy_revision` INT UNSIGNED NULL AFTER `execution_mode`;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_deliveries' AND COLUMN_NAME = 'policy_decision'
    ) THEN
        ALTER TABLE `event_deliveries`
            ADD COLUMN `policy_decision` VARCHAR(24) NULL AFTER `policy_revision`;
    END IF;
END //
DELIMITER ;
CALL `mig_deliveries_policy_cols_20261106`();
DROP PROCEDURE IF EXISTS `mig_deliveries_policy_cols_20261106`;

-- @lc-phase backfill
-- 种子 revision=1：六类互动事件可关；system.notification.issued（安全/审核/账号投影）锁定不可关。
INSERT INTO `notification_policy_versions` (`policy_revision`, `snapshot_json`, `source`, `reason`, `created_by`, `created_at`)
SELECT 1,
  '{"comment.published":{"enabled":true,"locked":false,"user_disableable":true,"notification_type":"comment.reply"},"content.liked":{"enabled":true,"locked":false,"user_disableable":true,"notification_type":"content.card_liked"},"content.saved":{"enabled":true,"locked":false,"user_disableable":true,"notification_type":"library.saved"},"mentions.added":{"enabled":true,"locked":false,"user_disableable":true,"notification_type":"content.mentioned"},"message.sent":{"enabled":true,"locked":false,"user_disableable":true,"notification_type":"message"},"social.followed":{"enabled":true,"locked":false,"user_disableable":true,"notification_type":"social.followed"},"system.notification.issued":{"enabled":true,"locked":true,"user_disableable":false,"notification_type":"system.*"}}',
  'seed', '通信运行时 Phase 3 初始策略目录', NULL, UTC_TIMESTAMP(6)
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `notification_policy_versions` WHERE `policy_revision` = 1);

INSERT INTO `notification_policies` (`event_type`, `event_version`, `rule_key`, `enabled`, `notification_type`, `recipient_rule`, `user_disableable`, `locked`, `policy_revision`, `updated_by`, `created_at`, `updated_at`)
SELECT seed.event_type, 1, 'notify', seed.enabled, seed.notification_type, 'handler', seed.user_disableable, seed.locked, 1, NULL, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
FROM (
  SELECT 'comment.published' AS event_type, 1 AS enabled, 'comment.reply' AS notification_type, 1 AS user_disableable, 0 AS locked
  UNION ALL SELECT 'content.liked', 1, 'content.card_liked', 1, 0
  UNION ALL SELECT 'content.saved', 1, 'library.saved', 1, 0
  UNION ALL SELECT 'mentions.added', 1, 'content.mentioned', 1, 0
  UNION ALL SELECT 'message.sent', 1, 'message', 1, 0
  UNION ALL SELECT 'social.followed', 1, 'social.followed', 1, 0
  UNION ALL SELECT 'system.notification.issued', 1, 'system.moderation', 0, 1
) AS seed
LEFT JOIN `notification_policies` AS existing
  ON existing.event_type = seed.event_type AND existing.event_version = 1 AND existing.rule_key = 'notify'
WHERE existing.event_type IS NULL;

-- @lc-phase verify
-- 历史在途投递不回填策略版本：policy_revision NULL 即 not-configured（照旧执行，绝不丢通知）。
SELECT COUNT(*) FROM `notification_policy_versions` WHERE `policy_revision` = 1;

-- @lc-phase complete
SELECT COUNT(*) FROM `notification_policies` WHERE `policy_revision` = 1;
