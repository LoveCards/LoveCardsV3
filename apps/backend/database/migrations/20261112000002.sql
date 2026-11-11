-- S11 校验过程清理：在独立受控 Contract 中完成。
-- 缺失视为已清理；同名但非本迁移所有的过程必须阻断。
-- @lc-requires-upgrade-contract
-- @lc-phase preflight
-- @lc-phase expand
-- @lc-phase backfill
-- @lc-phase verify
-- @lc-phase contract
DROP TEMPORARY TABLE IF EXISTS `lc_s12_owner_guard`;
CREATE TEMPORARY TABLE `lc_s12_owner_guard` (`id` tinyint NOT NULL PRIMARY KEY);
INSERT INTO `lc_s12_owner_guard` (`id`) VALUES (1);
INSERT INTO `lc_s12_owner_guard` (`id`)
SELECT IF(EXISTS (
    SELECT 1 FROM information_schema.ROUTINES
    WHERE `ROUTINE_SCHEMA` = DATABASE()
      AND `ROUTINE_NAME` = 'lc_s11_assert_legacy_seed'
      AND `ROUTINE_TYPE` = 'PROCEDURE'
      AND NOT (BINARY `ROUTINE_COMMENT` = BINARY 'LoveCards:20261112000001:legacy-seed-v1')
), 1, 2);
DROP TEMPORARY TABLE `lc_s12_owner_guard`;
SET @lc_s12_cleanup = IF(EXISTS (
    SELECT 1 FROM information_schema.ROUTINES
    WHERE `ROUTINE_SCHEMA` = DATABASE()
      AND `ROUTINE_NAME` = 'lc_s11_assert_legacy_seed'
      AND `ROUTINE_TYPE` = 'PROCEDURE'
      AND BINARY `ROUTINE_COMMENT` = BINARY 'LoveCards:20261112000001:legacy-seed-v1'
), 'DROP PROCEDURE `lc_s11_assert_legacy_seed`', 'SELECT 1');
PREPARE lc_s12_cleanup_stmt FROM @lc_s12_cleanup;
EXECUTE lc_s12_cleanup_stmt;
DEALLOCATE PREPARE lc_s12_cleanup_stmt;
