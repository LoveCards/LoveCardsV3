-- 退役旧角色 JSON；当前角色成员关系由 user_roles 维护。
-- 旧 JSON 可能滞后于后续撤权，不得向 user_roles 回灌。
-- @lc-requires-upgrade-contract
-- @lc-phase preflight
-- @lc-phase expand
-- @lc-phase backfill
-- @lc-phase verify
DROP TEMPORARY TABLE IF EXISTS `lc_user_roles_guard`;
CREATE TEMPORARY TABLE `lc_user_roles_guard` (`id` tinyint NOT NULL PRIMARY KEY);
INSERT INTO `lc_user_roles_guard` (`id`) VALUES (1);
INSERT INTO `lc_user_roles_guard` (`id`)
SELECT IF(
    (SELECT COUNT(*) FROM information_schema.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'user_roles' AND `TABLE_TYPE` = 'BASE TABLE') = 1
    AND (SELECT COUNT(*) FROM information_schema.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'user_roles') = 3
    AND (SELECT COUNT(*) FROM information_schema.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'user_roles' AND (
        (`COLUMN_NAME` IN ('user_id', 'role_id') AND `DATA_TYPE` = 'int' AND `IS_NULLABLE` = 'NO')
        OR (`COLUMN_NAME` = 'created_at' AND `DATA_TYPE` = 'datetime' AND `IS_NULLABLE` = 'NO')
      )) = 3
    AND (SELECT COUNT(*) FROM information_schema.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'user_roles' AND `INDEX_NAME` = 'PRIMARY'
      AND `NON_UNIQUE` = 0 AND ((`COLUMN_NAME` = 'user_id' AND `SEQ_IN_INDEX` = 1)
        OR (`COLUMN_NAME` = 'role_id' AND `SEQ_IN_INDEX` = 2))) = 2
    AND (SELECT COUNT(*) FROM information_schema.`KEY_COLUMN_USAGE` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'user_roles' AND (
        (`COLUMN_NAME` = 'user_id' AND `REFERENCED_TABLE_NAME` = 'users' AND `REFERENCED_COLUMN_NAME` = 'id')
        OR (`COLUMN_NAME` = 'role_id' AND `REFERENCED_TABLE_NAME` = 'roles' AND `REFERENCED_COLUMN_NAME` = 'id')
      )) = 2
    AND (SELECT COUNT(*) FROM information_schema.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'users' AND `COLUMN_NAME` = 'roles_id'
      AND `DATA_TYPE` IN ('json', 'longtext') AND `IS_NULLABLE` = 'YES') = 1,
    2, 1
);
INSERT INTO `lc_user_roles_guard` (`id`)
SELECT IF(EXISTS (SELECT 1 FROM `users` WHERE `roles_id` IS NOT NULL
    AND (JSON_VALID(`roles_id`) = 0 OR JSON_TYPE(`roles_id`) <> 'ARRAY')), 1, 3);
-- 旧 JSON 可能滞后于撤权；出现差异时先人工核验，不自动回灌或删除证据。
INSERT INTO `lc_user_roles_guard` (`id`)
SELECT IF(EXISTS (
    SELECT 1 FROM `users` legacy_user
    WHERE legacy_user.`roles_id` IS NOT NULL
      AND JSON_LENGTH(legacy_user.`roles_id`) <> (
          SELECT COUNT(*) FROM `user_roles` current_role
          WHERE current_role.`user_id` = legacy_user.`id`
            AND JSON_CONTAINS(legacy_user.`roles_id`, CAST(current_role.`role_id` AS CHAR)) = 1
      )
), 1, 4);
DROP TEMPORARY TABLE `lc_user_roles_guard`;

-- @lc-phase contract
DROP TEMPORARY TABLE IF EXISTS `lc_user_roles_guard`;
CREATE TEMPORARY TABLE `lc_user_roles_guard` (`id` tinyint NOT NULL PRIMARY KEY);
INSERT INTO `lc_user_roles_guard` (`id`) VALUES (1);
INSERT INTO `lc_user_roles_guard` (`id`)
SELECT IF(
    (SELECT COUNT(*) FROM information_schema.`TABLES` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'user_roles' AND `TABLE_TYPE` = 'BASE TABLE') = 1
    AND (SELECT COUNT(*) FROM information_schema.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'user_roles') = 3
    AND (SELECT COUNT(*) FROM information_schema.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'user_roles' AND (
        (`COLUMN_NAME` IN ('user_id', 'role_id') AND `DATA_TYPE` = 'int' AND `IS_NULLABLE` = 'NO')
        OR (`COLUMN_NAME` = 'created_at' AND `DATA_TYPE` = 'datetime' AND `IS_NULLABLE` = 'NO')
      )) = 3
    AND (SELECT COUNT(*) FROM information_schema.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'user_roles' AND `INDEX_NAME` = 'PRIMARY'
      AND `NON_UNIQUE` = 0 AND ((`COLUMN_NAME` = 'user_id' AND `SEQ_IN_INDEX` = 1)
        OR (`COLUMN_NAME` = 'role_id' AND `SEQ_IN_INDEX` = 2))) = 2
    AND (SELECT COUNT(*) FROM information_schema.`KEY_COLUMN_USAGE` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'user_roles' AND (
        (`COLUMN_NAME` = 'user_id' AND `REFERENCED_TABLE_NAME` = 'users' AND `REFERENCED_COLUMN_NAME` = 'id')
        OR (`COLUMN_NAME` = 'role_id' AND `REFERENCED_TABLE_NAME` = 'roles' AND `REFERENCED_COLUMN_NAME` = 'id')
      )) = 2
    AND (SELECT COUNT(*) FROM information_schema.`COLUMNS` WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'users' AND `COLUMN_NAME` = 'roles_id'
      AND `DATA_TYPE` IN ('json', 'longtext') AND `IS_NULLABLE` = 'YES') = 1,
    2, 1
);
INSERT INTO `lc_user_roles_guard` (`id`)
SELECT IF(EXISTS (SELECT 1 FROM `users` WHERE `roles_id` IS NOT NULL
    AND (JSON_VALID(`roles_id`) = 0 OR JSON_TYPE(`roles_id`) <> 'ARRAY')), 1, 3);
INSERT INTO `lc_user_roles_guard` (`id`)
SELECT IF(EXISTS (
    SELECT 1 FROM `users` legacy_user
    WHERE legacy_user.`roles_id` IS NOT NULL
      AND JSON_LENGTH(legacy_user.`roles_id`) <> (
          SELECT COUNT(*) FROM `user_roles` current_role
          WHERE current_role.`user_id` = legacy_user.`id`
            AND JSON_CONTAINS(legacy_user.`roles_id`, CAST(current_role.`role_id` AS CHAR)) = 1
      )
), 1, 4);
DROP TEMPORARY TABLE `lc_user_roles_guard`;
ALTER TABLE `users` DROP COLUMN `roles_id`;
