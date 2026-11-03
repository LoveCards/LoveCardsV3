-- Add authorization capabilities introduced after the normalized grant model.
-- Additive and idempotent for MySQL 5.7+.

INSERT INTO `capabilities` (`capability`, `description`, `risk_level`)
SELECT source.capability, source.description, source.risk_level
FROM (
  SELECT 'users.roles.assign' capability, 'Assign user roles' description, 2 risk_level
  UNION ALL SELECT 'files.restore', 'Restore files', 1
  UNION ALL SELECT 'files.purge', 'Permanently delete files', 3
  UNION ALL SELECT 'files.cleanup', 'Clean expired files', 2
) source
LEFT JOIN `capabilities` existing ON existing.capability = source.capability
WHERE existing.capability IS NULL;

INSERT INTO `role_grants` (`role_id`, `capability`, `scope`, `created_at`)
SELECT source.role_id, source.capability, source.scope, NOW()
FROM (
  SELECT 1 role_id, 'users.roles.assign' capability, 'global' scope
  UNION ALL SELECT 1, 'files.restore', 'all'
  UNION ALL SELECT 1, 'files.purge', 'all'
  UNION ALL SELECT 1, 'files.cleanup', 'global'
  UNION ALL SELECT 2, 'users.roles.assign', 'global'
  UNION ALL SELECT 2, 'files.restore', 'all'
  UNION ALL SELECT 2, 'files.purge', 'all'
  UNION ALL SELECT 2, 'files.cleanup', 'global'
) source
LEFT JOIN `role_grants` existing
  ON existing.role_id = source.role_id
 AND existing.capability = source.capability
 AND existing.scope = source.scope
WHERE existing.role_id IS NULL;
