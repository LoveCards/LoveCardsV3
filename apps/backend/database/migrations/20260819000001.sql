-- Operations administration foundations.
-- Additive and idempotent on MySQL 5.7; no destructive rewrite.

CREATE TABLE IF NOT EXISTS `system_leases` (
  `lease_name` VARCHAR(128) NOT NULL,
  `holder_token` CHAR(64) NOT NULL,
  `lease_expires_at` DATETIME(6) NOT NULL,
  `revision` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME(6) NOT NULL,
  `updated_at` DATETIME(6) NOT NULL,
  PRIMARY KEY (`lease_name`),
  KEY `idx_system_leases_expiry` (`lease_expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP PROCEDURE IF EXISTS `mig_operations_foundation_20260819`;
DELIMITER //
CREATE PROCEDURE `mig_operations_foundation_20260819`()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reports' AND COLUMN_NAME = 'assignee_id'
    ) THEN
        ALTER TABLE `reports` ADD COLUMN `assignee_id` INT NULL AFTER `status`;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reports' AND COLUMN_NAME = 'resolution'
    ) THEN
        ALTER TABLE `reports` ADD COLUMN `resolution` VARCHAR(255) NULL AFTER `assignee_id`;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reports' AND COLUMN_NAME = 'resolved_at'
    ) THEN
        ALTER TABLE `reports` ADD COLUMN `resolved_at` DATETIME NULL AFTER `resolution`;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reports' AND INDEX_NAME = 'idx_reports_status_updated'
    ) THEN
        ALTER TABLE `reports` ADD KEY `idx_reports_status_updated` (`status`,`updated_at`,`id`);
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs'
          AND COLUMN_NAME = 'correlation_id'
    ) THEN
        ALTER TABLE `audit_logs`
            ADD COLUMN `correlation_id` VARCHAR(128) NULL AFTER `request_id`;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'audit_logs'
          AND INDEX_NAME = 'idx_audit_action_time'
    ) THEN
        ALTER TABLE `audit_logs`
            ADD KEY `idx_audit_action_time` (`action`,`created_at`,`id`);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_deliveries'
          AND INDEX_NAME = 'idx_delivery_status_updated'
    ) THEN
        ALTER TABLE `event_deliveries`
            ADD KEY `idx_delivery_status_updated` (`status`,`updated_at`,`id`);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications'
          AND INDEX_NAME = 'idx_notifications_type_created'
    ) THEN
        ALTER TABLE `notifications`
            ADD KEY `idx_notifications_type_created` (`type`,`created_at`,`id`);
    END IF;
END //
DELIMITER ;

CALL `mig_operations_foundation_20260819`();
DROP PROCEDURE IF EXISTS `mig_operations_foundation_20260819`;

-- 升级完成后立即同步标准化授权表；受外键约束，必须先写 capability 再写 role_grants。
INSERT INTO `capabilities` (`capability`, `description`, `risk_level`)
SELECT source.capability, source.description, source.risk_level
FROM (
  SELECT 'system.update' capability, '系统更新' description, 3 risk_level
  UNION ALL SELECT 'system.update.contract', '批准系统更新 Contract 阶段', 3
  UNION ALL SELECT 'system.recover', '恢复系统 Release 指针', 3
  UNION ALL SELECT 'files.update', '编辑文件', 2
  UNION ALL SELECT 'files.restore', '恢复文件', 2
  UNION ALL SELECT 'operations.read', '查看系统运行和迁移状态', 1
  UNION ALL SELECT 'operations.migrate', '推进系统迁移', 3
  UNION ALL SELECT 'operations.retry', '重试异步投递', 2
  UNION ALL SELECT 'audit.read', '查看审计日志', 2
  UNION ALL SELECT 'reports.read', '查看举报案件', 2
  UNION ALL SELECT 'reports.manage', '处理举报案件', 3
  UNION ALL SELECT 'users.security.read', '查看用户安全状态', 2
  UNION ALL SELECT 'users.sessions.revoke', '撤销用户 Session', 3
  UNION ALL SELECT 'users.recovery.read', '查看账号恢复状态', 2
  UNION ALL SELECT 'communications.read', '查看通知和投递运维元数据', 1
  UNION ALL SELECT 'community.insights.read', '查看社区隐私安全聚合', 1
  UNION ALL SELECT 'files.usage.read', '查看文件引用与访问诊断', 1
) source
LEFT JOIN `capabilities` existing ON existing.capability = source.capability
WHERE existing.capability IS NULL;

INSERT INTO `role_grants` (`role_id`, `capability`, `scope`, `created_at`)
SELECT source.role_id, source.capability, source.scope, NOW()
FROM (
  SELECT 1 role_id, 'operations.read' capability, 'global' scope
  UNION ALL SELECT 1, 'operations.migrate', 'global'
  UNION ALL SELECT 1, 'operations.retry', 'global'
  UNION ALL SELECT 1, 'audit.read', 'global'
  UNION ALL SELECT 1, 'reports.read', 'global'
  UNION ALL SELECT 1, 'reports.manage', 'global'
  UNION ALL SELECT 1, 'users.security.read', 'global'
  UNION ALL SELECT 1, 'users.sessions.revoke', 'global'
  UNION ALL SELECT 1, 'users.recovery.read', 'global'
  UNION ALL SELECT 1, 'communications.read', 'global'
  UNION ALL SELECT 1, 'community.insights.read', 'global'
  UNION ALL SELECT 1, 'files.usage.read', 'global'
  UNION ALL SELECT 2, 'system.update', 'global'
  UNION ALL SELECT 2, 'system.update.contract', 'global'
  UNION ALL SELECT 2, 'system.recover', 'global'
  UNION ALL SELECT 2, 'files.update', 'all'
  UNION ALL SELECT 2, 'files.restore', 'own'
  UNION ALL SELECT 2, 'operations.read', 'global'
  UNION ALL SELECT 2, 'operations.migrate', 'global'
  UNION ALL SELECT 2, 'operations.retry', 'global'
  UNION ALL SELECT 2, 'audit.read', 'global'
  UNION ALL SELECT 2, 'reports.read', 'global'
  UNION ALL SELECT 2, 'reports.manage', 'global'
  UNION ALL SELECT 2, 'users.security.read', 'global'
  UNION ALL SELECT 2, 'users.sessions.revoke', 'global'
  UNION ALL SELECT 2, 'users.recovery.read', 'global'
  UNION ALL SELECT 2, 'communications.read', 'global'
  UNION ALL SELECT 2, 'community.insights.read', 'global'
  UNION ALL SELECT 2, 'files.usage.read', 'global'
) source
LEFT JOIN `role_grants` existing
  ON existing.role_id = source.role_id
 AND existing.capability = source.capability
 AND existing.scope = source.scope
WHERE existing.role_id IS NULL;
