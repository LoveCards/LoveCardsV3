-- Grant ordinary users author-scoped comment pin management.
-- @lc-phase expand
INSERT INTO `role_grants` (`role_id`, `capability`, `scope`, `created_at`)
SELECT role.`id`, 'comments.pin', 'own', NOW()
FROM `roles` role
LEFT JOIN `role_grants` existing
  ON existing.`role_id` = role.`id`
 AND existing.`capability` = 'comments.pin'
 AND existing.`scope` = 'own'
WHERE role.`slug` = 'user'
  AND role.`deleted_at` IS NULL
  AND existing.`role_id` IS NULL;

-- @lc-phase contract
SELECT 1;
