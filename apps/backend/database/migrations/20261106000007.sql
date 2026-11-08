-- 通信运行时（Phase 3）：communications.configure 能力 + admin 角色种子（幂等，契约 §5.2/§6.2）。
INSERT INTO `capabilities` (`capability`, `description`, `risk_level`)
SELECT source.capability, source.description, source.risk_level
FROM (
  SELECT 'communications.configure' capability, '启停互动通知策略' description, 3 risk_level
) source
LEFT JOIN `capabilities` existing ON existing.capability = source.capability
WHERE existing.capability IS NULL;

INSERT INTO `role_grants` (`role_id`, `capability`, `scope`, `created_at`)
SELECT source.role_id, source.capability, source.scope, NOW()
FROM (
  SELECT 1 role_id, 'communications.configure' capability, 'global' scope
  UNION ALL SELECT 2, 'communications.configure', 'global'
) source
LEFT JOIN `role_grants` existing
  ON existing.role_id = source.role_id
 AND existing.capability = source.capability
 AND existing.scope = source.scope
WHERE existing.capability IS NULL;
