-- 系统角色模板差分种子：operations_admin + communications.configure（Phase 3 通知策略）。
-- 历史文件 20260823000001.sql 已固化（checksum 入 ledger，不可改写）；模板新增授权以差分工单追加（D7 工作流）。
-- 幂等：LEFT JOIN 去重，可对已装实例重放。role id 各环境不同，按 slug 解析。
INSERT INTO `role_grants` (`role_id`, `capability`, `scope`, `created_at`)
SELECT r.`id`, t.capability, t.scope, NOW()
FROM `roles` r
JOIN (
  SELECT 'operations_admin' AS role_slug, 'communications.configure' AS capability, 'global' AS scope
) t ON t.role_slug = r.`slug`
LEFT JOIN `role_grants` existing
  ON existing.`role_id` = r.`id`
 AND existing.`capability` = t.capability
 AND existing.`scope` = t.scope
WHERE existing.`role_id` IS NULL AND r.`deleted_at` IS NULL;
