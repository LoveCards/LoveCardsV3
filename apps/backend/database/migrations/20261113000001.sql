-- 点赞持久化只保留 ref_type/ref_id；公开 aid/pid 由 Resource 派生。
-- 目标 Release 的前驱须完成 20261112000003 兼容迁移。
-- @lc-requires-upgrade-contract
-- @lc-phase preflight
-- @lc-phase expand
-- @lc-phase backfill
-- @lc-phase verify
-- 此时目标代码尚未激活，兼容前驱仍显式写入一致的旧值。
DROP TEMPORARY TABLE IF EXISTS `lc_likes_legacy_guard_20261113`;
CREATE TEMPORARY TABLE `lc_likes_legacy_guard_20261113` (`id` tinyint NOT NULL PRIMARY KEY);
INSERT INTO `lc_likes_legacy_guard_20261113` (`id`) VALUES (1);
INSERT INTO `lc_likes_legacy_guard_20261113` (`id`)
SELECT IF(EXISTS (
  SELECT 1 FROM `system_upgrade_steps`
  WHERE `migration_id` = '20261112000003' AND `status` = 'complete'
) AND NOT EXISTS (
  SELECT 1 FROM `likes`
  WHERE `ref_type` IS NULL OR `ref_type` NOT IN ('card', 'comment')
     OR `ref_id` IS NULL OR `ref_id` <= 0
     OR `aid` IS NULL OR `pid` IS NULL OR `aid` <> 1 OR `pid` <> `ref_id`
), 2, 1);
DROP TEMPORARY TABLE `lc_likes_legacy_guard_20261113`;

-- @lc-phase contract
-- 审批窗口内可能产生新代码写入的行，其旧列应同时为 NULL。
DROP TEMPORARY TABLE IF EXISTS `lc_likes_legacy_guard_20261113`;
CREATE TEMPORARY TABLE `lc_likes_legacy_guard_20261113` (`id` tinyint NOT NULL PRIMARY KEY);
INSERT INTO `lc_likes_legacy_guard_20261113` (`id`) VALUES (1);
INSERT INTO `lc_likes_legacy_guard_20261113` (`id`)
SELECT IF(NOT EXISTS (
  SELECT 1 FROM `likes`
  WHERE `ref_type` IS NULL OR `ref_type` NOT IN ('card', 'comment')
     OR `ref_id` IS NULL OR `ref_id` <= 0
     OR NOT ((`aid` IS NULL AND `pid` IS NULL)
             OR (COALESCE(`aid`, -1) = 1 AND COALESCE(`pid`, -1) = `ref_id`))
), 2, 1);
DROP TEMPORARY TABLE `lc_likes_legacy_guard_20261113`;
ALTER TABLE `likes` DROP COLUMN `aid`, DROP COLUMN `pid`;
