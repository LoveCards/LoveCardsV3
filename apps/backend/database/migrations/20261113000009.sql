-- id 已是聚簇主键，清理两个历史遗留的同列唯一索引。
-- 若某实例将索引名 id 用于其他结构，校验阶段必须阻断。
-- @lc-requires-upgrade-contract
-- @lc-phase preflight
-- @lc-phase expand
-- @lc-phase backfill
-- @lc-phase verify
SET @lc_library_pk = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_items'
      AND INDEX_NAME = 'PRIMARY' AND COLUMN_NAME = 'id' AND SEQ_IN_INDEX = 1 AND NON_UNIQUE = 0);
SET @lc_library_pk_columns = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_items' AND INDEX_NAME = 'PRIMARY');
SET @lc_library_id_count = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_items' AND INDEX_NAME = 'id');
SET @lc_library_id_valid = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_items'
      AND INDEX_NAME = 'id' AND COLUMN_NAME = 'id' AND SEQ_IN_INDEX = 1
      AND NON_UNIQUE = 0 AND SUB_PART IS NULL);
SET @lc_history_pk = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'view_history'
      AND INDEX_NAME = 'PRIMARY' AND COLUMN_NAME = 'id' AND SEQ_IN_INDEX = 1 AND NON_UNIQUE = 0);
SET @lc_history_pk_columns = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'view_history' AND INDEX_NAME = 'PRIMARY');
SET @lc_history_id_count = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'view_history' AND INDEX_NAME = 'id');
SET @lc_history_id_valid = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'view_history'
      AND INDEX_NAME = 'id' AND COLUMN_NAME = 'id' AND SEQ_IN_INDEX = 1
      AND NON_UNIQUE = 0 AND SUB_PART IS NULL);
DROP TEMPORARY TABLE IF EXISTS `lc_duplicate_id_index_guard`;
CREATE TEMPORARY TABLE `lc_duplicate_id_index_guard` (`id` tinyint NOT NULL PRIMARY KEY);
INSERT INTO `lc_duplicate_id_index_guard` (`id`) VALUES (1);
INSERT INTO `lc_duplicate_id_index_guard` (`id`) VALUES (IF(
    @lc_library_pk = 1 AND @lc_library_pk_columns = 1
    AND @lc_history_pk = 1 AND @lc_history_pk_columns = 1
    AND (@lc_library_id_count = 0 OR (@lc_library_id_count = 1 AND @lc_library_id_valid = 1))
    AND (@lc_history_id_count = 0 OR (@lc_history_id_count = 1 AND @lc_history_id_valid = 1)), 2, 1));
DROP TEMPORARY TABLE `lc_duplicate_id_index_guard`;

-- @lc-phase contract
-- DDL 非事务性；重试时已删除的索引可以跳过，但错误的同名索引不可跳过。
SET @lc_library_pk = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_items'
      AND INDEX_NAME = 'PRIMARY' AND COLUMN_NAME = 'id' AND SEQ_IN_INDEX = 1 AND NON_UNIQUE = 0);
SET @lc_library_pk_columns = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_items' AND INDEX_NAME = 'PRIMARY');
SET @lc_library_id_count = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_items' AND INDEX_NAME = 'id');
SET @lc_library_id_valid = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'library_items'
      AND INDEX_NAME = 'id' AND COLUMN_NAME = 'id' AND SEQ_IN_INDEX = 1
      AND NON_UNIQUE = 0 AND SUB_PART IS NULL);
SET @lc_history_pk = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'view_history'
      AND INDEX_NAME = 'PRIMARY' AND COLUMN_NAME = 'id' AND SEQ_IN_INDEX = 1 AND NON_UNIQUE = 0);
SET @lc_history_pk_columns = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'view_history' AND INDEX_NAME = 'PRIMARY');
SET @lc_history_id_count = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'view_history' AND INDEX_NAME = 'id');
SET @lc_history_id_valid = (SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'view_history'
      AND INDEX_NAME = 'id' AND COLUMN_NAME = 'id' AND SEQ_IN_INDEX = 1
      AND NON_UNIQUE = 0 AND SUB_PART IS NULL);
DROP TEMPORARY TABLE IF EXISTS `lc_duplicate_id_index_guard`;
CREATE TEMPORARY TABLE `lc_duplicate_id_index_guard` (`id` tinyint NOT NULL PRIMARY KEY);
INSERT INTO `lc_duplicate_id_index_guard` (`id`) VALUES (1);
INSERT INTO `lc_duplicate_id_index_guard` (`id`) VALUES (IF(
    @lc_library_pk = 1 AND @lc_library_pk_columns = 1
    AND @lc_history_pk = 1 AND @lc_history_pk_columns = 1
    AND (@lc_library_id_count = 0 OR (@lc_library_id_count = 1 AND @lc_library_id_valid = 1))
    AND (@lc_history_id_count = 0 OR (@lc_history_id_count = 1 AND @lc_history_id_valid = 1)), 2, 1));
DROP TEMPORARY TABLE `lc_duplicate_id_index_guard`;
SET @lc_drop_library_id = IF(@lc_library_id_count = 1,
    'ALTER TABLE `library_items` DROP INDEX `id`', 'SELECT 1');
PREPARE lc_drop_library_stmt FROM @lc_drop_library_id;
EXECUTE lc_drop_library_stmt;
DEALLOCATE PREPARE lc_drop_library_stmt;
SET @lc_drop_history_id = IF(@lc_history_id_count = 1,
    'ALTER TABLE `view_history` DROP INDEX `id`', 'SELECT 1');
PREPARE lc_drop_history_stmt FROM @lc_drop_history_id;
EXECUTE lc_drop_history_stmt;
DEALLOCATE PREPARE lc_drop_history_stmt;
