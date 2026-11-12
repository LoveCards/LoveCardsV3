-- good 是迁移前的点赞历史表；非空实例必须另行审核旧数据去向。
-- 不从 good 重新补写 likes，以免恢复用户已取消的点赞。
-- @lc-requires-upgrade-contract
-- @lc-phase preflight
-- @lc-phase expand
-- @lc-phase backfill
-- @lc-phase verify
SET @lc_good_table = (SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'good'
    AND TABLE_TYPE = 'BASE TABLE' AND ENGINE = 'InnoDB');
SET @lc_good_columns = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'good');
SET @lc_good_shape = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'good' AND (
    (ORDINAL_POSITION = 1 AND COLUMN_NAME = 'id' AND DATA_TYPE = 'int'
      AND IS_NULLABLE = 'NO' AND EXTRA LIKE '%auto_increment%')
    OR (ORDINAL_POSITION = 2 AND COLUMN_NAME = 'aid' AND DATA_TYPE = 'int' AND IS_NULLABLE = 'NO')
    OR (ORDINAL_POSITION = 3 AND COLUMN_NAME = 'pid' AND DATA_TYPE = 'int' AND IS_NULLABLE = 'NO')
    OR (ORDINAL_POSITION = 4 AND COLUMN_NAME = 'uid' AND DATA_TYPE = 'int' AND IS_NULLABLE = 'NO')
    OR (ORDINAL_POSITION = 5 AND COLUMN_NAME = 'ip' AND DATA_TYPE = 'varchar'
      AND CHARACTER_MAXIMUM_LENGTH = 32 AND IS_NULLABLE = 'NO')
    OR (ORDINAL_POSITION = 6 AND COLUMN_NAME = 'created_at' AND DATA_TYPE = 'timestamp'
      AND IS_NULLABLE = 'YES' AND COLUMN_DEFAULT IS NULL)
  ));
SET @lc_good_indexes = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'good');
SET @lc_good_primary = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'good'
    AND INDEX_NAME = 'PRIMARY' AND COLUMN_NAME = 'id' AND SEQ_IN_INDEX = 1 AND NON_UNIQUE = 0);
DROP TEMPORARY TABLE IF EXISTS `lc_good_empty_guard_20261113`;
CREATE TEMPORARY TABLE `lc_good_empty_guard_20261113` (`id` tinyint NOT NULL PRIMARY KEY);
INSERT INTO `lc_good_empty_guard_20261113` (`id`) VALUES (1);
INSERT INTO `lc_good_empty_guard_20261113` (`id`)
SELECT IF(@lc_good_table = 1 AND @lc_good_columns = 6 AND @lc_good_shape = 6
  AND @lc_good_indexes = 1 AND @lc_good_primary = 1
  AND NOT EXISTS (SELECT 1 FROM `good`), 2, 1);
DROP TEMPORARY TABLE `lc_good_empty_guard_20261113`;

-- @lc-phase contract
-- Contract 前重新检查，防止审批窗口内旧表被外部写入。
SET @lc_good_table = (SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'good'
    AND TABLE_TYPE = 'BASE TABLE' AND ENGINE = 'InnoDB');
SET @lc_good_columns = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'good');
SET @lc_good_shape = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'good' AND (
    (ORDINAL_POSITION = 1 AND COLUMN_NAME = 'id' AND DATA_TYPE = 'int'
      AND IS_NULLABLE = 'NO' AND EXTRA LIKE '%auto_increment%')
    OR (ORDINAL_POSITION = 2 AND COLUMN_NAME = 'aid' AND DATA_TYPE = 'int' AND IS_NULLABLE = 'NO')
    OR (ORDINAL_POSITION = 3 AND COLUMN_NAME = 'pid' AND DATA_TYPE = 'int' AND IS_NULLABLE = 'NO')
    OR (ORDINAL_POSITION = 4 AND COLUMN_NAME = 'uid' AND DATA_TYPE = 'int' AND IS_NULLABLE = 'NO')
    OR (ORDINAL_POSITION = 5 AND COLUMN_NAME = 'ip' AND DATA_TYPE = 'varchar'
      AND CHARACTER_MAXIMUM_LENGTH = 32 AND IS_NULLABLE = 'NO')
    OR (ORDINAL_POSITION = 6 AND COLUMN_NAME = 'created_at' AND DATA_TYPE = 'timestamp'
      AND IS_NULLABLE = 'YES' AND COLUMN_DEFAULT IS NULL)
  ));
SET @lc_good_indexes = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'good');
SET @lc_good_primary = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'good'
    AND INDEX_NAME = 'PRIMARY' AND COLUMN_NAME = 'id' AND SEQ_IN_INDEX = 1 AND NON_UNIQUE = 0);
DROP TEMPORARY TABLE IF EXISTS `lc_good_empty_guard_20261113`;
CREATE TEMPORARY TABLE `lc_good_empty_guard_20261113` (`id` tinyint NOT NULL PRIMARY KEY);
INSERT INTO `lc_good_empty_guard_20261113` (`id`) VALUES (1);
INSERT INTO `lc_good_empty_guard_20261113` (`id`)
SELECT IF(@lc_good_table = 1 AND @lc_good_columns = 6 AND @lc_good_shape = 6
  AND @lc_good_indexes = 1 AND @lc_good_primary = 1
  AND NOT EXISTS (SELECT 1 FROM `good`), 2, 1);
DROP TEMPORARY TABLE `lc_good_empty_guard_20261113`;
DROP TABLE `good`;
