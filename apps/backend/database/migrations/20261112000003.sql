-- 点赞旧列在兼容 Release 中先放宽为可空；兼容代码仍写入旧值，读取只依赖 ref_type/ref_id。
-- 此迁移完成的 schema ID 是后续点赞删列 Release 的最低前驱。
-- @lc-phase preflight
-- @lc-phase expand
ALTER TABLE `likes`
  MODIFY COLUMN `aid` int(11) NULL COMMENT '应用ID (legacy)',
  MODIFY COLUMN `pid` int(11) NULL COMMENT '条目ID (legacy)';

-- @lc-phase backfill
-- @lc-phase verify
DROP TEMPORARY TABLE IF EXISTS `lc_likes_nullable_guard_20261112`;
CREATE TEMPORARY TABLE `lc_likes_nullable_guard_20261112` (`id` tinyint NOT NULL PRIMARY KEY);
INSERT INTO `lc_likes_nullable_guard_20261112` (`id`) VALUES (1);
INSERT INTO `lc_likes_nullable_guard_20261112` (`id`)
SELECT IF((SELECT COUNT(*) FROM information_schema.`COLUMNS`
           WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'likes'
             AND `COLUMN_NAME` IN ('aid', 'pid') AND `IS_NULLABLE` = 'YES') = 2, 2, 1);
DROP TEMPORARY TABLE `lc_likes_nullable_guard_20261112`;
