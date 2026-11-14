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
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'images' AND TABLE_TYPE = 'BASE TABLE'
);
SET @lc_images_shape = (
    SELECT IF(COUNT(*) = 8 AND SUM(CASE
        WHEN COLUMN_NAME = 'id' AND DATA_TYPE = 'int' THEN 1
        WHEN COLUMN_NAME IN ('aid', 'pid', 'user_id') AND DATA_TYPE = 'int' THEN 1
        WHEN COLUMN_NAME = 'url' AND DATA_TYPE = 'varchar' THEN 1
        WHEN COLUMN_NAME IN ('created_at', 'updated_at', 'deleted_at') AND DATA_TYPE = 'datetime' THEN 1
        ELSE 0 END) = 8, 1, 0)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'images'
);
SET @lc_images_check = IF(@lc_images_exists = 1 AND @lc_images_shape = 1,
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
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'images' AND TABLE_TYPE = 'BASE TABLE'
);
SET @lc_images_shape = (
    SELECT IF(COUNT(*) = 8 AND SUM(CASE
        WHEN COLUMN_NAME = 'id' AND DATA_TYPE = 'int' THEN 1
        WHEN COLUMN_NAME IN ('aid', 'pid', 'user_id') AND DATA_TYPE = 'int' THEN 1
        WHEN COLUMN_NAME = 'url' AND DATA_TYPE = 'varchar' THEN 1
        WHEN COLUMN_NAME IN ('created_at', 'updated_at', 'deleted_at') AND DATA_TYPE = 'datetime' THEN 1
        ELSE 0 END) = 8, 1, 0)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'images'
);
SET @lc_images_check = IF(@lc_images_exists = 1 AND @lc_images_shape = 1,
    'INSERT INTO lc_retire_images_guard (id) SELECT IF(EXISTS(SELECT 1 FROM images LIMIT 1), 1, 2)',
    IF(@lc_images_exists = 0,
        'INSERT INTO lc_retire_images_guard (id) VALUES (2)',
        'INSERT INTO lc_retire_images_guard (id) VALUES (1)'));
PREPARE lc_images_stmt FROM @lc_images_check;
EXECUTE lc_images_stmt;
DEALLOCATE PREPARE lc_images_stmt;
DROP TEMPORARY TABLE `lc_retire_images_guard`;
DROP TABLE IF EXISTS `images`;
