-- 登记站内通知元数据只读能力；现有非 root 角色须由超级管理员显式授权。
-- 不修改 role_capabilities，重放不会扩大既有角色权限。
-- @lc-phase expand
INSERT INTO `capabilities` (`capability`, `description`, `risk_level`)
SELECT 'notifications.read', '查看站内通知记录元数据', 2
WHERE NOT EXISTS (
  SELECT 1 FROM `capabilities` WHERE `capability` = 'notifications.read'
);

-- 全站列表按生成时间和编号倒序；仅补充默认查询必需的索引。
SET @lc_notifications_created_exists = (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE() AND table_name = 'notifications' AND index_name = 'idx_notifications_created'
);
SET @lc_notifications_created_sql = IF(
  @lc_notifications_created_exists > 0,
  'SELECT 1',
  'ALTER TABLE `notifications` ADD INDEX `idx_notifications_created` (`created_at`,`id`), ALGORITHM=INPLACE, LOCK=NONE'
);
PREPARE lc_notifications_created_stmt FROM @lc_notifications_created_sql;
EXECUTE lc_notifications_created_stmt;
DEALLOCATE PREPARE lc_notifications_created_stmt;
