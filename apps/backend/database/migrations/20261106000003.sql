-- 运行时通信配置能力的角色种子（Phase 1 控制面）。
-- 与 SystemRolePolicy $roles['admin'] 对齐：legacy admin 角色（role_id=1、2）显式授权。
-- 幂等：LEFT JOIN 去重，可对已装实例重放。
INSERT INTO `role_grants` (`role_id`, `capability`, `scope`, `created_at`)
SELECT source.role_id, source.capability, source.scope, NOW()
FROM (
  SELECT 1 role_id, 'operations.runtime.configure' capability, 'global' scope
  UNION ALL SELECT 2, 'operations.runtime.configure', 'global'
) source
LEFT JOIN `role_grants` existing
  ON existing.role_id = source.role_id
 AND existing.capability = source.capability
 AND existing.scope = source.scope
WHERE existing.capability IS NULL;
