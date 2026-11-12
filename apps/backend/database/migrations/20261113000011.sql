-- 收件规则目前固定由 Handler 决定；仅允许全部为 handler 的旧列退役。
-- 管理 API 的 recipient_rule 仍由 Resource 兼容输出。
-- @lc-requires-upgrade-contract
-- @lc-phase preflight
-- @lc-phase expand
-- @lc-phase backfill
-- @lc-phase verify
SET @lc_policy_table = (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notification_policies' AND TABLE_TYPE = 'BASE TABLE');
SET @lc_recipient_count = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notification_policies' AND COLUMN_NAME = 'recipient_rule');
SET @lc_recipient_shape = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notification_policies'
      AND COLUMN_NAME = 'recipient_rule' AND DATA_TYPE = 'varchar' AND IS_NULLABLE = 'NO'
      AND REPLACE(COLUMN_DEFAULT, CHAR(39), '') = 'handler');
DROP TEMPORARY TABLE IF EXISTS `lc_recipient_rule_guard`;
CREATE TEMPORARY TABLE `lc_recipient_rule_guard` (`id` tinyint NOT NULL PRIMARY KEY);
INSERT INTO `lc_recipient_rule_guard` (`id`) VALUES (1);
INSERT INTO `lc_recipient_rule_guard` (`id`) VALUES (IF(
    @lc_policy_table = 1 AND (@lc_recipient_count = 0
      OR (@lc_recipient_count = 1 AND @lc_recipient_shape = 1)), 2, 1));
SET @lc_recipient_check = IF(@lc_recipient_count = 1,
    'INSERT INTO lc_recipient_rule_guard (id) SELECT IF(EXISTS(SELECT 1 FROM notification_policies WHERE BINARY recipient_rule <> BINARY ''handler'' LIMIT 1), 1, 3)',
    'INSERT INTO lc_recipient_rule_guard (id) VALUES (3)');
PREPARE lc_recipient_stmt FROM @lc_recipient_check;
EXECUTE lc_recipient_stmt;
DEALLOCATE PREPARE lc_recipient_stmt;
DROP TEMPORARY TABLE `lc_recipient_rule_guard`;

-- @lc-phase contract
-- 审批后重查，避免将新增自定义规则解释成固定 Handler 规则。
SET @lc_policy_table = (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notification_policies' AND TABLE_TYPE = 'BASE TABLE');
SET @lc_recipient_count = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notification_policies' AND COLUMN_NAME = 'recipient_rule');
SET @lc_recipient_shape = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notification_policies'
      AND COLUMN_NAME = 'recipient_rule' AND DATA_TYPE = 'varchar' AND IS_NULLABLE = 'NO'
      AND REPLACE(COLUMN_DEFAULT, CHAR(39), '') = 'handler');
DROP TEMPORARY TABLE IF EXISTS `lc_recipient_rule_guard`;
CREATE TEMPORARY TABLE `lc_recipient_rule_guard` (`id` tinyint NOT NULL PRIMARY KEY);
INSERT INTO `lc_recipient_rule_guard` (`id`) VALUES (1);
INSERT INTO `lc_recipient_rule_guard` (`id`) VALUES (IF(
    @lc_policy_table = 1 AND (@lc_recipient_count = 0
      OR (@lc_recipient_count = 1 AND @lc_recipient_shape = 1)), 2, 1));
SET @lc_recipient_check = IF(@lc_recipient_count = 1,
    'INSERT INTO lc_recipient_rule_guard (id) SELECT IF(EXISTS(SELECT 1 FROM notification_policies WHERE BINARY recipient_rule <> BINARY ''handler'' LIMIT 1), 1, 3)',
    'INSERT INTO lc_recipient_rule_guard (id) VALUES (3)');
PREPARE lc_recipient_stmt FROM @lc_recipient_check;
EXECUTE lc_recipient_stmt;
DEALLOCATE PREPARE lc_recipient_stmt;
DROP TEMPORARY TABLE `lc_recipient_rule_guard`;
SET @lc_recipient_drop = IF(@lc_recipient_count = 1,
    'ALTER TABLE `notification_policies` DROP COLUMN `recipient_rule`', 'SELECT 1');
PREPARE lc_recipient_drop_stmt FROM @lc_recipient_drop;
EXECUTE lc_recipient_drop_stmt;
DEALLOCATE PREPARE lc_recipient_drop_stmt;
