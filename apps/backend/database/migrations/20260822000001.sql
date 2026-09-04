-- Authorization boundary: immutable comment bodies and explicit own-content grants.
-- Additive/idempotent. Existing custom grants are preserved.

INSERT INTO `capabilities` (`capability`, `description`, `risk_level`)
SELECT 'comments.moderate', '审核评论', 1
WHERE NOT EXISTS (
    SELECT 1 FROM `capabilities` WHERE `capability` = 'comments.moderate'
);

INSERT INTO `role_grants` (`role_id`, `capability`, `scope`, `created_at`)
SELECT 2, 'comments.moderate', 'all', NOW()
WHERE EXISTS (SELECT 1 FROM `roles` WHERE `id` = 2)
  AND NOT EXISTS (
      SELECT 1 FROM `role_grants`
      WHERE `role_id` = 2 AND `capability` = 'comments.moderate' AND `scope` = 'all'
  );

INSERT INTO `role_grants` (`role_id`, `capability`, `scope`, `created_at`)
SELECT 3, grants.capability, 'own', NOW()
FROM (
    SELECT 'cards.update' AS capability
    UNION ALL SELECT 'cards.delete'
    UNION ALL SELECT 'comments.delete'
    UNION ALL SELECT 'tags.update'
    UNION ALL SELECT 'tags.delete'
    UNION ALL SELECT 'files.delete'
) grants
WHERE EXISTS (SELECT 1 FROM `roles` WHERE `id` = 3)
  AND NOT EXISTS (
      SELECT 1 FROM `role_grants` existing
      WHERE existing.`role_id` = 3
        AND existing.`capability` = grants.`capability`
        AND existing.`scope` = 'own'
  );
