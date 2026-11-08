-- 通信运行时控制面（Phase 1）：单例配置状态表 + Scheduler 运行台账。
-- 只增不改：既有表零 ALTER；旧代码不读取这两张新表，回滚=只停写。
-- 契约冻结见 docs/engineering/COMMUNICATION-RUNTIME-CONTRACT-2026-11-06.md §2。

CREATE TABLE IF NOT EXISTS `runtime_config_state` (
  `id` TINYINT UNSIGNED NOT NULL,
  `desired_mode` VARCHAR(16) NOT NULL DEFAULT 'polling',
  `settings_json` TEXT NOT NULL,
  `revision` INT UNSIGNED NOT NULL DEFAULT 1,
  `last_apply_state` VARCHAR(16) NOT NULL DEFAULT 'applied',
  `last_apply_reason` VARCHAR(64) NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 单例行：id 恒为 1（MySQL 5.7 无 CHECK，由代码保证只 UPDATE 不 INSERT）。
-- 默认目标模式 polling；全部运行参数为契约 §2.2 白名单默认值。
INSERT INTO `runtime_config_state` (`id`, `desired_mode`, `settings_json`, `revision`, `last_apply_state`, `created_at`, `updated_at`)
SELECT 1, 'polling',
  '{"notification_interval_seconds":30,"message_interval_seconds":45,"unread_interval_seconds":20,"polling_backoff_factor":2,"polling_max_interval_seconds":300,"pause_when_hidden":true,"worker_stale_seconds":30,"scheduler_stale_seconds":3600,"gateway_stale_seconds":15,"require_supervisor":false}',
  1, 'applied', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6)
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `runtime_config_state` WHERE `id` = 1);

CREATE TABLE IF NOT EXISTS `scheduler_runs` (
  `run_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `worker_id` VARCHAR(128) NOT NULL,
  `started_at` DATETIME(6) NOT NULL,
  `finished_at` DATETIME(6) NULL,
  `outcome` VARCHAR(16) NOT NULL DEFAULT 'running',
  `jobs_processed` INT UNSIGNED NOT NULL DEFAULT 0,
  `release_id` VARCHAR(128) NOT NULL DEFAULT '',
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`run_id`),
  KEY `idx_scheduler_runs_outcome` (`outcome`,`finished_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
