-- 系统通告发布：冻结收件范围并复用现有通知与事件账本，不自动授予任何角色发送权限。
CREATE TABLE IF NOT EXISTS `system_notice_batches` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `creator_id` int(11) NOT NULL,
  `request_key` varchar(64) NOT NULL,
  `request_hash` char(64) NOT NULL,
  `audience` varchar(16) NOT NULL,
  `message` text NOT NULL,
  `action_url` varchar(2048) DEFAULT NULL,
  `severity` varchar(16) NOT NULL,
  `selected_recipients` JSON NOT NULL,
  `recipient_count` int(10) unsigned NOT NULL,
  `dispatch_cursor` int(10) unsigned NOT NULL DEFAULT 0,
  `dispatch_complete` tinyint(1) unsigned NOT NULL DEFAULT 0,
  `dispatch_generation` int(10) unsigned NOT NULL DEFAULT 0,
  `dispatch_event_id` bigint(20) unsigned DEFAULT NULL,
  `dispatch_delivery_id` bigint(20) unsigned DEFAULT NULL,
  `retry_reason` varchar(255) DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_system_notice_request` (`creator_id`,`request_key`),
  KEY `idx_system_notice_creator` (`creator_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `system_notice_recipients` (
  `batch_id` bigint(20) unsigned NOT NULL,
  `user_id` int(11) NOT NULL,
  `issued_event_id` bigint(20) unsigned DEFAULT NULL,
  `delivery_id` bigint(20) unsigned DEFAULT NULL,
  `notification_id` bigint(20) unsigned DEFAULT NULL,
  `projected_at` datetime DEFAULT NULL,
  PRIMARY KEY (`batch_id`,`user_id`),
  UNIQUE KEY `uk_system_notice_event` (`issued_event_id`),
  UNIQUE KEY `uk_system_notice_delivery` (`delivery_id`),
  KEY `idx_system_notice_projection` (`batch_id`,`projected_at`,`delivery_id`),
  CONSTRAINT `fk_system_notice_batch` FOREIGN KEY (`batch_id`) REFERENCES `system_notice_batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `capabilities` (`capability`,`description`,`risk_level`) VALUES
('notifications.publish','发布指定用户系统通告',3),
('notifications.broadcast','向全站用户发布系统通告',3)
ON DUPLICATE KEY UPDATE `description`=VALUES(`description`),`risk_level`=VALUES(`risk_level`);

-- 原元数据查询已收束为聚合统计，权限目录文案同步收束；不改变已有授权。
UPDATE `capabilities` SET `description`='查看站内通知聚合统计' WHERE `capability`='notifications.read';
