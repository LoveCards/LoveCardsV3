-- tags_map.status 没有运行时状态转换；只有全部为默认 0 时可退役。
-- @lc-requires-upgrade-contract
-- @lc-phase preflight
-- @lc-phase expand
-- @lc-phase backfill
-- @lc-phase verify
SET @lc_tags_map_table = (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tags_map' AND TABLE_TYPE = 'BASE TABLE');
SET @lc_tags_map_status_count = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tags_map' AND COLUMN_NAME = 'status');
SET @lc_tags_map_status_shape = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tags_map' AND COLUMN_NAME = 'status'
      AND DATA_TYPE = 'int' AND IS_NULLABLE = 'NO' AND COLUMN_DEFAULT = '0');
DROP TEMPORARY TABLE IF EXISTS `lc_tags_map_status_guard`;
CREATE TEMPORARY TABLE `lc_tags_map_status_guard` (`id` tinyint NOT NULL PRIMARY KEY);
INSERT INTO `lc_tags_map_status_guard` (`id`) VALUES (1);
INSERT INTO `lc_tags_map_status_guard` (`id`) VALUES (IF(
    @lc_tags_map_table = 1 AND (@lc_tags_map_status_count = 0
      OR (@lc_tags_map_status_count = 1 AND @lc_tags_map_status_shape = 1)), 2, 1));
SET @lc_tags_map_check = IF(@lc_tags_map_status_count = 1,
    'INSERT INTO lc_tags_map_status_guard (id) SELECT IF(EXISTS(SELECT 1 FROM tags_map WHERE status <> 0 LIMIT 1), 1, 3)',
    'INSERT INTO lc_tags_map_status_guard (id) VALUES (3)');
PREPARE lc_tags_map_stmt FROM @lc_tags_map_check;
EXECUTE lc_tags_map_stmt;
DEALLOCATE PREPARE lc_tags_map_stmt;
DROP TEMPORARY TABLE `lc_tags_map_status_guard`;

-- @lc-phase contract
-- 审批后再次校验；非零历史状态不得被默认为有效关联。
SET @lc_tags_map_table = (SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tags_map' AND TABLE_TYPE = 'BASE TABLE');
SET @lc_tags_map_status_count = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tags_map' AND COLUMN_NAME = 'status');
SET @lc_tags_map_status_shape = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tags_map' AND COLUMN_NAME = 'status'
      AND DATA_TYPE = 'int' AND IS_NULLABLE = 'NO' AND COLUMN_DEFAULT = '0');
DROP TEMPORARY TABLE IF EXISTS `lc_tags_map_status_guard`;
CREATE TEMPORARY TABLE `lc_tags_map_status_guard` (`id` tinyint NOT NULL PRIMARY KEY);
INSERT INTO `lc_tags_map_status_guard` (`id`) VALUES (1);
INSERT INTO `lc_tags_map_status_guard` (`id`) VALUES (IF(
    @lc_tags_map_table = 1 AND (@lc_tags_map_status_count = 0
      OR (@lc_tags_map_status_count = 1 AND @lc_tags_map_status_shape = 1)), 2, 1));
SET @lc_tags_map_check = IF(@lc_tags_map_status_count = 1,
    'INSERT INTO lc_tags_map_status_guard (id) SELECT IF(EXISTS(SELECT 1 FROM tags_map WHERE status <> 0 LIMIT 1), 1, 3)',
    'INSERT INTO lc_tags_map_status_guard (id) VALUES (3)');
PREPARE lc_tags_map_stmt FROM @lc_tags_map_check;
EXECUTE lc_tags_map_stmt;
DEALLOCATE PREPARE lc_tags_map_stmt;
DROP TEMPORARY TABLE `lc_tags_map_status_guard`;
SET @lc_tags_map_drop = IF(@lc_tags_map_status_count = 1,
    'ALTER TABLE `tags_map` DROP COLUMN `status`', 'SELECT 1');
PREPARE lc_tags_map_drop_stmt FROM @lc_tags_map_drop;
EXECUTE lc_tags_map_drop_stmt;
DEALLOCATE PREPARE lc_tags_map_drop_stmt;
