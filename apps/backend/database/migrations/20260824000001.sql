-- Allow trusted administrators to correct comment bodies without restoring a
-- self-service comment update route.
-- @lc-phase expand

INSERT INTO `capabilities` (`capability`, `description`)
SELECT 'comments.update', '编辑评论正文'
WHERE NOT EXISTS (
  SELECT 1 FROM `capabilities` WHERE `capability` = 'comments.update'
);

INSERT INTO `role_grants` (`role_id`, `capability`, `scope`, `created_at`)
SELECT r.id, 'comments.update', 'all', NOW()
FROM `roles` r
WHERE r.slug IN ('root', 'admin', 'community_operator')
  AND r.deleted_at IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM `role_grants` rg
    WHERE rg.role_id = r.id
      AND rg.capability = 'comments.update'
      AND rg.scope = 'all'
  );

-- @lc-phase migrate
SELECT 1;

-- @lc-phase contract
SELECT 1;
