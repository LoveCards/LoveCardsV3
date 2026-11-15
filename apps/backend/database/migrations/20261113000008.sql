-- 旧 images 关联没有受审的 Files 映射；只允许空表退役。
-- 非空实例必须先另行审定逐行转换，不能把 URL 猜成文件身份。
-- @lc-requires-upgrade-contract
-- @lc-phase preflight
-- @lc-phase expand
-- @lc-phase backfill
-- @lc-phase verify
DROP TEMPORARY TABLE IF EXISTS `lc_retire_images_guard`;
CREATE TEMPORARY TABLE `lc_retire_images_guard` (`id` tinyint NOT NULL PRIMARY KEY);
INSERT INTO `lc_retire_images_guard` (`id`) VALUES (1);
SET @lc_images_exists = (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'images'
);
SET @lc_images_engine = (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'images'
      AND TABLE_TYPE = 'BASE TABLE' AND ENGINE = 'InnoDB'
);
SET @lc_images_shape = (
    SELECT IF(COUNT(*) = 8 AND SUM(CASE
        WHEN ORDINAL_POSITION = 1 AND COLUMN_NAME = 'id' AND DATA_TYPE = 'int'
          AND IS_NULLABLE = 'NO' AND EXTRA LIKE '%auto_increment%' THEN 1
        WHEN ORDINAL_POSITION = 2 AND COLUMN_NAME = 'aid'
          AND DATA_TYPE = 'int' AND IS_NULLABLE = 'NO' THEN 1
        WHEN ORDINAL_POSITION = 3 AND COLUMN_NAME = 'pid'
          AND DATA_TYPE = 'int' AND IS_NULLABLE = 'NO' THEN 1
        WHEN ORDINAL_POSITION = 4 AND COLUMN_NAME = 'user_id'
          AND DATA_TYPE = 'int' AND IS_NULLABLE = 'NO' THEN 1
        WHEN ORDINAL_POSITION = 5 AND COLUMN_NAME = 'url' AND DATA_TYPE = 'varchar'
          AND CHARACTER_MAXIMUM_LENGTH = 256 AND IS_NULLABLE = 'NO' THEN 1
        WHEN ORDINAL_POSITION = 6 AND COLUMN_NAME = 'created_at' AND DATA_TYPE = 'datetime'
          AND IS_NULLABLE = 'NO' THEN 1
        WHEN ORDINAL_POSITION = 7 AND COLUMN_NAME = 'updated_at'
          AND DATA_TYPE = 'datetime' AND IS_NULLABLE = 'YES' AND COLUMN_DEFAULT IS NULL THEN 1
        WHEN ORDINAL_POSITION = 8 AND COLUMN_NAME = 'deleted_at'
          AND DATA_TYPE = 'datetime' AND IS_NULLABLE = 'YES' AND COLUMN_DEFAULT IS NULL THEN 1
        ELSE 0 END) = 8, 1, 0)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'images'
);
SET @lc_images_indexes = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'images'
);
SET @lc_images_primary = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'images'
      AND INDEX_NAME = 'PRIMARY' AND COLUMN_NAME = 'id'
      AND SEQ_IN_INDEX = 1 AND NON_UNIQUE = 0
);
SET @lc_images_check = IF(@lc_images_exists = 1 AND @lc_images_engine = 1
    AND @lc_images_shape = 1
    AND @lc_images_indexes = 1 AND @lc_images_primary = 1,
    'INSERT INTO lc_retire_images_guard (id) SELECT IF(EXISTS(SELECT 1 FROM images LIMIT 1), 1, 2)',
    IF(@lc_images_exists = 0,
        'INSERT INTO lc_retire_images_guard (id) VALUES (2)',
        'INSERT INTO lc_retire_images_guard (id) VALUES (1)'));
PREPARE lc_images_stmt FROM @lc_images_check;
EXECUTE lc_images_stmt;
DEALLOCATE PREPARE lc_images_stmt;
DROP TEMPORARY TABLE `lc_retire_images_guard`;

-- @lc-phase contract
-- 审批之后再查一次，阻断审批窗口内新增的未知历史数据。
DROP TEMPORARY TABLE IF EXISTS `lc_retire_images_guard`;
CREATE TEMPORARY TABLE `lc_retire_images_guard` (`id` tinyint NOT NULL PRIMARY KEY);
INSERT INTO `lc_retire_images_guard` (`id`) VALUES (1);
SET @lc_images_exists = (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'images'
);
SET @lc_images_engine = (
    SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'images'
      AND TABLE_TYPE = 'BASE TABLE' AND ENGINE = 'InnoDB'
);
SET @lc_images_shape = (
    SELECT IF(COUNT(*) = 8 AND SUM(CASE
        WHEN ORDINAL_POSITION = 1 AND COLUMN_NAME = 'id' AND DATA_TYPE = 'int'
          AND IS_NULLABLE = 'NO' AND EXTRA LIKE '%auto_increment%' THEN 1
        WHEN ORDINAL_POSITION = 2 AND COLUMN_NAME = 'aid'
          AND DATA_TYPE = 'int' AND IS_NULLABLE = 'NO' THEN 1
        WHEN ORDINAL_POSITION = 3 AND COLUMN_NAME = 'pid'
          AND DATA_TYPE = 'int' AND IS_NULLABLE = 'NO' THEN 1
        WHEN ORDINAL_POSITION = 4 AND COLUMN_NAME = 'user_id'
          AND DATA_TYPE = 'int' AND IS_NULLABLE = 'NO' THEN 1
        WHEN ORDINAL_POSITION = 5 AND COLUMN_NAME = 'url' AND DATA_TYPE = 'varchar'
          AND CHARACTER_MAXIMUM_LENGTH = 256 AND IS_NULLABLE = 'NO' THEN 1
        WHEN ORDINAL_POSITION = 6 AND COLUMN_NAME = 'created_at' AND DATA_TYPE = 'datetime'
          AND IS_NULLABLE = 'NO' THEN 1
        WHEN ORDINAL_POSITION = 7 AND COLUMN_NAME = 'updated_at'
          AND DATA_TYPE = 'datetime' AND IS_NULLABLE = 'YES' AND COLUMN_DEFAULT IS NULL THEN 1
        WHEN ORDINAL_POSITION = 8 AND COLUMN_NAME = 'deleted_at'
          AND DATA_TYPE = 'datetime' AND IS_NULLABLE = 'YES' AND COLUMN_DEFAULT IS NULL THEN 1
        ELSE 0 END) = 8, 1, 0)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'images'
);
SET @lc_images_indexes = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'images'
);
SET @lc_images_primary = (
    SELECT COUNT(*) FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'images'
      AND INDEX_NAME = 'PRIMARY' AND COLUMN_NAME = 'id'
      AND SEQ_IN_INDEX = 1 AND NON_UNIQUE = 0
);
SET @lc_images_check = IF(@lc_images_exists = 1 AND @lc_images_engine = 1
    AND @lc_images_shape = 1
    AND @lc_images_indexes = 1 AND @lc_images_primary = 1,
    'INSERT INTO lc_retire_images_guard (id) SELECT IF(EXISTS(SELECT 1 FROM images LIMIT 1), 1, 2)',
    IF(@lc_images_exists = 0,
        'INSERT INTO lc_retire_images_guard (id) VALUES (2)',
        'INSERT INTO lc_retire_images_guard (id) VALUES (1)'));
PREPARE lc_images_stmt FROM @lc_images_check;
EXECUTE lc_images_stmt;
DEALLOCATE PREPARE lc_images_stmt;
DROP TEMPORARY TABLE `lc_retire_images_guard`;
DROP TABLE IF EXISTS `images`;
