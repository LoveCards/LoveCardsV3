-- 通信运行时（Phase 2）：一次性 stream ticket 存储（契约 §4.4）。
-- 只增不改：新表旧代码无感；回滚=只停写。ticket 明文永不落库，仅存 sha256。

CREATE TABLE IF NOT EXISTS `stream_tickets` (
  `ticket_hash` CHAR(64) NOT NULL,
  `uid` BIGINT UNSIGNED NOT NULL,
  `expires_at` DATETIME(6) NOT NULL,
  `used_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`ticket_hash`),
  KEY `idx_stream_tickets_uid_created` (`uid`, `created_at`),
  KEY `idx_stream_tickets_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
