-- Ensure the minimum FPM + MySQL profile has a real local storage channel.
-- Existing configured channels are preserved; every insert is additive and idempotent.
-- @lc-phase expand

INSERT INTO `configs` (`group`, `key`, `value`, `type`, `description`, `created_at`, `updated_at`)
SELECT 'storage', 'default', 'local', 'string', '默认存储渠道', NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `configs` WHERE `group` = 'storage' AND `key` = 'default'
);

INSERT INTO `configs` (`group`, `key`, `value`, `type`, `description`, `created_at`, `updated_at`)
SELECT 'storage_local', source.`key`, source.`value`, source.`type`, source.`description`, NOW(), NOW()
FROM (
  SELECT 'root' AS `key`, 'public/storage' AS `value`, 'string' AS `type`, '本地存储根目录' AS `description`
  UNION ALL SELECT 'url_prefix', '/storage', 'string', '本地存储 URL 前缀'
  UNION ALL SELECT 'allow_mime_types', 'image/jpeg,image/png,image/gif,image/webp,application/pdf', 'string', '允许的 MIME 类型'
  UNION ALL SELECT 'max_file_size', '10485760', 'int', '单文件最大字节数'
  UNION ALL SELECT 'path_template', '{date}/{uuid}.{ext}', 'string', '对象路径模板'
) AS source
LEFT JOIN `configs` existing
  ON existing.`group` = 'storage_local' AND existing.`key` = source.`key`
WHERE existing.`id` IS NULL;
