-- 配置组的内部版本，与现有键值存储共用同一张表；业务读取过滤保留键。
INSERT INTO `configs` (`group`, `key`, `value`, `type`, `description`, `created_at`, `updated_at`)
SELECT groups_to_version.`group`, '__revision', '1', 'int', '内部配置组版本', UTC_TIMESTAMP(), UTC_TIMESTAMP()
FROM (SELECT DISTINCT `group` FROM `configs` WHERE `key` <> '__revision') AS groups_to_version
WHERE NOT EXISTS (
  SELECT 1 FROM `configs` AS existing_revision
  WHERE existing_revision.`group` = groups_to_version.`group` AND existing_revision.`key` = '__revision'
);
