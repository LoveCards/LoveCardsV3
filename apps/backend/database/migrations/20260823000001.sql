-- Install the six built-in administration role templates.
-- Slugs are stable; IDs remain installation-local so root/admin/user/guest keep
-- their historical 1/2/3/4 anchors. The migration is additive and never assigns
-- a role to an existing user or removes a grant added by a root administrator.
-- @lc-phase preflight

DROP PROCEDURE IF EXISTS `mig_admin_role_templates_preflight_20260823`;
DELIMITER //
CREATE PROCEDURE `mig_admin_role_templates_preflight_20260823`()
BEGIN
    DECLARE v_conflicts INT DEFAULT 0;

    SELECT COUNT(*) INTO v_conflicts
      FROM `roles`
     WHERE `slug` IN (
        'content_operator', 'community_operator', 'moderator',
        'security_admin', 'operations_admin', 'auditor'
     )
       AND (`is_system` <> 1 OR `deleted_at` IS NOT NULL);

    IF v_conflicts > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'reserved administration role slug is occupied by a custom or deleted role';
    END IF;
END //
DELIMITER ;

CALL `mig_admin_role_templates_preflight_20260823`();
DROP PROCEDURE IF EXISTS `mig_admin_role_templates_preflight_20260823`;

-- @lc-phase expand

DROP TEMPORARY TABLE IF EXISTS `tmp_lc_admin_roles_20260823`;
CREATE TEMPORARY TABLE `tmp_lc_admin_roles_20260823` (
  `slug` VARCHAR(50) NOT NULL,
  `name` VARCHAR(50) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `tmp_lc_admin_roles_20260823` (`slug`, `name`, `description`) VALUES
('content_operator', '内容运营', '负责卡片与标签的日常内容治理，不包含审核、用户或系统权限。'),
('community_operator', '社区运营', '负责评论修正、置顶、举报和社区聚合信息，不包含卡片编辑或系统配置。'),
('moderator', '审核员', '处理卡片、评论和举报审核，不获得正文编辑、删除或系统权限。'),
('security_admin', '安全管理员', '处理账号状态、会话和恢复事件，并读取安全相关审计记录。'),
('operations_admin', '运维管理员', '负责基础设施配置、事件投递、升级迁移和运行恢复。'),
('auditor', '审计员', '只读核对获授权的内容、用户、配置摘要、运行状态和审计记录。');

INSERT INTO `roles` (`name`, `slug`, `is_system`, `description`, `created_at`, `updated_at`, `deleted_at`)
SELECT template.`name`, template.`slug`, 1, template.`description`, NOW(), NOW(), NULL
  FROM `tmp_lc_admin_roles_20260823` template
  LEFT JOIN `roles` existing ON existing.`slug` = template.`slug`
 WHERE existing.`id` IS NULL;

UPDATE `roles` AS target
  JOIN `tmp_lc_admin_roles_20260823` AS template ON template.`slug` = target.`slug`
   SET target.`name` = template.`name`,
       target.`description` = template.`description`,
       target.`updated_at` = NOW()
 WHERE target.`name` <> template.`name`
    OR NOT (target.`description` <=> template.`description`);

DROP TEMPORARY TABLE IF EXISTS `tmp_lc_admin_grants_20260823`;
CREATE TEMPORARY TABLE `tmp_lc_admin_grants_20260823` (
  `role_slug` VARCHAR(50) NOT NULL,
  `capability` VARCHAR(100) NOT NULL,
  `scope` VARCHAR(16) NOT NULL,
  PRIMARY KEY (`role_slug`, `capability`, `scope`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `tmp_lc_admin_grants_20260823` (`role_slug`, `capability`, `scope`) VALUES
('content_operator', 'admin.access', 'global'),
('content_operator', 'dashboard.read', 'global'),
('content_operator', 'cards.read', 'all'),
('content_operator', 'cards.create', 'global'),
('content_operator', 'cards.update', 'all'),
('content_operator', 'cards.delete', 'all'),
('content_operator', 'tags.read', 'all'),
('content_operator', 'tags.create', 'global'),
('content_operator', 'tags.update', 'all'),
('content_operator', 'tags.delete', 'all'),
('content_operator', 'files.upload', 'global'),

('community_operator', 'admin.access', 'global'),
('community_operator', 'dashboard.read', 'global'),
('community_operator', 'comments.read', 'all'),
('community_operator', 'comments.update', 'all'),
('community_operator', 'comments.pin', 'all'),
('community_operator', 'comments.delete', 'all'),
('community_operator', 'reports.read', 'global'),
('community_operator', 'reports.manage', 'global'),
('community_operator', 'community.insights.read', 'global'),

('moderator', 'admin.access', 'global'),
('moderator', 'dashboard.read', 'global'),
('moderator', 'cards.read', 'all'),
('moderator', 'cards.approve', 'all'),
('moderator', 'comments.read', 'all'),
('moderator', 'comments.moderate', 'all'),
('moderator', 'reports.read', 'global'),
('moderator', 'reports.manage', 'global'),

('security_admin', 'admin.access', 'global'),
('security_admin', 'dashboard.read', 'global'),
('security_admin', 'users.read', 'all'),
('security_admin', 'users.update', 'all'),
('security_admin', 'users.security.read', 'global'),
('security_admin', 'users.sessions.revoke', 'global'),
('security_admin', 'users.recovery.read', 'global'),
('security_admin', 'audit.read', 'global'),

('operations_admin', 'admin.access', 'global'),
('operations_admin', 'dashboard.read', 'global'),
('operations_admin', 'operations.read', 'global'),
('operations_admin', 'operations.migrate', 'global'),
('operations_admin', 'operations.retry', 'global'),
('operations_admin', 'communications.read', 'global'),
('operations_admin', 'audit.read', 'global'),
('operations_admin', 'system.update', 'global'),
('operations_admin', 'system.update.contract', 'global'),
('operations_admin', 'system.recover', 'global'),
('operations_admin', 'config.read', 'global'),
('operations_admin', 'config.update', 'global'),
('operations_admin', 'storage.read', 'global'),
('operations_admin', 'storage.install', 'global'),
('operations_admin', 'storage.test', 'global'),
('operations_admin', 'sender.read', 'global'),
('operations_admin', 'sender.install', 'global'),
('operations_admin', 'sender.test', 'global'),
('operations_admin', 'captcha.read', 'global'),
('operations_admin', 'captcha.install', 'global'),
('operations_admin', 'files.read', 'all'),
('operations_admin', 'files.update', 'all'),
('operations_admin', 'files.delete', 'all'),
('operations_admin', 'files.restore', 'all'),
('operations_admin', 'files.purge', 'all'),
('operations_admin', 'files.cleanup', 'global'),
('operations_admin', 'files.usage.read', 'global'),

('auditor', 'admin.access', 'global'),
('auditor', 'dashboard.read', 'global'),
('auditor', 'cards.read', 'all'),
('auditor', 'comments.read', 'all'),
('auditor', 'tags.read', 'all'),
('auditor', 'reports.read', 'global'),
('auditor', 'community.insights.read', 'global'),
('auditor', 'users.read', 'all'),
('auditor', 'users.security.read', 'global'),
('auditor', 'users.recovery.read', 'global'),
('auditor', 'config.read', 'global'),
('auditor', 'storage.read', 'global'),
('auditor', 'sender.read', 'global'),
('auditor', 'captcha.read', 'global'),
('auditor', 'theme.read', 'global'),
('auditor', 'files.read', 'all'),
('auditor', 'files.usage.read', 'global'),
('auditor', 'operations.read', 'global'),
('auditor', 'communications.read', 'global'),
('auditor', 'audit.read', 'global');

INSERT INTO `capabilities` (`capability`, `description`, `risk_level`)
SELECT DISTINCT source.`capability`, source.`capability`, 0
  FROM `tmp_lc_admin_grants_20260823` source
  LEFT JOIN `capabilities` existing ON existing.`capability` = source.`capability`
 WHERE existing.`capability` IS NULL;

INSERT INTO `role_grants` (`role_id`, `capability`, `scope`, `created_at`)
SELECT role.`id`, source.`capability`, source.`scope`, NOW()
  FROM `tmp_lc_admin_grants_20260823` source
  JOIN `roles` role ON role.`slug` = source.`role_slug`
  LEFT JOIN `role_grants` existing
    ON existing.`role_id` = role.`id`
   AND existing.`capability` = source.`capability`
   AND existing.`scope` = source.`scope`
 WHERE existing.`role_id` IS NULL;

DROP TEMPORARY TABLE IF EXISTS `tmp_lc_admin_grants_20260823`;
DROP TEMPORARY TABLE IF EXISTS `tmp_lc_admin_roles_20260823`;
