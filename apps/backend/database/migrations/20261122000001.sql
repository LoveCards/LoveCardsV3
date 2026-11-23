-- 文件引用顺序显式入库；已有引用保留 position=0，并用 id 维持历史顺序。
-- @lc-phase expand
SET @lc_has_file_reference_position = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'file_references' AND COLUMN_NAME = 'position');
SET @lc_add_file_reference_position = IF(@lc_has_file_reference_position = 0,
    'ALTER TABLE `file_references` ADD COLUMN `position` int(10) unsigned NOT NULL DEFAULT 0 AFTER `purpose`',
    'SELECT 1');
PREPARE lc_file_reference_position_stmt FROM @lc_add_file_reference_position;
EXECUTE lc_file_reference_position_stmt;
DEALLOCATE PREPARE lc_file_reference_position_stmt;

-- @lc-phase verify
SET @lc_file_reference_position_shape = (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'file_references' AND COLUMN_NAME = 'position'
      AND DATA_TYPE = 'int' AND COLUMN_TYPE LIKE '%unsigned%' AND IS_NULLABLE = 'NO'
      AND COLUMN_DEFAULT = '0');
DROP TEMPORARY TABLE IF EXISTS `lc_file_reference_position_guard`;
CREATE TEMPORARY TABLE `lc_file_reference_position_guard` (`id` tinyint NOT NULL PRIMARY KEY);
INSERT INTO `lc_file_reference_position_guard` (`id`) VALUES (1);
INSERT INTO `lc_file_reference_position_guard` (`id`) VALUES (IF(@lc_file_reference_position_shape = 1, 2, 1));
DROP TEMPORARY TABLE `lc_file_reference_position_guard`;
