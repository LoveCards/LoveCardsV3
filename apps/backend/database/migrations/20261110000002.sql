-- Redis TLS 配置默认关闭，现有连接保持明文；管理员显式开启后才使用 TLS。
INSERT INTO `configs` (`group`, `key`, `value`, `type`, `description`, `created_at`, `updated_at`)
SELECT 'redis', source.`key`, source.`value`, source.`type`, source.`description`, NOW(), NOW()
FROM (
  SELECT 'tls' AS `key`, '0' AS `value`, 'bool' AS `type`, '启用 Redis TLS 并验证证书' AS `description`
  UNION ALL SELECT 'tls_server_name', '', 'string', 'Redis TLS 证书名称；留空使用主机地址'
) AS source
LEFT JOIN `configs` existing ON existing.`group` = 'redis' AND existing.`key` = source.`key`
WHERE existing.`id` IS NULL;
