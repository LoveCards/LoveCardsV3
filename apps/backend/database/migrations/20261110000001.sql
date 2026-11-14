-- 主动更新信号策略只保存开关覆盖值；代码中的 Handler Registry 是目录权威。
CREATE TABLE IF NOT EXISTS `signal_policy_state` (
  `id` TINYINT UNSIGNED NOT NULL,
  `revision` INT UNSIGNED NOT NULL DEFAULT 1,
  `overrides_json` LONGTEXT NOT NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `signal_policy_state` (`id`, `revision`, `overrides_json`, `created_at`, `updated_at`)
SELECT 1, 1, '{}', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
WHERE NOT EXISTS (SELECT 1 FROM `signal_policy_state` WHERE `id` = 1);

-- 已有实例补齐默认关闭的系统 Redis 配置；保留管理员已有值。
INSERT INTO `configs` (`group`, `key`, `value`, `type`, `description`, `created_at`, `updated_at`)
SELECT 'redis', source.`key`, source.`value`, source.`type`, source.`description`, NOW(), NOW()
FROM (
  SELECT 'enabled' AS `key`, '0' AS `value`, 'bool' AS `type`, '启用 Redis 系统连接' AS `description`
  UNION ALL SELECT 'host', '127.0.0.1', 'string', 'Redis 地址'
  UNION ALL SELECT 'port', '6379', 'int', 'Redis 端口'
  UNION ALL SELECT 'database', '0', 'int', 'Redis 数据库编号'
  UNION ALL SELECT 'password', '', 'password', 'Redis 认证密码'
) AS source
LEFT JOIN `configs` existing ON existing.`group` = 'redis' AND existing.`key` = source.`key`
WHERE existing.`id` IS NULL;

-- Worker 心跳显式声明可领取队列；旧心跳缺少声明时实时门禁拒绝启用。
DROP PROCEDURE IF EXISTS `mig_runtime_worker_queues_20261110`;
DELIMITER //
CREATE PROCEDURE `mig_runtime_worker_queues_20261110`()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'runtime_workers' AND COLUMN_NAME = 'queue_names_json'
    ) THEN
        ALTER TABLE `runtime_workers`
            ADD COLUMN `queue_names_json` VARCHAR(2048) NOT NULL DEFAULT '[]' AFTER `handler_catalog_hash`;
    END IF;
END//
DELIMITER ;
CALL `mig_runtime_worker_queues_20261110`();
DROP PROCEDURE IF EXISTS `mig_runtime_worker_queues_20261110`;
