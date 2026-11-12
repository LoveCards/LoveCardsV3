-- 退役评论级旧置顶标记；cards.pinned_comment_id 是当前真源。
-- 不根据旧 is_top 值重新设置已变更的置顶关系。
-- @lc-requires-upgrade-contract
-- @lc-phase preflight
-- @lc-phase expand
-- @lc-phase backfill
-- @lc-phase verify
DROP TEMPORARY TABLE IF EXISTS `lc_comment_pin_guard`;
CREATE TEMPORARY TABLE `lc_comment_pin_guard` (`id` tinyint NOT NULL PRIMARY KEY);
INSERT INTO `lc_comment_pin_guard` (`id`) VALUES (1);
INSERT INTO `lc_comment_pin_guard` (`id`)
SELECT IF(EXISTS (
    SELECT 1 FROM `cards` card
    LEFT JOIN `comments` comment ON comment.`id` = card.`pinned_comment_id`
    WHERE card.`pinned_comment_id` IS NOT NULL
      AND (card.`deleted_at` IS NOT NULL
        OR comment.`id` IS NULL
        OR comment.`aid` <> 1
        OR comment.`pid` <> card.`id`
        OR COALESCE(comment.`parent_id`, -1) <> 0
        OR comment.`status` <> 0
        OR comment.`deleted_at` IS NOT NULL)
), 1, 2);
-- 只允许删除历史评论表中的非空 int(11) 默认 0 标记。
INSERT INTO `lc_comment_pin_guard` (`id`)
SELECT IF((SELECT COUNT(*) FROM information_schema.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'comments'
      AND `COLUMN_NAME` = 'is_top' AND `DATA_TYPE` = 'int'
      AND `IS_NULLABLE` = 'NO' AND `COLUMN_DEFAULT` = '0') = 1, 3, 1);
DROP TEMPORARY TABLE `lc_comment_pin_guard`;

-- @lc-phase contract
DROP TEMPORARY TABLE IF EXISTS `lc_comment_pin_guard`;
CREATE TEMPORARY TABLE `lc_comment_pin_guard` (`id` tinyint NOT NULL PRIMARY KEY);
INSERT INTO `lc_comment_pin_guard` (`id`) VALUES (1);
INSERT INTO `lc_comment_pin_guard` (`id`)
SELECT IF(EXISTS (
    SELECT 1 FROM `cards` card
    LEFT JOIN `comments` comment ON comment.`id` = card.`pinned_comment_id`
    WHERE card.`pinned_comment_id` IS NOT NULL
      AND (card.`deleted_at` IS NOT NULL
        OR comment.`id` IS NULL
        OR comment.`aid` <> 1
        OR comment.`pid` <> card.`id`
        OR COALESCE(comment.`parent_id`, -1) <> 0
        OR comment.`status` <> 0
        OR comment.`deleted_at` IS NOT NULL)
), 1, 2);
INSERT INTO `lc_comment_pin_guard` (`id`)
SELECT IF((SELECT COUNT(*) FROM information_schema.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'comments'
      AND `COLUMN_NAME` = 'is_top' AND `DATA_TYPE` = 'int'
      AND `IS_NULLABLE` = 'NO' AND `COLUMN_DEFAULT` = '0') = 1, 3, 1);
DROP TEMPORARY TABLE `lc_comment_pin_guard`;
ALTER TABLE `comments` DROP COLUMN `is_top`;
