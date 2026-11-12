-- 退役评论置顶过渡表；当前指针只在 cards.pinned_comment_id。
-- 旧表可能保留已取消或更换的历史置顶，不能回灌当前指针。
-- pinned_by/pinned_at 的历史值只由升级前已校验的数据库备份保存；须经独立 Contract 批准。
-- @lc-requires-upgrade-contract
-- @lc-phase preflight
-- @lc-phase expand
-- @lc-phase backfill
-- @lc-phase verify
DROP TEMPORARY TABLE IF EXISTS `lc_pin_pointer_guard`;
CREATE TEMPORARY TABLE `lc_pin_pointer_guard` (`id` tinyint NOT NULL PRIMARY KEY);
INSERT INTO `lc_pin_pointer_guard` (`id`) VALUES (1);
INSERT INTO `lc_pin_pointer_guard` (`id`)
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
-- 同名旧表必须与历史迁移创建的四列、主键和唯一键一致。
INSERT INTO `lc_pin_pointer_guard` (`id`)
SELECT IF(
    (SELECT COUNT(*) FROM information_schema.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'card_comment_pins' AND `TABLE_TYPE` = 'BASE TABLE') = 1
    AND (SELECT COUNT(*) FROM information_schema.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'card_comment_pins') = 4
    AND (SELECT COUNT(*) FROM information_schema.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'card_comment_pins' AND (
        (`COLUMN_NAME` IN ('card_id', 'comment_id', 'pinned_by') AND `DATA_TYPE` = 'int' AND `IS_NULLABLE` = 'NO')
        OR (`COLUMN_NAME` = 'pinned_at' AND `DATA_TYPE` = 'datetime' AND `IS_NULLABLE` = 'NO')
      )) = 4
    AND (SELECT COUNT(*) FROM information_schema.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'card_comment_pins' AND `COLUMN_NAME` = 'pinned_by' AND `COLUMN_DEFAULT` = '0') = 1
    AND (SELECT COUNT(*) FROM information_schema.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'card_comment_pins') = 2
    AND (SELECT COUNT(*) FROM information_schema.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'card_comment_pins' AND `INDEX_NAME` = 'PRIMARY'
      AND `COLUMN_NAME` = 'card_id' AND `SEQ_IN_INDEX` = 1 AND `NON_UNIQUE` = 0) = 1
    AND (SELECT COUNT(*) FROM information_schema.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'card_comment_pins' AND `INDEX_NAME` = 'uq_card_comment_pins_comment'
      AND `COLUMN_NAME` = 'comment_id' AND `SEQ_IN_INDEX` = 1 AND `NON_UNIQUE` = 0) = 1,
    3, 1
);
DROP TEMPORARY TABLE `lc_pin_pointer_guard`;

-- @lc-phase contract
-- 审批窗口内可能有新操作；真正删除前重新核对当前权威指针。
DROP TEMPORARY TABLE IF EXISTS `lc_pin_pointer_guard`;
CREATE TEMPORARY TABLE `lc_pin_pointer_guard` (`id` tinyint NOT NULL PRIMARY KEY);
INSERT INTO `lc_pin_pointer_guard` (`id`) VALUES (1);
INSERT INTO `lc_pin_pointer_guard` (`id`)
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
INSERT INTO `lc_pin_pointer_guard` (`id`)
SELECT IF(
    (SELECT COUNT(*) FROM information_schema.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'card_comment_pins' AND `TABLE_TYPE` = 'BASE TABLE') = 1
    AND (SELECT COUNT(*) FROM information_schema.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'card_comment_pins') = 4
    AND (SELECT COUNT(*) FROM information_schema.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'card_comment_pins' AND (
        (`COLUMN_NAME` IN ('card_id', 'comment_id', 'pinned_by') AND `DATA_TYPE` = 'int' AND `IS_NULLABLE` = 'NO')
        OR (`COLUMN_NAME` = 'pinned_at' AND `DATA_TYPE` = 'datetime' AND `IS_NULLABLE` = 'NO')
      )) = 4
    AND (SELECT COUNT(*) FROM information_schema.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'card_comment_pins' AND `COLUMN_NAME` = 'pinned_by' AND `COLUMN_DEFAULT` = '0') = 1
    AND (SELECT COUNT(*) FROM information_schema.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'card_comment_pins') = 2
    AND (SELECT COUNT(*) FROM information_schema.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'card_comment_pins' AND `INDEX_NAME` = 'PRIMARY'
      AND `COLUMN_NAME` = 'card_id' AND `SEQ_IN_INDEX` = 1 AND `NON_UNIQUE` = 0) = 1
    AND (SELECT COUNT(*) FROM information_schema.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'card_comment_pins' AND `INDEX_NAME` = 'uq_card_comment_pins_comment'
      AND `COLUMN_NAME` = 'comment_id' AND `SEQ_IN_INDEX` = 1 AND `NON_UNIQUE` = 0) = 1,
    3, 1
);
DROP TEMPORARY TABLE `lc_pin_pointer_guard`;
DROP TABLE `card_comment_pins`;
